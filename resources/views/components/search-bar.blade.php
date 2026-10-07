@props(['q' => '', 'placeholder' => null, 'clear' => false])
<form {{ $attributes->merge(['class' => 'search-bar']) }} action="{{ route('cars.index') }}" method="get" role="search">
    <x-icon name="search" :size="20" />
    <input type="search" name="q" value="{{ $q }}" placeholder="{{ $placeholder ?? __('Search by make, model, part or location, e.g. BMW, Volan, Ljubljana') }}" aria-label="{{ __('Search cars') }}" maxlength="80">
    @if ($clear && $q !== '')
        <a class="search-clear" href="{{ route('cars.index') }}" aria-label="{{ __('Clear search') }}">&times;</a>
    @endif
    <button type="submit" class="btn accent">{{ __('Search') }}</button>
</form>
