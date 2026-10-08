<x-layouts.app :title="__('See the interest live')" active="why">

<main class="wrap why-page">
    <div class="why-hero">
        <x-icon name="eye" :size="56" class="why-hero-icon" />
        <div>
            <p class="eyebrow">{{ __('Why buy here') }}</p>
            <h1>{{ __('See the interest') }} <span>{{ __('live.') }}</span></h1>
            <p class="lede">{{ __("Every car shows how many people looked at it and who's viewing it right now. Good offers go fast, and now you can see it happen.") }}</p>
        </div>
    </div>

    <div class="stats why-stats">
        <div class="stat"><b>{{ $people }}</b><span>{{ __('people looked at our cars') }}</span></div>
        <div class="stat"><b>{{ $views }}</b><span>{{ __('total views') }}</span></div>
        <div class="stat"><b><span class="live-dot @unless ($watching) idle @endunless"></span>{{ $watching }}</b><span>{{ __('watching right now') }}</span></div>
    </div>

    <div class="why-grid">
        <section class="panel">
            <h2>{{ __('What the numbers mean') }}</h2>
            <ul class="why-list">
                <li><b>{{ __('People looked:') }}</b> {{ __('each browser is counted once per car, however often it comes back.') }}</li>
                <li><b>{{ __('Views:') }}</b> {{ __('every visit to the car\'s page.') }}</li>
                <li><b>{{ __('Watching now:') }}</b> {{ __('everyone who has the car\'s page open at this moment.') }}</li>
            </ul>
            <p class="hint">{{ __('No names or personal data are collected for this, only an anonymous browser id.') }}</p>
        </section>

        @if ($top && $top->people > 0)
            <a class="panel why-top" href="{{ route('cars.show', $top) }}">
                <h2>{{ __('Most watched right now') }}</h2>
                <div class="why-top-car">
                    @if ($top->coverPhoto)
                        <img src="{{ $top->coverPhoto->url() }}" alt="" loading="lazy">
                    @endif
                    <div>
                        <b>{{ $top->name }}</b>
                        <span>{{ trans_choice(':count person looked at it|:count people looked at it', (int) $top->people) }}</span>
                    </div>
                </div>
            </a>
        @endif
    </div>

    <div class="why-cta">
        <a class="btn accent big" href="{{ route('most-watched') }}">{{ __('See the ranking') }} &rarr;</a>
        <a class="btn ghost big" href="{{ route('how-buying') }}">{{ __('Next: how buying works') }}</a>
    </div>
</main>

</x-layouts.app>
