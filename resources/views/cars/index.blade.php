@php($quoted = '“'.$q.'”')
<x-layouts.app :title="$q !== '' ? __('Search: :q', ['q' => $q]) : __('All cars')" active="cars">

<main class="wrap">
    <div class="page-title">
        <h1>{{ $q !== '' && ! $noMatch ? __('Results for :q', ['q' => $quoted]) : __('All cars') }}</h1>
        @unless ($noMatch)
            <span class="count">{{ trans_choice(':count result|:count results', $cars->count()) }}</span>
        @endunless
    </div>

    <x-search-bar :q="$q" :clear="true" />

    @if ($noMatch)
        <div class="empty search-empty">{{ __('No cars match :q. Here are other options you might like.', ['q' => $quoted]) }}</div>
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
                    {{ $noMatch ? __('Other options you might like') : ($q !== '' ? __('Other options related to :q', ['q' => $quoted]) : __('Other options')) }}
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
