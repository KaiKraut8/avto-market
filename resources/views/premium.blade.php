@php
    use App\Services\Pricing;
    use App\Support\Money;
@endphp
<x-layouts.app :title="__('Premium')" active="premium">

<main class="wrap premium-page">
    <div class="premium-head">
        <span class="premium-hero-crown" aria-hidden="true">&#9813;</span>
        <h1>{{ __('Premium') }} <span>{{ __('seller account') }}</span></h1>
        <p class="lede">{{ __('Every car you list is shown first on All cars and the home page, in gold.') }}</p>
    </div>

    @if (session('status'))
        <div class="notice">&#9813; {{ session('status') }} <a href="{{ route('cars.index') }}">{{ __('See your cars at the top') }}</a>.</div>
    @elseif (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    @if ($user?->hasPremium())
        <div class="panel premium-status">
            <h2>&#9813; {{ __("You're a premium seller") }}</h2>
            <p>{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->premium_plan)), 'date' => $user->premium_until->format('Y-m-d')]) }}</p>
            <a class="btn gold" href="{{ route('cars.create') }}">+ {{ __('Sell a car') }}</a>
        </div>
    @else
        <form method="post" action="{{ route('premium.store') }}" class="panel buy-box">
            @csrf
            <fieldset class="plan-pick billing-pick">
                <legend>{{ __('Billing') }}</legend>
                <label class="plan plan-premium">
                    <input type="radio" name="billing" value="monthly" checked>
                    <span class="plan-card">
                        <b>{{ __('Monthly') }}</b>
                        <span class="plan-price">@eur(Pricing::monthly()) <i>/ {{ __('month') }}</i></span>
                        <small>{{ __('Cancel any time.') }}</small>
                    </span>
                </label>
                <label class="plan plan-premium">
                    <input type="radio" name="billing" value="yearly">
                    <span class="plan-card">
                        <b>{{ __('Yearly') }}</b>
                        <span class="plan-price">@eur(Pricing::yearly()) <i>/ {{ __('year') }}</i></span>
                        <small><s>@eur(Pricing::yearlyAtMonthlyRate())</s> {{ __('if paid monthly') }} &middot; {{ __('about :price a month', ['price' => Money::eur(Pricing::yearly() / 12)]) }}</small>
                        <span class="save-badge">{{ __('Save :percent%', ['percent' => Pricing::yearlySaving()]) }}</span>
                    </span>
                </label>
            </fieldset>
            <div class="buy-total"><span>{{ __('Total') }}</span><b id="buy-total" data-monthly="{{ Money::eur(Pricing::monthly()) }}" data-yearly="{{ Money::eur(Pricing::yearly()) }}">@eur(Pricing::monthly())</b></div>
            @auth
                <button type="submit" class="btn gold big">&#9813; {{ __('Buy premium') }}</button>
                <p class="hint demo-note">{{ __('Demo: premium is switched on right away and no payment is taken.') }}</p>
            @else
                <a class="btn gold big" href="{{ route('register') }}">{{ __('Create a seller account') }}</a>
                <p class="hint demo-note">{{ __('Premium is for seller accounts. Already have one?') }} <a href="{{ route('login') }}">{{ __('Log in') }}</a>.</p>
            @endauth
        </form>
    @endif
</main>

</x-layouts.app>
