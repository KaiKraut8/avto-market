{{-- Search by text, year and price. The same form on the home page (hero) and above the car list. --}}
@props(['search' => null, 'bounds', 'placeholder' => null, 'hero' => false])
@php
    use App\Support\CarSearch;
    use App\Support\Money;
    $search ??= new CarSearch;
    $years = range($bounds['yearMax'], $bounds['yearMin']);
    $priceHint = fn (?int $v, string $fallback) => $v !== null ? number_format($v, 0, ',', '.') : $fallback;
@endphp
<form {{ $attributes->merge(['class' => 'search-panel'.($hero ? ' hero-search' : '')]) }} action="{{ route('cars.index') }}" method="get" role="search">
    <div class="search-bar">
        <x-icon name="search" :size="20" />
        <input type="search" name="q" value="{{ $search->q }}" placeholder="{{ $placeholder ?? __('Search by make, model or location, e.g. BMW, Golf, Ljubljana') }}" aria-label="{{ __('Search cars') }}" maxlength="80">
        @if (! $search->isEmpty())
            <a class="search-clear" href="{{ route('cars.index') }}" aria-label="{{ __('Clear search') }}">&times;</a>
        @endif
        <button type="submit" class="btn accent">{{ __('Search') }}</button>
    </div>
    @if ($hero)
    <div class="search-filters">
        <div class="filter-group">
            <span class="filter-label">{{ __('Year') }}</span>
            <label class="sr-only" for="year_from{{ $hero ? '-hero' : '' }}">{{ __('Year from') }}</label>
            <select name="year_from" id="year_from{{ $hero ? '-hero' : '' }}">
                <option value="">{{ __('from') }}</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($search->yearFrom === $y)>{{ $y }}</option>
                @endforeach
            </select>
            <span class="filter-dash" aria-hidden="true">–</span>
            <label class="sr-only" for="year_to{{ $hero ? '-hero' : '' }}">{{ __('Year to') }}</label>
            <select name="year_to" id="year_to{{ $hero ? '-hero' : '' }}">
                <option value="">{{ __('to') }}</option>
                @foreach ($years as $y)
                    <option value="{{ $y }}" @selected($search->yearTo === $y)>{{ $y }}</option>
                @endforeach
            </select>
        </div>
        <div class="filter-group">
            <span class="filter-label">{{ __('Price') }}</span>
            <label class="sr-only" for="price_from{{ $hero ? '-hero' : '' }}">{{ __('Price from') }}</label>
            <span class="filter-money"><input type="text" inputmode="numeric" name="price_from" id="price_from{{ $hero ? '-hero' : '' }}" placeholder="{{ $priceHint($bounds['priceMin'], __('from')) }}" value="{{ $search->priceFrom !== null ? number_format($search->priceFrom, 0, ',', '.') : '' }}" maxlength="12"><i>€</i></span>
            <span class="filter-dash" aria-hidden="true">–</span>
            <label class="sr-only" for="price_to{{ $hero ? '-hero' : '' }}">{{ __('Price to') }}</label>
            <span class="filter-money"><input type="text" inputmode="numeric" name="price_to" id="price_to{{ $hero ? '-hero' : '' }}" placeholder="{{ $priceHint($bounds['priceMax'], __('to')) }}" value="{{ $search->priceTo !== null ? number_format($search->priceTo, 0, ',', '.') : '' }}" maxlength="12"><i>€</i></span>
        </div>
    </div>
    @else
        {{-- the car list: every filter and the order, in a panel that opens by itself when something is set --}}
        @php($active = $search->activeCount())
        <details class="filters" @if ($active) open @endif>
            <summary>
                <span class="filters-toggle"><x-icon name="search" :size="16" /> {{ __('Filters') }}</span>
                @if ($active)<span class="filters-count">{{ $active }}</span>@endif
                <span class="filters-chevron" aria-hidden="true">&#9662;</span>
            </summary>
            <div class="filters-grid">
                <label class="filter-field">
                    <span class="filter-label">{{ __('Make') }}</span>
                    <select name="make">
                        <option value="">{{ __('All makes') }}</option>
                        @foreach ($bounds['makes'] as $key => $label)
                            <option value="{{ $key }}" @selected($search->make === $key)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <div class="filter-field">
                    <span class="filter-label">{{ __('Year') }}</span>
                    <div class="filter-range">
                        <select name="year_from" aria-label="{{ __('Year from') }}">
                            <option value="">{{ __('from') }}</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($search->yearFrom === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                        <span class="filter-dash" aria-hidden="true">–</span>
                        <select name="year_to" aria-label="{{ __('Year to') }}">
                            <option value="">{{ __('to') }}</option>
                            @foreach ($years as $y)
                                <option value="{{ $y }}" @selected($search->yearTo === $y)>{{ $y }}</option>
                            @endforeach
                        </select>
                    </div>
                </div>
                <div class="filter-field">
                    <span class="filter-label">{{ __('Price') }}</span>
                    <div class="filter-range">
                        <span class="filter-money"><input type="text" inputmode="numeric" name="price_from" aria-label="{{ __('Price from') }}" placeholder="{{ $priceHint($bounds['priceMin'], __('from')) }}" value="{{ $search->priceFrom !== null ? number_format($search->priceFrom, 0, ',', '.') : '' }}" maxlength="12"><i>€</i></span>
                        <span class="filter-dash" aria-hidden="true">–</span>
                        <span class="filter-money"><input type="text" inputmode="numeric" name="price_to" aria-label="{{ __('Price to') }}" placeholder="{{ $priceHint($bounds['priceMax'], __('to')) }}" value="{{ $search->priceTo !== null ? number_format($search->priceTo, 0, ',', '.') : '' }}" maxlength="12"><i>€</i></span>
                    </div>
                </div>
                <label class="filter-field">
                    <span class="filter-label">{{ __('Country') }}</span>
                    <select name="country">
                        <option value="">{{ __('All countries') }}</option>
                        @foreach ($bounds['countries'] as $c)
                            <option value="{{ $c }}" @selected($search->country === $c)>{{ __($c) }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="filter-field">
                    <span class="filter-label">{{ __('Sort by') }}</span>
                    <select name="sort">
                        @foreach ([
                            'recommended' => __('Recommended'),
                            'price_asc' => __('Price: low to high'),
                            'price_desc' => __('Price: high to low'),
                            'year_desc' => __('Year: newest first'),
                            'year_asc' => __('Year: oldest first'),
                            'newest' => __('Newly listed'),
                            'popular' => __('Most watched'),
                        ] as $value => $label)
                            <option value="{{ $value }}" @selected($search->sort === $value)>{{ $label }}</option>
                        @endforeach
                    </select>
                </label>
                <label class="filter-check">
                    <input type="checkbox" name="deals" value="1" @checked($search->dealsOnly)>
                    <span><b>{{ __('Price dropped') }}</b><small>{{ __('Only cars on a special deal') }}</small></span>
                </label>
            </div>
            <div class="filters-actions">
                <button type="submit" class="btn accent">{{ __('Show cars') }}</button>
                @if ($active)
                    <a class="btn ghost" href="{{ route('cars.index', $search->q !== '' ? ['q' => $search->q] : []) }}">{{ __('Clear filters') }}</a>
                @endif
            </div>
        </details>
    @endif
    @if ($hero)
        {{-- one-click searches --}}
        <div class="quick-searches">
            <a href="{{ route('cars.index', ['price_to' => 15000]) }}">{{ __('Under :price', ['price' => Money::price(15000)]) }}</a>
            <a href="{{ route('cars.index', ['price_from' => 15000, 'price_to' => 30000]) }}">{{ Money::price(15000) }} – {{ Money::price(30000) }}</a>
            <a href="{{ route('cars.index', ['price_from' => 30000]) }}">{{ __('Over :price', ['price' => Money::price(30000)]) }}</a>
            <a href="{{ route('cars.index', ['year_from' => date('Y') - 5]) }}">{{ __('Newer than :year', ['year' => date('Y') - 5]) }}</a>
        </div>
    @endif
</form>
