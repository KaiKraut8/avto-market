<x-layouts.app :title="__('Checkout')" active="premium">

<main class="wrap checkout-page">
    <h1>{{ __('Checkout') }}</h1>

    @if (session('status'))
        <div class="notice">{{ session('status') }}</div>
    @endif
    @if (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    <div class="checkout-grid">
        <section class="panel order-summary">
            <h2>{{ __('Your order') }}</h2>
            <p class="order-title">@if ($product !== 'boost')&#9813; @endif{{ $title }}</p>
            @isset($breakdown)
                <div class="calc-rows small">
                    <div><span>{{ __('Price of the car') }}</span><b>@price($breakdown['price'])</b></div>
                    <div class="calc-online"><span>{{ __('You pay online now (:rate%, to KAI Garage)', ['rate' => $breakdown['rate']]) }}</span><b>@eur($breakdown['commission'])</b></div>
                    <div><span>{{ __('You pay the seller at the handover') }}</span><b>@price($breakdown['remainder'])</b></div>
                </div>
                <p class="hint">{{ __('The car is reserved for you as soon as this is paid. If the seller cancels, you get it back in full.') }}</p>
            @endisset
            @if ($product === 'renew')
                <p class="hint">{{ __('The next period of your subscription.') }}</p>
            @elseif ($product !== 'boost')
                <p class="hint">{{ __(ucfirst($billing)) }}</p>
            @endif
            <div class="buy-total">
                <span>{{ __('Total today') }}</span>
                <b>@eur($price)</b>
            </div>
            @if ($renews)
                <p class="renew-note">
                    <x-icon name="clock" :size="15" />
                    @if ($billing === 'yearly')
                        {{ __('Renews automatically every year at :price until you cancel. Cancel any time on your profile; you keep premium until the end of the period you paid for.', ['price' => \App\Support\Money::eur($price)]) }}
                    @elseif ($billing === 'quarterly')
                        {{ __('Renews automatically every 3 months at :price until you cancel. Cancel any time on your profile; you keep premium until the end of the period you paid for.', ['price' => \App\Support\Money::eur($price)]) }}
                    @else
                        {{ __('Renews automatically every month at :price until you cancel. Cancel any time on your profile; you keep premium until the end of the period you paid for.', ['price' => \App\Support\Money::eur($price)]) }}
                    @endif
                </p>
            @else
                <p class="renew-note"><x-icon name="check" :size="15" /> {{ __('One payment, nothing renews.') }}</p>
            @endif
        </section>

        <form method="post" action="{{ route('checkout.store') }}" class="panel">
            @csrf
            <input type="hidden" name="product" value="{{ $product }}">
            @if ($billing)<input type="hidden" name="billing" value="{{ $billing }}">@endif
            @isset($car)<input type="hidden" name="car" value="{{ $car->id }}">@endisset
            @isset($subscription)<input type="hidden" name="subscription" value="{{ $subscription->id }}">@endisset

            <h2>{{ __('Payment method') }}</h2>
            @error('method')
                <ul class="form-errors"><li>{{ $message }}</li></ul>
            @enderror
            <fieldset class="pay-methods">
                <legend class="visually-hidden">{{ __('Payment method') }}</legend>
                @foreach ($methods as $i => $method)
                    <label class="pay-method">
                        <input type="radio" name="method" value="{{ $method }}" @checked(old('method', $methods[0]) === $method)>
                        <span class="pay-card">
                            <span class="pay-logo pay-{{ $method }}">
                                @switch($method)
                                    @case('creditcard') <x-icon name="card" :size="22" /> @break
                                    @case('paypal') <b>Pay</b><i>Pal</i> @break
                                    @case('paysafecard') <b>paysafe</b>card @break
                                @endswitch
                            </span>
                            <span class="pay-text">
                                <b>{{ $method === 'creditcard' ? __('Credit or debit card') : \App\Models\Payment::methodName($method) }}</b>
                                <small>
                                    @switch($method)
                                        @case('creditcard') {{ __('Visa, Mastercard, Maestro, American Express.') }} @break
                                        @case('paypal') {{ __('Pay with your PayPal account.') }} @break
                                        @case('paysafecard') {{ __('Prepaid voucher from a shop or kiosk.') }} @break
                                    @endswitch
                                    @if ($renews)
                                        @if (in_array($method, config('payments.recurring'), true))
                                            {{ __('Renews automatically.') }}
                                        @else
                                            <span class="amber">{{ __('Can\'t renew automatically: we remind you to pay again before it ends.') }}</span>
                                        @endif
                                    @endif
                                </small>
                            </span>
                        </span>
                    </label>
                @endforeach
            </fieldset>
            @if (in_array($product, ['reserve', 'commission'], true))
                @error('terms')
                    <ul class="form-errors"><li>{{ $message }}</li></ul>
                @enderror
                <label class="remember terms-check"><input type="checkbox" name="terms" value="1" required> {!! __('I have read <a href=":url" target="_blank">how buying works</a> and agree to it.', ['url' => route('how-buying')]) !!}</label>
            @endif
            <button type="submit" class="btn gold big pay-button">{{ __('Continue to payment') }} &middot; @eur($price)</button>
            <p class="hint secure-note"><x-icon name="lock" :size="14" />
                @if ($testMode)
                    <span class="amber">{{ __('Test mode: no payment provider is connected yet, so the next page only simulates the payment. No money is taken.') }}</span>
                @else
                    {{ __('You pay on the secure page of our payment provider, Mollie. We never see or store your card details.') }}
                @endif
            </p>
        </form>
    </div>
</main>

</x-layouts.app>
