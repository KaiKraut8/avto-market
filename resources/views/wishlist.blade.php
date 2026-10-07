<x-layouts.app :title="__('Wishlist')" active="wishlist">

<main class="wrap">
    <div class="page-title">
        <h1>{{ __('Wishlist') }}</h1>
        @if ($cars->isNotEmpty())
            <span class="count">{{ trans_choice(':count car|:count cars', $cars->count()) }} &middot; {{ __('together :price', ['price' => \App\Support\Money::price($sum)]) }}</span>
        @endif
    </div>

    <div class="empty" id="wish-empty" @if ($cars->isNotEmpty()) hidden @endif>
        <svg class="empty-heart" viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.6-9.3C.9 7.8 3 4 6.8 4c2.1 0 3.6 1.1 4.4 2.5h1.6C13.6 5.1 15.1 4 17.2 4 21 4 23.1 7.8 21.6 11.2c-2.1 4.7-9.6 9.3-9.6 9.3Z"/></svg>
        {{ __('Your wishlist is empty. Tap the heart on any car to save it here.') }}
        <div style="margin-top:1rem"><a class="btn accent" href="{{ route('cars.index') }}">{{ __('Browse all cars') }}</a></div>
    </div>

    <div class="results">
        @foreach ($cars as $i => $car)
            <x-car-card :car="$car" :i="$i" :wished="true" :wishlist="true" />
        @endforeach
    </div>
</main>

</x-layouts.app>
