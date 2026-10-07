<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing;
use App\Services\Payments\TestGateway;
use App\Services\Pricing;
use Illuminate\Http\Client\RequestException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use RuntimeException;

// Paying for premium (seller or buyer) or a push forward: choose a payment method, pay at the provider,
// come back here. Products: seller, buyer (subscriptions), renew (the next period, paid by hand), boost (one week).
class CheckoutController extends Controller
{
    public function create(Request $request): View|RedirectResponse
    {
        $order = $this->order($request);
        if ($order instanceof RedirectResponse) {
            return $order;
        }

        return view('checkout.create', $order + ['methods' => config('payments.methods'), 'testMode' => config('payments.driver') === 'test']);
    }

    public function store(Request $request, Billing $billing): RedirectResponse
    {
        $order = $this->order($request);
        if ($order instanceof RedirectResponse) {
            return $order;
        }
        $method = $request->validate(['method' => ['required', Rule::in(config('payments.methods'))]])['method'];
        $user = $request->user();

        try {
            $payment = match ($order['product']) {
                'seller', 'buyer' => $billing->startSubscription($user, $order['product'], $order['billing'], $method),
                'renew' => $billing->payNextPeriod($order['subscription'], $method),
                'boost' => $billing->startBoost($user, $order['car'], $method),
            };
        } catch (RuntimeException $e) {
            return redirect()->route('account')->with('error', $e->getMessage());
        } catch (RequestException) {
            return back()->with('error', __('The payment provider could not be reached. Please try again in a moment.'));
        }

        return redirect()->away($payment->checkout_url);
    }

    // The provider sends the customer back here, paid or not
    public function return(Request $request, Payment $payment, Billing $billing): RedirectResponse
    {
        abort_unless((int) $payment->user_id === (int) $request->user()->id, 404);
        $payment = $billing->sync($payment);

        $target = $payment->purpose === 'boost' && $payment->car ? route('cars.show', $payment->car) : route('account');

        return match ($payment->status) {
            'paid' => redirect()->to($target)->with('status', $this->paidMessage($payment)),
            'open' => redirect()->to($target)->with('status', __('Your payment is being processed. Premium switches on as soon as it is confirmed.')),
            default => redirect()->to($target)->with('error', __('The payment was not completed, so nothing was charged. You can try again.')),
        };
    }

    // ---- local test payments (no Mollie key) ----

    public function test(Request $request, Payment $payment): View
    {
        abort_unless($payment->provider === 'test' && (int) $payment->user_id === (int) $request->user()->id, 404);

        return view('checkout.test', ['payment' => $payment]);
    }

    public function testComplete(Request $request, Payment $payment): RedirectResponse
    {
        abort_unless($payment->provider === 'test' && (int) $payment->user_id === (int) $request->user()->id, 404);
        $outcome = $request->validate(['outcome' => ['required', Rule::in(['paid', 'failed', 'canceled'])]])['outcome'];
        TestGateway::setStatus($payment->provider_id, $outcome);

        return redirect()->route('checkout.return', $payment);
    }

    // What is being bought, from the query string or form; a redirect when it can't be bought
    private function order(Request $request): array|RedirectResponse
    {
        $data = $request->validate([
            'product' => ['required', Rule::in(['seller', 'buyer', 'renew', 'boost'])],
            'billing' => ['nullable', Rule::in(['monthly', 'yearly'])],
            'car' => ['nullable', 'integer'],
            'subscription' => ['nullable', 'integer'],
        ]);
        $user = $request->user();
        $product = $data['product'];
        $billing = $data['billing'] ?? 'monthly';

        if (in_array($product, ['seller', 'buyer'], true)) {
            if ($user->currentSubscription($product)) {
                return redirect()->route('account')->with('status', __('You already have this premium plan. You can manage it on your profile.'));
            }
            $price = Subscription::priceFor($product, $billing);

            return [
                'product' => $product, 'billing' => $billing, 'price' => $price,
                'title' => $product === 'buyer' ? __('Premium buyer') : __('Premium seller'),
                'period' => $billing === 'yearly' ? __('year') : __('month'),
                'renews' => true,
            ];
        }

        if ($product === 'renew') {
            $subscription = $user->subscriptions()->current()->findOrFail($data['subscription'] ?? 0);

            return [
                'product' => 'renew', 'billing' => $subscription->plan, 'subscription' => $subscription, 'price' => $subscription->price(),
                'title' => $subscription->label(),
                'period' => $subscription->plan === 'yearly' ? __('year') : __('month'),
                'renews' => true,
            ];
        }

        $car = Car::findOrFail($data['car'] ?? 0);
        Gate::authorize('update', $car);
        if ($car->isPremium()) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already premium, so it is shown first anyway.'));
        }

        return [
            'product' => 'boost', 'billing' => null, 'car' => $car, 'price' => Pricing::boostWeekly(),
            'title' => __('Push forward: :car, 1 week', ['car' => $car->name]),
            'period' => null, 'renews' => false,
        ];
    }

    private function paidMessage(Payment $payment): string
    {
        if ($payment->purpose === 'boost') {
            $car = $payment->car->refresh();

            return __('Payment received. Pushed forward until :date: shown first among the regular cars.', ['date' => $car->boosted_until->format('Y-m-d H:i')]);
        }
        $subscription = $payment->subscription->refresh();

        return __('Payment received. :plan is active until :date.', ['plan' => $subscription->label(), 'date' => $subscription->current_period_end->format('Y-m-d')])
            .' '.($subscription->renewsAutomatically()
                ? __('It renews automatically until you cancel.')
                : __('paysafecard can\'t be charged again automatically: we will remind you before it ends.'));
    }
}
