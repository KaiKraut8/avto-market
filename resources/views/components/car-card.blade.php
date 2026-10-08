@props(['car', 'i' => 0, 'wished' => false, 'wishlist' => false])
@php
    $link = route('cars.show', $car);
@endphp
<article @class(['car-card', 'has-deal' => $car->activeDeal, 'premium' => $car->isPremium(), 'boosted' => ! $car->isPremium() && $car->isBoosted(), 'wish-card' => $wishlist])
         style="--i: {{ $i }}" data-href="{{ $link }}" @if ($wishlist) data-remove-on-unwish @endif>
    @if ($car->isSold())
        <span class="sale-ribbon sold">{{ __('Sold') }}</span>
    @elseif ($car->isReserved())
        <span class="sale-ribbon">{{ __('Reserved') }}</span>
    @elseif ($car->activeDeal)
        <span class="deal-ribbon"><x-icon name="tag" :size="13" /> {{ __('Special deal') }}</span>
    @elseif ($car->isPremium())
        <span class="premium-ribbon"><span aria-hidden="true">&#9813;</span> {{ __('Premium') }}</span>
    @elseif ($car->isBoosted())
        <span class="boost-ribbon"><span aria-hidden="true">&#8679;</span> {{ __('Pushed') }}</span>
    @endif
    <a class="thumb" href="{{ $link }}">
        @if ($car->coverPhoto)
            <img src="{{ $car->coverPhoto->url() }}" alt="" loading="lazy">
        @else
            {{ $car->initial() }}
        @endif
    </a>
    <div class="car-body">
        @unless ($wishlist)
            {{-- the amber line that rises from the photo into the price column --}}
            <svg class="card-curve" viewBox="0 0 100 100" preserveAspectRatio="none" aria-hidden="true">
                <defs>
                    <linearGradient id="curve-stroke-{{ $i }}" x1="0" x2="1">
                        <stop offset="0" stop-color="#ffc94d" stop-opacity=".55"/>
                        <stop offset=".5" stop-color="#ffb02e"/>
                        <stop offset="1" stop-color="#ff7a1a"/>
                    </linearGradient>
                    <linearGradient id="curve-fill-{{ $i }}" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0" stop-color="#ffa62b" stop-opacity=".16"/>
                        <stop offset="1" stop-color="#ff7a1a" stop-opacity=".02"/>
                    </linearGradient>
                </defs>
                <path class="curve-area" d="M0,98 C45,98 80,85 99.5,0 L100,100 L0,100 Z" fill="url(#curve-fill-{{ $i }})"/>
                <path class="curve-line" d="M0,98 C45,98 80,85 99.5,0" fill="none" stroke="url(#curve-stroke-{{ $i }})" stroke-width="3" vector-effect="non-scaling-stroke"/>
            </svg>
        @endunless
        <h2><a href="{{ $link }}">{{ $car->name }}</a></h2>
        <div class="meta">
            @if ($car->year)
                <span class="year">{{ $car->year }}</span> &middot;
            @endif
            @if ($car->locationLabel())
                <span class="loc"><x-icon name="pin" :size="13" />{{ $car->locationLabel() }}</span> &middot;
            @endif
            @if ($wishlist && $car->wished_at)
                {{ __('Saved :date', ['date' => \Illuminate\Support\Carbon::parse($car->wished_at)->format('Y-m-d H:i')]) }}
            @else
                {{ __('Added :date', ['date' => $car->created_at?->format('Y-m-d')]) }}
            @endif
        </div>
        @if ($car->description)
            <p class="card-desc">{{ $car->description }}</p>
        @else
            <p class="card-desc empty-desc">{{ __('No description yet.') }}</p>
        @endif
        @unless ($wishlist)
            <button type="button" class="views-toggle" data-url="{{ route('cars.view-stats', $car) }}" aria-expanded="false">
                <x-icon name="eye" />
                {!! trans_choice('<b>:count</b> person looked at this car|<b>:count</b> people looked at this car', (int) $car->people, ['count' => (int) $car->people]) !!}
            </button>
            <div class="views-pop" hidden></div>
        @endunless
    </div>
    <div class="car-side">
        <x-heart :car="$car" :wished="$wished" />
        <x-deal-price :car="$car" />
        @if ($car->activeDeal)
            <span class="deal-ends">{{ __('Deal ends :date', ['date' => $car->activeDeal->ends_at->format('Y-m-d')]) }}</span>
        @endif
        <span class="id-tag">#{{ $car->id }}</span>
        <a class="btn details" href="{{ $link }}">{{ __('Details') }}</a>
    </div>
</article>
