{{-- The current weather from ARSO next to the menu button: Maribor's, or the visitor's nearest station (weather.js).
     Renders nothing without a recent reading. Behind the content sits a small sky (.weather-scene) in the colours of the
     weather and the time of day, with what is happening in it: rain and snow falling across, clouds drifting by,
     lightning, fog, stars at night, a sun glare. The icon is drawn with filled, shaded shapes. Motion: app.css (.wx-*). --}}
@props(['reading' => null])
@if ($reading)
    @php
        $kind = $reading['condition'];
        $night = (bool) $reading['night'];
        $label = \App\Services\Weather::label($kind);
        $temp = str_replace('-', '−', (string) $reading['temp']);
        $time = \Illuminate\Support\Carbon::parse($reading['observed_at'])->setTimezone('Europe/Ljubljana')->format('H:i');
        $place = $reading['place'] ?? \App\Services\Weather::HOME;
        $title = __(':place now: :condition, :temp °C. Measured at :time by ARSO.', ['place' => $place, 'condition' => $label, 'temp' => $temp, 'time' => $time]);

        $sky = in_array($kind, ['clear', 'mostly-clear', 'partly-cloudy'], true);          // the sun or the moon shows
        $falls = in_array($kind, ['rain', 'sleet', 'snow', 'storm', 'hail'], true);        // something comes out of the cloud
        $cloud = $kind !== 'clear' && $kind !== 'fog';
        $dark = in_array($kind, ['overcast', 'rain', 'sleet', 'snow', 'storm', 'hail'], true);   // a heavy grey cloud
        // the cloud sits low beside the sun, high above rain or snow, in the middle otherwise
        $cloudAt = $sky ? 'translate(5 6) scale(.82)' : ($falls ? 'translate(0 -4)' : 'translate(0 -1)');

        // particles across the whole widget, spread out evenly but not in a pattern the eye picks up
        $particles = fn (int $n, float $duration, float $spread) => collect(range(0, $n - 1))->map(fn ($i) => [
            'x' => (($i * 61 + 7) % 97) + 1.5,
            't' => round($duration + (($i * 7) % 5) * $spread, 2),
            'd' => -round(fmod($i * 0.37, $duration + 0.4), 2),
        ]);
        $scene = match ($kind) {
            'rain', 'storm' => ['drop' => $particles(12, .55, .07)],
            'sleet' => ['drop' => $particles(6, .6, .07), 'flake' => $particles(6, 2.4, .25)],
            'snow' => ['flake' => $particles(11, 2.6, .3)],
            'hail' => ['hail' => $particles(9, .7, .08)],
            default => [],
        };
    @endphp
    <a class="weather weather-{{ $kind }} {{ $night ? 'is-night' : 'is-day' }}" href="{{ \App\Services\Weather::page($place) }}"
       target="_blank" rel="noopener" title="{{ $title }}" aria-label="{{ $title }}">
        <span class="weather-scene" aria-hidden="true">
            @if ($night && $sky)
                @foreach ([[8, 22, 0], [21, 60, 1.3], [34, 30, 2.1], [52, 70, .6], [67, 18, 1.8], [79, 48, .9], [91, 28, 2.6], [45, 12, 1.1]] as [$x, $y, $d])
                    <i class="wx-star" style="--x: {{ $x }}%; --y: {{ $y }}%; --d: {{ $d }}s"></i>
                @endforeach
            @endif
            @if ($cloud || $kind === 'fog')
                <i class="wx-cloudlet"></i><i class="wx-cloudlet two"></i>
            @endif
            @if ($kind === 'clear' && ! $night)
                <i class="wx-shine"></i>
            @endif
            @if ($kind === 'fog')
                <i class="wx-mist"></i><i class="wx-mist two"></i>
            @endif
            @foreach ($scene as $type => $list)
                @foreach ($list as $p)
                    <i class="wx-{{ $type }}" style="--x: {{ $p['x'] }}%; --t: {{ $p['t'] }}s; --d: {{ $p['d'] }}s"></i>
                @endforeach
            @endforeach
            @if ($kind === 'storm')
                <i class="wx-flash"></i>
            @endif
        </span>

        <svg class="weather-icon" viewBox="0 0 32 32" width="34" height="34" aria-hidden="true">
            <defs>
                <radialGradient id="wx-sun-core" cx="40%" cy="38%" r="65%">
                    <stop offset="0" stop-color="#fffbe0"/><stop offset=".45" stop-color="#ffd84d"/><stop offset="1" stop-color="#ff9a1a"/>
                </radialGradient>
                <radialGradient id="wx-sun-glow"><stop offset="0" stop-color="#ffd84d" stop-opacity=".75"/><stop offset="1" stop-color="#ffb02e" stop-opacity="0"/></radialGradient>
                <radialGradient id="wx-moon-face" cx="35%" cy="35%" r="75%"><stop offset="0" stop-color="#fffdf2"/><stop offset="1" stop-color="#c9d3f2"/></radialGradient>
                <radialGradient id="wx-moon-glow"><stop offset="0" stop-color="#dfe8ff" stop-opacity=".55"/><stop offset="1" stop-color="#dfe8ff" stop-opacity="0"/></radialGradient>
                <mask id="wx-crescent"><rect width="32" height="32" fill="#fff"/><circle cx="19.5" cy="9" r="7.2" fill="#000"/></mask>
                <linearGradient id="wx-cloud-light" x1="0" y1="5" x2="0" y2="25" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#ffffff"/><stop offset=".6" stop-color="#eef3f9"/><stop offset="1" stop-color="#c3cfdd"/>
                </linearGradient>
                <linearGradient id="wx-cloud-dark" x1="0" y1="5" x2="0" y2="25" gradientUnits="userSpaceOnUse">
                    <stop offset="0" stop-color="#d5dce6"/><stop offset=".55" stop-color="#9aa6b6"/><stop offset="1" stop-color="#5f6b7c"/>
                </linearGradient>
                <linearGradient id="wx-drop" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#d6f0ff"/><stop offset="1" stop-color="#3d9bff"/></linearGradient>
                <linearGradient id="wx-bolt" x1="0" y1="0" x2="0" y2="1"><stop offset="0" stop-color="#fff6b0"/><stop offset="1" stop-color="#ffb300"/></linearGradient>
            </defs>

            @if ($sky)
                <g class="wx-body" @if ($cloud) transform="translate(-2 -2.5) scale(.78)" @endif>
                    @if ($night)
                        <circle class="wx-glow" cx="14" cy="15" r="13" fill="url(#wx-moon-glow)"/>
                        <g class="wx-moon">
                            <circle cx="14" cy="15" r="8.5" fill="url(#wx-moon-face)" mask="url(#wx-crescent)"/>
                        </g>
                    @else
                        <circle class="wx-glow" cx="16" cy="16" r="15" fill="url(#wx-sun-glow)"/>
                        <g class="wx-rays" stroke="#ffc43d" stroke-width="2.2" stroke-linecap="round">
                            @foreach (range(0, 315, 45) as $angle)
                                <line x1="16" y1="3.2" x2="16" y2="6.4" transform="rotate({{ $angle }} 16 16)"/>
                            @endforeach
                        </g>
                        <circle class="wx-core" cx="16" cy="16" r="7" fill="url(#wx-sun-core)"/>
                    @endif
                </g>
            @endif

            @if ($falls)
                <g class="wx-icon-fall">
                    @switch ($kind)
                        @case('snow')
                            @foreach ([[9, 25, 0], [16, 27.5, -.9], [23, 25, -1.7]] as [$x, $y, $d])
                                <g transform="translate({{ $x }} {{ $y }})">
                                    <g class="wx-icon-flake" style="animation-delay: {{ $d }}s" stroke="#fff" stroke-width="1.3" stroke-linecap="round">
                                        <path d="M0-2.4v4.8M-2.1-1.2l4.2 2.4M-2.1 1.2l4.2-2.4"/>
                                    </g>
                                </g>
                            @endforeach
                            @break
                        @case('hail')
                            @foreach ([[10, 25, 0], [16, 28, -.35], [22, 25, -.7]] as [$x, $y, $d])
                                <circle class="wx-icon-drop" style="animation-delay: {{ $d }}s" cx="{{ $x }}" cy="{{ $y }}" r="1.6" fill="#eef6ff" stroke="#9fc4ef" stroke-width=".6"/>
                            @endforeach
                            @break
                        @default
                            @foreach (match ($kind) { 'sleet' => [[10, 23], [22, 23]], 'storm' => [[8.5, 23], [24, 23]], default => [[10, 23], [16, 26], [22, 23]] } as $n => [$x, $y])
                                <path class="wx-icon-drop" style="animation-delay: {{ -0.35 * $n }}s" d="M{{ $x }} {{ $y }}c-1.2 1.8-1.8 2.9-1.8 3.7a1.8 1.8 0 0 0 3.6 0c0-.8-.6-1.9-1.8-3.7z" fill="url(#wx-drop)"/>
                            @endforeach
                            @if ($kind === 'sleet')
                                <g transform="translate(16 27)"><g class="wx-icon-flake" stroke="#fff" stroke-width="1.3" stroke-linecap="round"><path d="M0-2.4v4.8M-2.1-1.2l4.2 2.4M-2.1 1.2l4.2-2.4"/></g></g>
                            @endif
                    @endswitch
                </g>
            @endif

            @if ($cloud)
                <g transform="{{ $cloudAt }}">
                    <g class="wx-cloud" fill="url(#{{ $dark ? 'wx-cloud-dark' : 'wx-cloud-light' }})">
                        <circle cx="10.5" cy="17" r="5.5"/>
                        <circle cx="17.5" cy="13" r="7.5"/>
                        <circle cx="24" cy="17.5" r="5"/>
                        <rect x="5" y="16.5" width="24" height="7.5" rx="3.75"/>
                    </g>
                </g>
            @endif

            @if ($kind === 'storm')
                <path class="wx-bolt" d="M17.5 17 12 25h4.5l-2 6.5 7-9.5h-4.6l2.1-5z" fill="url(#wx-bolt)" stroke="#ff9f00" stroke-width=".6" stroke-linejoin="round"/>
            @endif

            @if ($kind === 'fog')
                <g class="wx-fog" stroke-linecap="round" stroke-width="2.6">
                    <line class="one" x1="5" y1="10" x2="23" y2="10" stroke="#f2f5f9"/>
                    <line class="two" x1="9" y1="15.5" x2="28" y2="15.5" stroke="#dde3ea"/>
                    <line class="one" x1="4" y1="21" x2="21" y2="21" stroke="#c9d1db"/>
                    <line class="two" x1="11" y1="26.5" x2="26" y2="26.5" stroke="#b4bec9"/>
                </g>
            @endif
        </svg>
        <span class="weather-temp">{{ $temp }}°</span>
        <span class="weather-text"><b>{{ $place }}</b><small>{{ $label }}</small></span>
    </a>
@endif
