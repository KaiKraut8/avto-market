@props(['car', 'lazy' => true])
{{-- the car's first photo, or its initial on a gradient when there is none --}}
@if ($car->coverPhoto)
    <img src="{{ $car->coverPhoto->url() }}" alt="{{ $car->name }}" @if ($lazy) loading="lazy" @endif {{ $attributes }}>
@else
    <span class="initial">{{ $car->initial() }}</span>
@endif
