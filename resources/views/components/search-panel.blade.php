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
        <input type="search" name="q" value="{{ $search->q }}" placeholder="{{ $placeholder ?? __('Search by make, model, part or location, e.g. BMW, Volan, Ljubljana') }}" aria-label="{{ __('Search cars') }}" maxlength="80">
        @if (! $search->isEmpty())
            <a class="search-clear" href="{{ route('cars.index') }}" aria-label="{{ __('Clear search') }}">&times;</a>
        @endif
        <button type="submit" class="btn accent">{{ __('Search') }}</button>
    </div>
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
        @unless ($hero)
            <button type="submit" class="btn ghost filter-apply">{{ __('Apply') }}</button>
        @endunless
    </div>
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
