<?php

namespace App\Services;

use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * The current weather in Maribor, as measured by ARSO (the Slovenian Environment Agency).
 *
 * The scheduler refreshes it every 15 minutes (weather:refresh in routes/console.php). If the scheduler is not
 * running, the first page shown after a reading turns 15 minutes old fetches a new one, so it stays current either way.
 * The last good reading is kept: if ARSO cannot be reached, the old one is shown until it is 3 hours old, then nothing.
 */
class Weather
{
    /** The JSON the page vreme.arso.gov.si/napoved/Maribor/graf is built from */
    public const API = 'https://vreme.arso.gov.si/api/1.0/location/?location=Maribor';

    /** Where the widget links to: the full forecast on ARSO's site */
    public const PAGE = 'https://vreme.arso.gov.si/napoved/Maribor/graf';

    public const REFRESH_MINUTES = 15;

    private const KEY = 'weather:maribor';
    private const PAUSE = 'weather:maribor:pause';      // set after a failed fetch, so pages don't all retry at once
    private const PAUSE_MINUTES = 5;
    private const SHOW_FOR_HOURS = 3;                    // an older measurement is not "the current weather" any more

    /** The reading to show, refreshed first if it is due; null when there is nothing recent enough */
    public function current(): ?array
    {
        $reading = Cache::get(self::KEY);

        if ($this->due($reading) && ! Cache::has(self::PAUSE)) {
            $reading = $this->refresh() ?? $reading;
        }

        if (! $reading || Carbon::parse($reading['observed_at'])->lt(now()->subHours(self::SHOW_FOR_HOURS))) {
            return null;
        }

        return $reading;
    }

    /** Fetches a new reading from ARSO and stores it; null (and the old reading kept) when that fails */
    public function refresh(): ?array
    {
        try {
            $response = Http::timeout(4)->connectTimeout(3)->acceptJson()->get(self::API);
            $reading = $response->successful() ? self::parse((array) $response->json()) : null;
        } catch (Throwable $e) {
            $reading = null;
        }

        if (! $reading) {
            Cache::put(self::PAUSE, true, now()->addMinutes(self::PAUSE_MINUTES));
            Log::warning('Weather: could not read the current weather from ARSO.');

            return null;
        }

        $reading['fetched_at'] = now()->toIso8601String();
        Cache::forever(self::KEY, $reading);
        Cache::forget(self::PAUSE);

        return $reading;
    }

    /**
     * Picks the latest measurement out of ARSO's answer.
     *
     * Its icon code reads sky_weather_daytime, the weather part being optional:
     * "prevCloudy_day", "prevCloudy_lightRA_day", "clear_night", "FG_day".
     */
    public static function parse(array $json): ?array
    {
        $days = data_get($json, 'observation.features.0.properties.days');
        if (! is_array($days)) {
            return null;
        }

        $latest = null;
        foreach ($days as $day) {
            foreach ((array) ($day['timeline'] ?? []) as $entry) {
                if (is_array($entry) && isset($entry['valid']) && (! $latest || strcmp($entry['valid'], $latest['valid']) > 0)) {
                    $latest = $entry;
                }
            }
        }

        if (! $latest || ! is_numeric($latest['t'] ?? null)) {
            return null;
        }

        try {
            $observed = Carbon::parse($latest['valid']);
        } catch (Throwable $e) {
            return null;
        }

        $icon = (string) ($latest['clouds_icon_wwsyn_icon'] ?? '');
        $parts = explode('_', $icon);
        $night = end($parts) === 'night';
        $sky = $parts[0] ?? '';
        $weather = count($parts) > 2 ? $parts[1] : '';

        return [
            'temp' => (int) round((float) $latest['t']),
            'condition' => self::condition($sky, $weather),
            'night' => $night,
            'description' => trim((string) ($latest['clouds_shortText_wwsyn_shortText'] ?? '')),   // ARSO's own words, in Slovenian
            'observed_at' => $observed->toIso8601String(),
        ];
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

    private function due(?array $reading): bool
    {
        return ! $reading || ! isset($reading['fetched_at'])
            || Carbon::parse($reading['fetched_at'])->lte(now()->subMinutes(self::REFRESH_MINUTES));
    }
}
