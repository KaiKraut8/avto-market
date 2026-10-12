<?php

namespace App\Services;

use Closure;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The current weather, as measured by ARSO (the Slovenian Environment Agency), for the widget next to the menu button.
 *
 * Everyone first sees Maribor, where the garage is. A visitor who lets the browser share their location sees the
 * nearest ARSO station instead, as long as one is within NEAR_KM: ARSO covers Slovenia and the regions around it.
 * The location arrives rounded to about 1 km (2 decimals) and is used only to ask ARSO; the weather for that spot
 * is cached for a few hours, with nothing that links it to a visitor.
 *
 * Every reading is fetched again once it is REFRESH_MINUTES old: by the scheduler for Maribor (weather:refresh),
 * otherwise by the first page that needs it. If ARSO cannot be reached, the last reading stays until it is
 * SHOW_FOR_HOURS old, and pages pause PAUSE_MINUTES before trying again.
 */
class Weather
{
    /** The JSON the page vreme.arso.gov.si/napoved/Maribor/graf is built from */
    public const API = 'https://vreme.arso.gov.si/api/1.0/location/';

    /** Finds the ARSO station nearest to a point and returns its latest measurement */
    public const API_NEAR = 'https://vreme.arso.gov.si/api/1.0/locations/';

    public const HOME = 'Maribor';
    public const REFRESH_MINUTES = 15;
    public const NEAR_KM = 25;

    private const PAUSE_MINUTES = 5;
    private const SHOW_FOR_HOURS = 3;

    /** Maribor's weather; null when there is no recent reading */
    public function current(): ?array
    {
        return $this->cached('weather:maribor', fn () => $this->fetchHome(), forever: true);
    }

    /**
     * The weather at the ARSO station nearest to a point, or Maribor's when no station is close enough.
     * The point is rounded to 2 decimals (about 1 km) before anything is done with it.
     */
    public function near(float $lat, float $lon): ?array
    {
        $lat = round($lat, 2);
        $lon = round($lon, 2);
        if ($lat < -90 || $lat > 90 || $lon < -180 || $lon > 180) {
            return $this->current();
        }

        $reading = $this->cached(sprintf('weather:near:%.2f,%.2f', $lat, $lon), fn () => $this->fetchNear($lat, $lon), forever: false);

        return $reading && empty($reading['far']) ? $reading : $this->current();
    }

    /** Fetches Maribor's weather now and stores it (the scheduler's job); null when ARSO can't be read */
    public function refresh(): ?array
    {
        return $this->store('weather:maribor', $this->fetchHome(), forever: true);
    }

    /** The page on ARSO's site with the full forecast for a place */
    public static function page(string $place): string
    {
        return 'https://vreme.arso.gov.si/napoved/'.rawurlencode($place).'/graf';
    }

    /**
     * Reads ARSO's measurements (the "days" of an observation) into what the widget needs.
     *
     * The icon code reads sky_weather_daytime, the weather part being optional:
     * "prevCloudy_day", "prevCloudy_lightRA_day", "clear_night", "FG_day".
     */
    public static function parse(mixed $days, string $place): ?array
    {
        if (! is_array($days) || trim($place) === '') {
            return null;
        }

        $latest = null;
        foreach ($days as $day) {
            foreach ((array) ($day['timeline'] ?? []) as $entry) {
                if (is_array($entry) && is_string($entry['valid'] ?? null) && (! $latest || strcmp($entry['valid'], $latest['valid']) > 0)) {
                    $latest = $entry;
                }
            }
        }

        if (! $latest || ! is_numeric($latest['t'] ?? null)) {
            return null;
        }

        try {
            $observed = Carbon::parse($latest['valid']);
        } catch (Throwable) {
            return null;
        }

        $icon = (string) ($latest['clouds_icon_wwsyn_icon'] ?? '');
        $parts = explode('_', $icon);
        $sky = $parts[0] ?? '';
        $weather = count($parts) > 2 ? $parts[1] : '';

        return [
            'place' => trim($place),
            'temp' => (int) round((float) $latest['t']),
            'condition' => self::condition($sky, $weather),
            'night' => end($parts) === 'night',
            'observed_at' => $observed->toIso8601String(),
        ];
    }

    /** The condition in words, in the current language */
    public static function label(string $condition): string
    {
        return match ($condition) {
            'clear' => __('Clear sky'),
            'mostly-clear' => __('Mostly clear'),
            'partly-cloudy' => __('Partly cloudy'),
            'mostly-cloudy' => __('Mostly cloudy'),
            'fog' => __('Fog'),
            'rain' => __('Rain'),
            'sleet' => __('Sleet'),
            'snow' => __('Snow'),
            'storm' => __('Thunderstorm'),
            'hail' => __('Hail'),
            default => __('Overcast'),
        };
    }

    /** One of: clear, mostly-clear, partly-cloudy, mostly-cloudy, overcast, fog, rain, sleet, snow, storm, hail */
    private static function condition(string $sky, string $weather): string
    {
        return match (true) {
            str_contains($weather, 'TS') => 'storm',
            str_contains($weather, 'GR') || str_contains($weather, 'GS') => 'hail',
            str_contains($weather, 'RASN') || str_contains($weather, 'SNRA') => 'sleet',
            str_contains($weather, 'SN') => 'snow',
            str_contains($weather, 'RA') || str_contains($weather, 'DZ') => 'rain',
            str_contains($weather, 'FG') || $sky === 'FG' => 'fog',
            $sky === 'clear' => 'clear',
            $sky === 'mostClear' => 'mostly-clear',
            in_array($sky, ['slCloudy', 'partCloudy', 'modCloudy'], true) => 'partly-cloudy',
            $sky === 'prevCloudy' => 'mostly-cloudy',
            default => 'overcast',
        };
    }

    private function fetchHome(): ?array
    {
        $json = $this->get(self::API, ['location' => self::HOME]);

        return $json ? self::parse(data_get($json, 'observation.features.0.properties.days'), self::HOME) : null;
    }

    /** The nearest station's reading, or ['far' => true] when the nearest one is too far away to stand for the spot */
    private function fetchNear(float $lat, float $lon): ?array
    {
        $json = $this->get(self::API_NEAR, ['lat' => $lat, 'lon' => $lon]);
        $at = data_get($json, 'geometry.coordinates');
        if (! $json || ! is_array($at) || ! is_numeric($at[0] ?? null) || ! is_numeric($at[1] ?? null)) {
            return null;
        }

        if (self::km($lat, $lon, (float) $at[1], (float) $at[0]) > self::NEAR_KM) {
            return ['far' => true, 'observed_at' => now()->toIso8601String()];
        }

        return self::parse(data_get($json, 'properties.days'), (string) data_get($json, 'properties.title', ''));
    }

    private function get(string $url, array $query): ?array
    {
        try {
            $response = Http::timeout(4)->connectTimeout(3)->acceptJson()->get($url, $query);

            return $response->successful() && is_array($response->json()) ? $response->json() : null;
        } catch (Throwable) {
            return null;
        }
    }

    /** The stored reading, fetched again first when it is due; null when there is none recent enough to show */
    private function cached(string $key, Closure $fetch, bool $forever): ?array
    {
        $reading = Cache::get($key);
        $due = ! $reading || Carbon::parse($reading['fetched_at'] ?? '2000-01-01')->lte(now()->subMinutes(self::REFRESH_MINUTES));

        if ($due && ! Cache::has($key.':pause')) {
            $reading = $this->store($key, $fetch(), $forever) ?? $reading;
        }

        if (! $reading || Carbon::parse($reading['observed_at'])->lt(now()->subHours(self::SHOW_FOR_HOURS))) {
            return null;
        }

        return $reading;
    }

    /** Keeps a new reading, or after a failure leaves the old one and pauses further tries for a few minutes */
    private function store(string $key, ?array $reading, bool $forever): ?array
    {
        if (! $reading) {
            Cache::put($key.':pause', true, now()->addMinutes(self::PAUSE_MINUTES));
            Log::warning("Weather: could not read the weather from ARSO ({$key}).");

            return null;
        }

        $reading['fetched_at'] = now()->toIso8601String();
        $forever
            ? Cache::forever($key, $reading)
            : Cache::put($key, $reading, now()->addHours(self::SHOW_FOR_HOURS));
        Cache::forget($key.':pause');

        return $reading;
    }

    /** Distance between two points in kilometres (haversine) */
    private static function km(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;

        return 6371 * 2 * asin(min(1, sqrt($a)));
    }
}
