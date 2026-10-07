@props(['car', 'size' => 'card'])
{{-- The car's price; with a running deal the old price crossed out, the deal price, and the member price
     (shown to premium buyers, a hint to everyone else) --}}
@php
    $deal = $car->activeDeal;
    $viewer = auth()->user();
    $member = $deal?->member_price !== null && $viewer?->hasBuyerPremium();
@endphp
@if ($deal)
    <span {{ $attributes->class(['deal-price', 'deal-price-'.$size]) }}>
        <s class="was">@price($deal->regular_price)</s>
        <span class="price now">@price($member ? $deal->member_price : $deal->deal_price)</span>
        <span class="deal-off">&minus;{{ $deal->percentOff($member ? (float) $deal->member_price : null) }}%</span>
        @if ($member)
            <span class="member-note">&#9813; {{ __('Your member price') }}</span>
        @elseif ($deal->member_price !== null)
            <a class="member-note locked" href="{{ route('premium.index') }}#buyers">&#9813; {{ __('Premium buyers pay :price', ['price' => \App\Support\Money::price($deal->member_price)]) }}</a>
        @endif
    </span>
@else
    <span {{ $attributes->class(['price', 'muted' => $car->price === null]) }}>@price($car->price)</span>
@endif
