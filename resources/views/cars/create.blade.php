<x-layouts.app :title="__('New car')" active="sell" :back-to-cars="true">

<main class="wrap">
    <p class="crumbs"><a href="{{ route('home') }}">{{ __('Home') }}</a> &rsaquo; <a href="{{ route('cars.index') }}">{{ __('All cars') }}</a> &rsaquo; {{ __('New car') }}</p>
    <div class="detail-head">
        <h1>{{ __('New car') }}</h1>
    </div>

    <div class="detail new-car">
        <div></div>
        <aside>
            <div class="panel">
                <h2>{{ __('Sell your car') }}</h2>
                @include('cars._form')
            </div>
        </aside>
    </div>
</main>

</x-layouts.app>
