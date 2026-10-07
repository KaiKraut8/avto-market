@props(['car', 'wished' => false, 'label' => false])
@php($tip = $wished ? __('Remove from wishlist') : __('Add to wishlist'))
<button type="button" @class(['heart', 'with-label' => $label]) data-car="{{ $car->id }}" data-url="{{ route('wishlist.toggle', $car) }}"
        aria-pressed="{{ $wished ? 'true' : 'false' }}" aria-label="{{ $tip }}" title="{{ $tip }}">
    <svg viewBox="0 0 24 24" aria-hidden="true"><path d="M12 20.5s-7.5-4.6-9.6-9.3C.9 7.8 3 4 6.8 4c2.1 0 3.6 1.1 4.4 2.5h1.6C13.6 5.1 15.1 4 17.2 4 21 4 23.1 7.8 21.6 11.2c-2.1 4.7-9.6 9.3-9.6 9.3Z"/></svg>
    @if ($label)
        <span class="heart-label">{{ $wished ? __('In your wishlist') : __('Add to wishlist') }}</span>
    @endif
</button>
