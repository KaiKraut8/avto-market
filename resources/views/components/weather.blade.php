{{-- The current weather in Maribor from ARSO, next to the menu button. Renders nothing when there is no recent reading.
     Icons drawn after Feather (MIT); the parts that move are animated in app.css (.weather-*). --}}
@php
    $reading = app(\App\Services\Weather::class)->current();
@endphp
@if ($reading)
    @php
        $kind = $reading['condition'];
        $label = \App\Services\Weather::label($kind);
        $temp = str_replace('-', '−', (string) $reading['temp']);
        $time = \Illuminate\Support\Carbon::parse($reading['observed_at'])->setTimezone('Europe/Ljubljana')->format('H:i');
        $title = __('Maribor now: :condition, :temp °C. Measured at :time by ARSO.', ['condition' => $label, 'temp' => $temp, 'time' => $time]);
        $sun = in_array($kind, ['clear', 'mostly-clear', 'partly-cloudy'], true);   // a sun or moon in the picture
        $cloud = $kind !== 'clear' && $kind !== 'fog';
        $small = $sun && $cloud;                                                       // sun or moon peeking out behind a cloud
        $cloudPath = in_array($kind, ['rain', 'sleet', 'snow', 'storm', 'hail'], true)
            ? 'M20 16.58A5 5 0 0 0 18 7h-1.26A8 8 0 1 0 4 15.25'   // open at the bottom, for what falls out of it
            : 'M18 10h-1.26A8 8 0 1 0 9 20h9a5 5 0 0 0 0-10z';
    @endphp
    <a class="weather weather-{{ $kind }} {{ $reading['night'] ? 'is-night' : 'is-day' }}" href="{{ \App\Services\Weather::PAGE }}"
       target="_blank" rel="noopener" title="{{ $title }}" aria-label="{{ $title }}">
        <svg class="weather-icon" viewBox="0 0 24 24" width="30" height="30" aria-hidden="true" fill="none" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round">
            @if ($sun)
                <g class="weather-sky" @if ($small) transform="translate(-1.5 -2) scale(.62)" @endif>
                    @if ($reading['night'])
                        <path class="weather-moon" d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>
                    @else
                        <g class="weather-sun">
                            <circle cx="12" cy="12" r="5"/>
                            <path d="M12 1v2M12 21v2M4.22 4.22l1.42 1.42M18.36 18.36l1.42 1.42M1 12h2M21 12h2M4.22 19.78l1.42-1.42M18.36 5.64l1.42-1.42"/>
                        </g>
                    @endif
                </g>
            @endif
            @if ($cloud)
                <g class="weather-cloud" @if ($small) transform="translate(4 4.6) scale(.8)" @endif>
                    <path d="{{ $cloudPath }}"/>
                </g>
            @endif
            @switch ($kind)
                @case('rain')
                    <path class="weather-fall" d="M8 13v6M16 13v6M12 15v6"/>
                    @break
                @case('sleet')
                    <path class="weather-fall" d="M8 13v5M16 13v5M12 17h.01M8 22h.01M16 22h.01"/>
                    @break
                @case('snow')
                    <path class="weather-fall snow" d="M8 16h.01M8 20h.01M12 18h.01M12 22h.01M16 16h.01M16 20h.01"/>
                    @break
                @case('hail')
                    <g class="weather-fall hail"><circle cx="8" cy="17" r="1.1"/><circle cx="12" cy="20" r="1.1"/><circle cx="16" cy="17" r="1.1"/></g>
                    @break
                @case('storm')
                    <path class="weather-bolt" d="M13 11 9 17h6l-4 6"/>
                    @break
                @case('fog')
                    <path class="weather-fog" d="M3 8h14M6 12h15M3 16h12M8 20h10"/>
                    @break
            @endswitch
        </svg>
        <span class="weather-temp">{{ $temp }}°</span>
        <span class="weather-text"><b>Maribor</b><small>{{ $label }}</small></span>
    </a>
@endif
