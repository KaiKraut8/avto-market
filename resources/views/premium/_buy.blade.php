@php
    use App\Services\Pricing;
    use App\Support\Money;
@endphp
{{-- billing choice and buy button for one premium plan ($kind: seller or buyer) --}}
<form method="get" action="{{ route('checkout.create') }}" class="buy-box">
    <input type="hidden" name="product" value="{{ $kind }}">
    <fieldset class="plan-pick billing-pick">
        <legend>{{ __('Billing') }}</legend>
        <label class="plan plan-premium">
            <input type="radio" name="billing" value="monthly" checked>
            <span class="plan-card">
                <b>{{ __('Monthly') }}</b>
                <span class="plan-price">@eur($monthly) <i>/ {{ __('month') }}</i></span>
                <small>{{ __('Cancel any time.') }}</small>
            </span>
        </label>
        <label class="plan plan-premium">
            <input type="radio" name="billing" value="yearly">
            <span class="plan-card">
                <b>{{ __('Yearly') }} <span class="save-badge">{{ __('Save :percent%', ['percent' => Pricing::yearlySaving()]) }}</span></b>
                <span class="plan-price">@eur($yearly) <i>/ {{ __('year') }}</i></span>
                <small><s>@eur($full)</s> {{ __('if paid monthly') }} &middot; {{ __('about :price a month', ['price' => Money::eur($yearly / 12)]) }}</small>
            </span>
        </label>
    </fieldset>
    <div class="buy-total"><span>{{ __('Total') }}</span><b data-buy-total data-monthly="{{ Money::eur($monthly) }}" data-yearly="{{ Money::eur($yearly) }}">@eur($monthly)</b></div>
    @auth
        <button type="submit" class="btn gold big">&#9813; {{ $kind === 'buyer' ? __('Become a premium buyer') : __('Buy premium') }}</button>
        <p class="hint demo-note">{{ __('Pay by card, PayPal or paysafecard. Renews automatically until you cancel; cancel any time.') }}</p>
    @else
        <a class="btn gold big" href="{{ route('register') }}">{{ __('Create a free account') }}</a>
        <p class="hint demo-note">{{ __('Premium is for accounts. Already have one?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>.</p>
    @endauth
</form>
