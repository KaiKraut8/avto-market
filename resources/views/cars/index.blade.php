@php
    $quoted = '“'.$q.'”';
    // "Results for “bmw”", "Cars 2018–2022, up to 20.000 €" or both
    $what = $q !== '' ? $quoted : '';
    if ($search->hasFilters()) {
        $what = trim($what.' '.implode(', ', $search->labels()));
    }
@endphp
<x-layouts.app :title="$what !== '' ? __('Search: :q', ['q' => $what]) : __('All cars')" active="cars">

<main class="wrap">
    <div class="page-title">
        <h1>{{ $what !== '' && ! $noMatch ? ($q !== '' ? __('Results for :q', ['q' => $what]) : __('Cars :filters', ['filters' => $what])) : __('All cars') }}</h1>
        @unless ($noMatch)
            <span class="count">{{ trans_choice(':count result|:count results', $cars->count()) }}</span>
        @endunless
    </div>

    <x-search-panel :search="$search" :bounds="$bounds" />

    @if ($noMatch)
        <div class="empty search-empty">{{ __('No cars match :q. Here are other options you might like.', ['q' => $what]) }}</div>
    @endif

    @if ($cars->isNotEmpty())
        @php($n = 0)
        @if ($premium->isNotEmpty())
            <div class="group-label premium-label"><span class="crown" aria-hidden="true">&#9813;</span> {{ __('Premium listings') }}</div>
            <div class="results premium-results">
                @foreach ($premium as $car)
                    <x-car-card :car="$car" :i="$n++" :wished="isset($wished[$car->id])" />
                @endforeach
            </div>
        @endif
        @if ($regular->isNotEmpty())
            @if ($premium->isNotEmpty() || $q !== '')
                <div class="group-label">
                    {{ $noMatch ? __('Other options you might like') : ($what !== '' ? __('Other options related to :q', ['q' => $what]) : __('Other options')) }}
                </div>
            @endif
            <div class="results regular-results">
                @foreach ($regular as $car)
                    <x-car-card :car="$car" :i="$n++" :wished="isset($wished[$car->id])" />
                @endforeach
            </div>
        @endif
    @else
        <div class="empty">{{ __('No cars yet.') }} <a href="{{ route('cars.create') }}">{{ __('Add the first one') }}</a>.</div>
    @endif
</main>

</x-layouts.app>
