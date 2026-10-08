@php
    use App\Models\Subscription;
    use App\Services\Pricing;
    use App\Support\Money;
    $plans = collect(Pricing::PLANS)->map(fn ($months, $plan) => [
        'months' => $months,
        'price' => Pricing::price($kind, $plan),
        'full' => Pricing::fullPrice($kind, $plan),
        'saving' => Pricing::saving($plan),
    ]);
@endphp
{{-- billing choice and buy button for one premium plan ($kind: seller or buyer) --}}
<form method="get" action="{{ route('checkout.create') }}" class="buy-box">
    <input type="hidden" name="product" value="{{ $kind }}">
    <fieldset class="plan-pick billing-pick">
        <legend>{{ __('Billing') }}</legend>
        @foreach ($plans as $plan => $p)
            <label @class(['plan', 'plan-premium', 'plan-offer' => $plan === 'quarterly', 'plan-best' => $plan === 'yearly'])>
                <input type="radio" name="billing" value="{{ $plan }}" @checked($plan === 'monthly')>
                <span class="plan-card">
                    @if ($plan === 'quarterly')
                        <span class="offer-ribbon">{{ __('Most popular') }}</span>
                    @elseif ($plan === 'yearly')
                        <span class="offer-ribbon best">{{ __('Best offer') }}</span>
                    @endif
                    <b>{{ __(ucfirst($plan)) }}</b>
                    {{-- the price per month; with a discount, the full monthly price struck through beside it --}}
                    <span class="plan-price">@if ($p['saving'])<s>{{ Money::eur($p['full'] / $p['months']) }}</s> @endif{{ Money::eur($p['price'] / $p['months']) }} <i>/ {{ __('month') }}</i></span>
                </span>
            </label>
        @endforeach
    </fieldset>
    <div class="buy-total"><span>{{ __('Total') }}</span><b data-buy-total @foreach ($plans as $plan => $p) data-{{ $plan }}="{{ Money::eur($p['price']) }}" @endforeach>@eur($plans['monthly']['price'])</b></div>
    @auth
        <button type="submit" class="btn gold big">&#9813; {{ $kind === 'buyer' ? __('Become a premium buyer') : __('Buy premium') }}</button>
        <p class="hint demo-note">{{ __('Pay by card, PayPal or paysafecard. Renews automatically until you cancel; cancel any time.') }}</p>
    @else
        <a class="btn gold big" href="{{ route('register') }}">{{ __('Create a free account') }}</a>
        <p class="hint demo-note">{{ __('Premium is for accounts. Already have one?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>.</p>
    @endauth
</form>
