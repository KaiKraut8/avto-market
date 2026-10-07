<?php

namespace App\Services;

use App\Models\Car;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Notifications\SiteAlert;
use App\Services\Payments\PaymentGateway;
use Illuminate\Support\Facades\DB;
use RuntimeException;

// Premium subscriptions and push-forward payments.
//  - A subscription starts when its first payment is paid and renews every month or year until cancelled.
//  - Cards and PayPal are charged again automatically (the first payment creates a mandate at the provider);
//    paysafecard is prepaid, so its customers get a reminder and pay each period themselves.
//  - Cancelling keeps premium until the end of the paid period, then the subscription ends.
// The provider's word is final: every payment is checked with it (sync) before anything is handed out.
class Billing
{
    public function __construct(private PaymentGateway $gateway, private Pricing $pricing) {}

    public function startSubscription(User $user, string $kind, string $plan, string $method): Payment
    {
        if ($user->currentSubscription($kind)) {
            throw new RuntimeException(__('You already have this premium plan. You can manage it on your profile.'));
        }
        $subscription = $user->subscriptions()->create(['kind' => $kind, 'plan' => $plan, 'method' => $method, 'status' => 'pending']);
        $recurring = $this->canRecur($method);
        if ($recurring) {
            $subscription->forceFill(['provider_customer_id' => $this->customerId($user)])->save();
        }
        $payment = $this->newPayment($user, [
            'subscription_id' => $subscription->id,
            'purpose' => 'subscription',
            'amount' => $subscription->price(),
            'method' => $method,
            'description' => $this->describe($subscription),
        ]);
        $this->send($payment, $recurring ? 'first' : 'oneoff', ['customer_id' => $subscription->provider_customer_id]);

        return $payment;
    }

    // Paying the next period by hand: paysafecard customers, or after an automatic renewal failed.
    // Paying with a card or PayPal here also switches the subscription to automatic renewal with that method.
    public function payNextPeriod(Subscription $subscription, string $method): Payment
    {
        $recurring = $this->canRecur($method);
        if ($recurring && ! $subscription->provider_customer_id) {
            $subscription->forceFill(['provider_customer_id' => $this->customerId($subscription->user)])->save();
        }
        $payment = $this->newPayment($subscription->user, [
            'subscription_id' => $subscription->id,
            'purpose' => 'renewal',
            'amount' => $subscription->price(),
            'method' => $method,
            'description' => $this->describe($subscription),
        ]);
        $this->send($payment, $recurring ? 'first' : 'oneoff', ['customer_id' => $subscription->provider_customer_id]);

        return $payment;
    }

    public function startBoost(User $user, Car $car, string $method): Payment
    {
        $payment = $this->newPayment($user, [
            'car_id' => $car->id,
            'purpose' => 'boost',
            'amount' => Pricing::boostWeekly(),
            'method' => $method,
            'description' => __('Push forward: :car, 1 week', ['car' => $car->name]),
        ]);
        $this->send($payment, 'oneoff', []);

        return $payment;
    }

    // Ask the provider how the payment stands and act on it. Safe to call any number of times
    // (webhook, return page, renewal run): what was paid for is handed out exactly once.
    public function sync(Payment $payment): Payment
    {
        if ($payment->applied_at || ($payment->isFinal() && $payment->status !== 'paid')) {
            return $payment;
        }
        $result = $this->gateway->fetch($payment);
        $status = match ($result['status']) {
            'paid' => 'paid',
            'failed' => 'failed',
            'canceled' => 'canceled',
            'expired' => 'expired',
            default => 'open',   // open, pending, authorized
        };

        return DB::transaction(function () use ($payment, $status, $result) {
            $payment = Payment::lockForUpdate()->find($payment->id);
            if ($payment->applied_at) {
                return $payment;
            }
            $was = $payment->status;
            $payment->status = $status;
            if ($status === 'paid') {
                $payment->paid_at ??= now();
                $this->apply($payment, $result['mandate_id'] ?? null);
                $payment->applied_at = now();
            }
            $payment->save();
            if ($was !== $status && in_array($status, ['failed', 'canceled', 'expired'], true)) {
                $this->failed($payment);
            }

            return $payment;
        });
    }

    public function cancel(Subscription $subscription): void
    {
        $subscription->forceFill(['cancel_at_period_end' => true, 'canceled_at' => now()])->save();
    }

    public function resume(Subscription $subscription): void
    {
        if ($subscription->current_period_end?->isFuture()) {
            $subscription->forceFill(['cancel_at_period_end' => false, 'canceled_at' => null])->save();
        }
    }

    // Run every hour (routes/console.php): charge renewals that are due, remind paysafecard customers,
    // and end subscriptions that were cancelled or could not be renewed.
    public function renewDue(): array
    {
        $done = ['charged' => 0, 'reminded' => 0, 'ended' => 0];
        $soon = now()->addHours((int) config('payments.renew_before_hours'));
        $remindFrom = now()->addDays((int) config('payments.remind_before_days'));

        $due = Subscription::current()->where('cancel_at_period_end', false)->where('current_period_end', '<=', $remindFrom)->with('user')->get();
        foreach ($due as $subscription) {
            if ($subscription->payments()->where('status', 'open')->where('created_at', '>', now()->subDay())->exists()) {
                continue;   // a payment for this period is under way
            }
            if ($subscription->renewsAutomatically()) {
                $lastFail = $subscription->payments()->where('purpose', 'renewal')->whereIn('status', ['failed', 'canceled', 'expired'])->value('updated_at');
                if ($subscription->current_period_end <= $soon && $subscription->failed_renewals < (int) config('payments.max_failed_renewals')
                    && (! $lastFail || now()->diffInHours($lastFail, true) >= 20)) {
                    $this->chargeRenewal($subscription);
                    $done['charged']++;
                }
            } elseif (! $subscription->reminded_at) {
                $subscription->user->notify(new SiteAlert('renewal_due', [
                    'subscription_id' => $subscription->id,
                    'plan' => $subscription->kind,
                    'date' => $subscription->current_period_end->format('Y-m-d'),
                    'price' => $subscription->price(),
                ]));
                $subscription->forceFill(['reminded_at' => now()])->save();
                $done['reminded']++;
            }
        }

        foreach (Subscription::current()->where('current_period_end', '<', now())->get() as $subscription) {
            $stuck = $subscription->cancel_at_period_end
                || ! $subscription->renewsAutomatically()
                || $subscription->failed_renewals >= (int) config('payments.max_failed_renewals');
            if ($stuck) {
                $subscription->forceFill(['status' => 'ended'])->save();
                $done['ended']++;
            }
        }

        return $done;
    }

    private function chargeRenewal(Subscription $subscription): void
    {
        $payment = $this->newPayment($subscription->user, [
            'subscription_id' => $subscription->id,
            'purpose' => 'renewal',
            'amount' => $subscription->price(),
            'method' => $subscription->method,
            'description' => $this->describe($subscription),
        ]);
        $this->send($payment, 'recurring', [
            'customer_id' => $subscription->provider_customer_id,
            'mandate_id' => $subscription->provider_mandate_id,
        ]);
        $this->sync($payment);
    }

    // Hand out what was paid for
    private function apply(Payment $payment, ?string $mandateId): void
    {
        if ($payment->purpose === 'boost') {
            $this->pricing->boost($payment->car, force: true);

            return;
        }
        $subscription = $payment->subscription;
        $user = $subscription->user;
        // the new period follows on from what is already paid for (also premium bought before payments existed)
        $already = $subscription->kind === 'seller' ? $user->premium_until : $user->buyer_premium_until;
        $from = collect([now(), $subscription->current_period_end, $already])->filter()->max();

        $subscription->forceFill([
            'status' => 'active',
            'method' => $payment->method,
            'current_period_end' => $from->copy()->addMonths($subscription->months()),
            'failed_renewals' => 0,
            'reminded_at' => null,
        ]);
        if ($mandateId) {
            $subscription->provider_mandate_id = $mandateId;
        } elseif (! $this->canRecur($payment->method)) {
            $subscription->provider_mandate_id = null;   // paid with paysafecard: the next period is paid by hand too
        }
        $subscription->save();

        $prefix = $subscription->kind === 'seller' ? 'premium' : 'buyer_premium';
        $user->forceFill([
            "{$prefix}_plan" => $subscription->plan,
            "{$prefix}_since" => $payment->purpose === 'subscription' ? now() : ($user->{"{$prefix}_since"} ?? now()),
            "{$prefix}_until" => $subscription->current_period_end,
        ])->save();
    }

    private function failed(Payment $payment): void
    {
        $subscription = $payment->subscription;
        if (! $subscription) {
            return;
        }
        if ($subscription->status === 'pending') {
            $subscription->forceFill(['status' => 'ended'])->save();   // never got going

            return;
        }
        if ($payment->purpose === 'renewal') {
            $subscription->forceFill(['status' => 'past_due', 'failed_renewals' => $subscription->failed_renewals + 1])->save();
            $subscription->user->notify(new SiteAlert('payment_failed', [
                'subscription_id' => $subscription->id,
                'plan' => $subscription->kind,
                'date' => $subscription->current_period_end?->format('Y-m-d'),
            ]));
        }
    }

    private function newPayment(User $user, array $values): Payment
    {
        return Payment::create($values + ['user_id' => $user->id, 'provider' => $this->gateway->name()]);
    }

    private function send(Payment $payment, string $sequence, array $options): void
    {
        $result = $this->gateway->create($payment, $sequence, $options + [
            'redirect_url' => route('checkout.return', $payment),
            'webhook_url' => $this->webhookUrl(),
        ]);
        $payment->forceFill(['provider_id' => $result['id'], 'checkout_url' => $result['checkout_url']])->save();
    }

    private function customerId(User $user): string
    {
        return $user->subscriptions()->whereNotNull('provider_customer_id')->value('provider_customer_id')
            ?? $this->gateway->createCustomer($user);
    }

    private function canRecur(string $method): bool
    {
        return in_array($method, config('payments.recurring'), true);
    }

    private function describe(Subscription $subscription): string
    {
        return config('app.name').' '.$subscription->label().' ('.__($subscription->plan).')';
    }

    // The provider calls this address when a payment changes. It has to be reachable from the internet,
    // so it is left out on a local machine (the return page checks the payment instead).
    private function webhookUrl(): ?string
    {
        $host = parse_url(config('app.url'), PHP_URL_HOST);

        return in_array($host, ['localhost', '127.0.0.1'], true) || str_ends_with((string) $host, '.test') ? null : route('webhooks.mollie');
    }
}
