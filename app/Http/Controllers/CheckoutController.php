<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarSale;
use App\Models\Payment;
use App\Models\Subscription;
use App\Services\Billing;
use App\Services\Payments\TestGateway;
use App\Services\Pricing;
use App\Support\Money;
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

        return view('checkout.create', $order + ['methods' => $this->methods($order['price']), 'testMode' => config('payments.driver') === 'test']);
    }

    public function store(Request $request, Billing $billing): RedirectResponse
    {
        $order = $this->order($request);
        if ($order instanceof RedirectResponse) {
            return $order;
        }
        $method = $request->validate([
            'method' => ['required', Rule::in($this->methods($order['price']))],
            // the sale rules have to be accepted before money moves
            'terms' => [in_array($order['product'], ['reserve', 'commission'], true) ? 'accepted' : 'nullable'],
        ], ['terms.accepted' => __('Please confirm that you have read how buying works.')])['method'];
        $user = $request->user();

        try {
            $payment = match ($order['product']) {
                'seller', 'buyer' => $billing->startSubscription($user, $order['product'], $order['billing'], $method),
                'renew' => $billing->payNextPeriod($order['subscription'], $method),
                'boost' => $billing->startBoost($user, $order['car'], $method),
                'reserve' => $billing->startReservation($user, $order['car'], $method),
                'commission' => $billing->startCommission($user, $order['sale'], $method),
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

        $target = in_array($payment->purpose, ['boost', 'reservation'], true) && $payment->car ? route('cars.show', $payment->car) : route('account');

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
            'product' => ['required', Rule::in(['seller', 'buyer', 'renew', 'boost', 'reserve', 'commission'])],
            'sale' => ['nullable', 'integer'],
            'billing' => ['nullable', Rule::in(array_keys(Pricing::PLANS))],
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
                'period' => Subscription::periodLabel($billing),
                'renews' => true,
            ];
        }

        if ($product === 'renew') {
            $subscription = $user->subscriptions()->current()->findOrFail($data['subscription'] ?? 0);

            return [
                'product' => 'renew', 'billing' => $subscription->plan, 'subscription' => $subscription, 'price' => $subscription->price(),
                'title' => $subscription->label(),
                'period' => Subscription::periodLabel($subscription->plan),
                'renews' => true,
            ];
        }

        if ($product === 'commission') {
            $sale = CarSale::where('seller_id', $user->id)->where('status', 'due')->with('car')->findOrFail($data['sale'] ?? 0);

            return [
                'product' => 'commission', 'billing' => null, 'sale' => $sale, 'car' => $sale->car, 'price' => (float) $sale->commission,
                'title' => __('Commission (:rate%): :car', ['rate' => (float) $sale->rate, 'car' => $sale->car->name]),
                'period' => null, 'renews' => false,
            ];
        }

        $car = Car::with('activeSale')->findOrFail($data['car'] ?? 0);
        if ($product === 'reserve') {
            if ((int) $car->user_id === (int) $user->id) {
                return redirect()->route('cars.show', $car)->with('status', __('This is your own car.'));
            }
            if (! $car->isBuyable()) {
                return redirect()->route('cars.show', $car)->with('error', __('This car can\'t be bought right now.'));
            }
            $price = $car->salePrice($user);   // a running deal counts
            $commission = CarSale::commissionFor($price);

            return [
                'product' => 'reserve', 'billing' => null, 'car' => $car, 'price' => $commission,
                'title' => __('Reservation: :car', ['car' => $car->name]),
                'period' => null, 'renews' => false,
                'breakdown' => ['price' => $price, 'commission' => $commission, 'remainder' => round($price - $commission, 2), 'rate' => CarSale::rate()],
            ];
        }
        Gate::authorize('update', $car);
        if ($car->isSold()) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already reserved or sold.'));
        }
        if ($car->isPremium()) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already premium, so it is shown first anyway.'));
        }

        return [
            'product' => 'boost', 'billing' => null, 'car' => $car, 'price' => Pricing::boostWeekly(),
            'title' => __('Push forward: :car, 1 week', ['car' => $car->name]),
            'period' => null, 'renews' => false,
        ];
    }

    // Methods that can take this amount (paysafecard has a ceiling)
    private function methods(float $amount): array
    {
        return array_values(array_filter(config('payments.methods'), fn ($m) => $amount <= (config('payments.max_amount')[$m] ?? INF)));
    }

    private function paidMessage(Payment $payment): string
    {
        if ($payment->purpose === 'reservation') {
            $sale = $payment->sale->refresh();
            if ($sale->status !== 'reserved') {
                return __('Someone else bought this car a moment before you. Your payment is being refunded.');
            }

            return __('Payment received: :car is reserved for you. The seller will contact you; at the handover you pay them :remainder.', [
                'car' => $payment->car->name, 'remainder' => Money::price($sale->remainder()),
            ]);
        }
        if ($payment->purpose === 'commission') {
            return __('Thank you: the commission is paid and you can list cars again.');
        }
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
