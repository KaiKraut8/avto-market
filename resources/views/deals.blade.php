<x-layouts.app :title="__('Special deals')" active="deals">

<main class="wrap">
    <div class="page-title">
        <h1><x-icon name="tag" :size="26" /> {{ __('Special deals') }}</h1>
        @if ($deals->isNotEmpty())
            <span class="count">{{ trans_choice(':count deal running|:count deals running', $deals->count()) }}</span>
        @endif
    </div>
    <p class="lede deals-lede">{{ __('Price cuts from premium sellers, for a few days only. Premium buyers see an even lower member price on many of them.') }}</p>

    @auth
        @unless (auth()->user()->hasBuyerPremium())
            <a class="deals-member-bar" href="{{ route('premium.index') }}#buyers">
                <span>&#9813; <b>{{ __('Premium buyers pay less.') }}</b> {{ __('Member prices, alerts for deals on cars like yours, and saved searches.') }}</span>
                <span class="go">{{ __('From :price a month', ['price' => \App\Support\Money::eur(\App\Services\Pricing::buyerMonthly())]) }} &rarr;</span>
            </a>
        @endunless
    @else
        <a class="deals-member-bar" href="{{ route('premium.index') }}#buyers">
            <span>&#9813; <b>{{ __('Premium buyers pay less.') }}</b> {{ __('Member prices, alerts for deals on cars like yours, and saved searches.') }}</span>
            <span class="go">{{ __('From :price a month', ['price' => \App\Support\Money::eur(\App\Services\Pricing::buyerMonthly())]) }} &rarr;</span>
        </a>
    @endauth

    @if ($deals->isEmpty())
        <div class="empty">
            {{ __('No deals are running right now. Save cars to your wishlist and we will show you when one of them, or a similar car, gets a deal.') }}
            <div style="margin-top:1rem"><a class="btn accent" href="{{ route('cars.index') }}">{{ __('Browse all cars') }}</a></div>
        </div>
    @else
        @if ($forYou)
            <div class="group-label for-you-label"><x-icon name="heart" :size="15" /> {{ __('For you: like the cars in your wishlist') }}</div>
            <div class="results">
                @foreach ($deals->where('for_you', true) as $i => $car)
                    <x-car-card :car="$car" :i="$i" :wished="isset($wished[$car->id])" />
                @endforeach
            </div>
        @endif
        @if ($deals->where('for_you', false)->isNotEmpty())
            @if ($forYou)
                <div class="group-label">{{ __('More deals') }}</div>
            @endif
            <div class="results">
                @foreach ($deals->where('for_you', false) as $i => $car)
                    <x-car-card :car="$car" :i="$i" :wished="isset($wished[$car->id])" />
                @endforeach
            </div>
        @endif
    @endif
</main>

</x-layouts.app>
