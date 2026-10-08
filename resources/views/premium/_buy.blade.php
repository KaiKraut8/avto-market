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
            <label @class(['plan', 'plan-premium', 'plan-offer' => $plan === 'quarterly'])>
                <input type="radio" name="billing" value="{{ $plan }}" @checked($plan === 'monthly')>
                <span class="plan-card">
                    @if ($plan === 'quarterly')
                        <span class="offer-ribbon">{{ __('Offer') }}</span>
                    @endif
                    <b>{{ __(ucfirst($plan)) }}@if ($p['saving']) <span class="save-badge">{{ __('Save :percent%', ['percent' => $p['saving']]) }}</span>@endif</b>
                    <span class="plan-price">@eur($p['price']) <i>/ {{ Subscription::periodLabel($plan) }}</i></span>
                    @if ($p['saving'])
                        <small><s>@eur($p['full'])</s> {{ __('if paid monthly') }} &middot; {{ __('about :price a month', ['price' => Money::eur($p['price'] / $p['months'])]) }}</small>
                    @else
                        <small>{{ __('Cancel any time.') }}</small>
                    @endif
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
