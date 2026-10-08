@php
    use App\Services\Pricing;
    use App\Support\Money;
@endphp
<x-layouts.app :title="__('Premium')" active="premium">

<main class="wrap premium-page">
    <div class="premium-head">
        <span class="premium-hero-crown" aria-hidden="true">&#9813;</span>
        <h1>{{ __('Premium') }}</h1>
        <p class="lede">{{ __('Two plans: one for selling cars faster, one for buying them for less.') }}</p>
        <nav class="premium-switch">
            <a href="#sellers">{{ __('For sellers') }}</a>
            <a href="#buyers">{{ __('For buyers') }}</a>
        </nav>
    </div>

    @if (session('status'))
        <div class="notice">&#9813; {{ session('status') }}</div>
    @elseif (session('error'))
        <div class="notice error">{{ session('error') }}</div>
    @endif

    <div class="premium-plans">
        {{-- sellers --}}
        <section class="panel premium-plan" id="sellers">
            <p class="eyebrow">{{ __('For sellers') }}</p>
            <h2>&#9813; {{ __('Premium seller') }}</h2>
            <ul class="perks">
                <li><b>{{ __('Top placement, in gold') }}</b> {{ __('Every car you list is shown first on All cars and in the home page highlights.') }}</li>
                <li><b>{{ __('Special deals') }}</b> {{ __('Cut a price for 3, 7 or 14 days. The car gets a deal badge, a place on the Deals page and the home page, and buyers who saved it or similar cars get an alert.') }}</li>
                <li><b>{{ __('Member prices') }}</b> {{ __('Offer premium buyers an even lower price, the buyers most ready to buy.') }}</li>
                <li><b>{{ __('Insights') }}</b> {{ __('Views over 30 days, people, saves, inquiries and how many lookers contacted you, for every car.') }}</li>
                <li><b>{{ __('Interest alerts') }}</b> {{ __('Know the moment someone saves one of your cars.') }}</li>
            </ul>
            <p class="hint">{{ __('Push forward (:price a week per car) only moves one car up the list. Premium gives every car all of the above.', ['price' => Money::eur(Pricing::boostWeekly())]) }}</p>

            @if ($user?->hasPremium())
                <div class="premium-status">
                    <h3>&#9813; {{ __("You're a premium seller") }}</h3>
                    <p>{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->premium_plan)), 'date' => $user->premium_until->format('Y-m-d')]) }}</p>
                    <a class="btn gold" href="{{ route('account') }}">{{ __('See your insights') }}</a>
                    <a class="btn ghost" href="{{ route('account') }}#subscriptions">{{ __('Manage subscription') }}</a>
                </div>
            @else
                @include('premium._buy', ['kind' => 'seller'])
            @endif
        </section>

        {{-- buyers --}}
        <section class="panel premium-plan buyer-plan" id="buyers">
            <p class="eyebrow">{{ __('For buyers') }}</p>
            <h2>&#9813; {{ __('Premium buyer') }}</h2>
            <ul class="perks">
                <li><b>{{ __('Member prices') }}</b> {{ __('Many deals have a lower price only premium buyers get, and the seller is told you are a member when you contact them.') }}</li>
                <li><b>{{ __('Deals you might like') }}</b> {{ __('An alert when a car like the ones in your wishlist (same make, or a similar price) gets a deal.') }}</li>
                <li><b>{{ __('Saved searches') }}</b> {{ __('Save up to :count searches, like “BMW up to 30.000 €”. New cars and deals that match are sent to you right away.', ['count' => \App\Models\SavedSearch::LIMIT]) }}</li>
            </ul>
            <p class="hint">{{ __('Every account, free too, is told when a car in its wishlist gets a deal.') }}</p>

            @if ($user?->hasBuyerPremium())
                <div class="premium-status">
                    <h3>&#9813; {{ __("You're a premium buyer") }}</h3>
                    <p>{{ __(':plan plan, active until :date.', ['plan' => __(ucfirst($user->buyer_premium_plan)), 'date' => $user->buyer_premium_until->format('Y-m-d')]) }}</p>
                    <a class="btn gold" href="{{ route('deals.index') }}">{{ __('See the deals') }}</a>
                    <a class="btn ghost" href="{{ route('account') }}#saved-searches">{{ __('Your saved searches') }}</a>
                    <a class="btn ghost" href="{{ route('account') }}#subscriptions">{{ __('Manage subscription') }}</a>
                </div>
            @else
                @include('premium._buy', ['kind' => 'buyer'])
            @endif
        </section>
    </div>
</main>

</x-layouts.app>
