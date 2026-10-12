<?php

use App\Services\Weather;
use Illuminate\Support\Facades\Http;

// ARSO's answer for Maribor, cut down to the part that is used, with the measurement from $minutesAgo
function arso(string $icon = 'prevCloudy_day', string $temp = '11', int $minutesAgo = 20): array
{
    return ['observation' => ['features' => [['properties' => ['days' => [['timeline' => [[
        'valid' => now()->subMinutes($minutesAgo)->utc()->format('Y-m-d\TH:i:s+00:00'),
        't' => $temp,
        'clouds_icon_wwsyn_icon' => $icon,
        'clouds_shortText_wwsyn_shortText' => 'pretežno oblačno',
    ]]]]]]]]];
}

// ARSO's answer to "which station is nearest to this point": the station's place, its coordinates and its measurement
function arsoNear(string $place, float $lat, float $lon, string $icon = 'overcast_lightRA_day', string $temp = '9'): array
{
    return [
        'geometry' => ['type' => 'Point', 'coordinates' => [$lon, $lat]],
        'properties' => ['title' => $place, 'days' => [['timeline' => [[
            'valid' => now()->subMinutes(10)->utc()->format('Y-m-d\TH:i:s+00:00'),
            't' => $temp,
            'clouds_icon_wwsyn_icon' => $icon,
        ]]]]],
    ];
}

function days(array $answer): array
{
    return $answer['observation']['features'][0]['properties']['days'];
}

it('shows the current weather in Maribor next to the menu button', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::response(arso())]);

    $this->get('/')->assertOk()
        ->assertSee('weather-mostly-cloudy', false)
        ->assertSee('11°')
        ->assertSee('Mostly cloudy')
        ->assertSee('https://vreme.arso.gov.si/napoved/Maribor/graf', false);
});

it('names the weather in the visitor\'s language', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::response(arso('clear_night', '-3'))]);

    $this->get(route('language', 'sl'));
    $this->get('/cars')->assertSee('Jasno')->assertSee('−3°')->assertSee('weather-clear is-night', false);
});

it('asks ARSO again only once the reading is 15 minutes old', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::response(arso())]);

    $this->get('/');
    $this->get('/cars');
    Http::assertSentCount(1);

    $this->travel(14)->minutes();
    $this->get('/');
    Http::assertSentCount(1);

    $this->travel(2)->minutes();
    $this->get('/');
    Http::assertSentCount(2);
});

it('keeps the last reading while ARSO is down, and drops it once it is 3 hours old', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::sequence()
        ->push(arso())
        ->whenEmpty(Http::response('down', 503))]);

    $this->get('/')->assertSee('11°');

    $this->travel(20)->minutes();
    $this->get('/')->assertSee('11°');           // the fetch failed: the old reading stays
    $this->get('/cars')->assertSee('11°');
    Http::assertSentCount(2);                    // and pages wait a few minutes before trying again

    $this->travel(3)->hours();
    $this->get('/')->assertOk()->assertDontSee('weather-temp', false);
});

it('reads rain, snow, storms, fog and night from ARSO\'s icon codes', function (string $icon, string $condition, bool $night) {
    $reading = Weather::parse(days(arso($icon)), 'Maribor');

    expect($reading['condition'])->toBe($condition)
        ->and($reading['night'])->toBe($night)
        ->and($reading['temp'])->toBe(11);
})->with([
    ['clear_day', 'clear', false],
    ['clear_night', 'clear', true],
    ['mostClear_day', 'mostly-clear', false],
    ['partCloudy_night', 'partly-cloudy', true],
    ['prevCloudy_day', 'mostly-cloudy', false],
    ['overcast_day', 'overcast', false],
    ['prevCloudy_lightRA_day', 'rain', false],
    ['overcast_heavyDZ_night', 'rain', true],
    ['overcast_modRASN_day', 'sleet', false],
    ['overcast_modSN_day', 'snow', false],
    ['overcast_modTSRA_day', 'storm', false],
    ['overcast_modGR_day', 'hail', false],
    ['FG_day', 'fog', false],
]);

it('ignores an answer without a measurement', function () {
    expect(Weather::parse([], 'Maribor'))->toBeNull()
        ->and(Weather::parse(null, 'Maribor'))->toBeNull()
        ->and(Weather::parse([['timeline' => [['valid' => '2026-01-01T00:00:00+00:00', 't' => '']]]], 'Maribor'))->toBeNull()
        ->and(Weather::parse(days(arso()), ''))->toBeNull();
});

it('hands open pages the widget, and nothing when there is no reading', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::sequence()->push('down', 503)->push(arso('overcast_modSN_day', '0'))]);

    $this->getJson('/weather')->assertOk()->assertExactJson(['html' => '', 'place' => null]);

    $this->travel(6)->minutes();   // past the pause after a failed fetch
    $html = $this->getJson('/weather')->assertOk()->json('html');
    expect($html)->toContain('weather-snow')->toContain('0°');
});

it('refreshes the weather from the scheduler', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::response(arso('overcast_day', '7'))]);

    $this->artisan('weather:refresh')->expectsOutputToContain('Maribor: 7 °C, overcast')->assertSuccessful();
    $this->get('/')->assertSee('7°')->assertSee('Overcast');
    Http::assertSentCount(1);   // the page used what the scheduler fetched
});

it('shows the weather at the visitor\'s nearest station, from a location rounded to about 1 km', function () {
    Http::fake([
        'vreme.arso.gov.si/api/1.0/location/*' => Http::response(arso()),
        'vreme.arso.gov.si/api/1.0/locations/*' => Http::response(arsoNear('Novo mesto', 45.8018, 15.1773)),
    ]);

    $res = $this->getJson('/weather?lat=45.812345&lon=15.164321')->assertOk()->assertJson(['place' => 'Novo mesto']);
    expect($res->json('html'))->toContain('Novo mesto')->toContain('9°')->toContain('weather-rain')
        ->toContain('https://vreme.arso.gov.si/napoved/Novo%20mesto/graf');

    Http::assertSent(fn ($request) => str_contains($request->url(), '/locations/') && str_contains($request->url(), 'lat=45.81') && str_contains($request->url(), 'lon=15.16'));
    Http::assertNotSent(fn ($request) => str_contains($request->url(), '45.812345'));   // the precise spot never leaves the site
});

it('shares the weather for a spot between visitors, and asks again after 15 minutes', function () {
    Http::fake(['vreme.arso.gov.si/api/1.0/locations/*' => Http::response(arsoNear('Celje', 46.2361, 15.2677))]);

    $this->getJson('/weather?lat=46.24&lon=15.27')->assertJson(['place' => 'Celje']);
    $this->getJson('/weather?lat=46.2412&lon=15.2689')->assertJson(['place' => 'Celje']);   // rounds to the same spot
    Http::assertSentCount(1);

    $this->travel(16)->minutes();
    $this->getJson('/weather?lat=46.24&lon=15.27')->assertJson(['place' => 'Celje']);
    Http::assertSentCount(2);
});

it('shows Maribor when the nearest station is too far away to stand for the visitor\'s place', function () {
    Http::fake([
        'vreme.arso.gov.si/api/1.0/location/*' => Http::response(arso()),
        'vreme.arso.gov.si/api/1.0/locations/*' => Http::response(arsoNear('Gradec', 47.0, 15.43)),   // asked from Vienna
    ]);

    $this->getJson('/weather?lat=48.21&lon=16.37')->assertJson(['place' => 'Maribor']);
});

it('shows Maribor for a location that is no location, or when the nearest station can\'t be read', function () {
    Http::fake([
        'vreme.arso.gov.si/api/1.0/location/*' => Http::response(arso()),
        'vreme.arso.gov.si/api/1.0/locations/*' => Http::response('down', 503),
    ]);

    $this->getJson('/weather?lat=abc&lon=15')->assertJson(['place' => 'Maribor']);
    $this->getJson('/weather?lat=123&lon=15')->assertJson(['place' => 'Maribor']);
    $this->getJson('/weather?lat=46.05&lon=14.51')->assertJson(['place' => 'Maribor']);
    Http::assertNotSent(fn ($request) => str_contains($request->url(), 'lat=123') || str_contains($request->url(), 'abc'));
});

it('tells visitors in the privacy policy what happens with their location', function () {
    $this->get(route('privacy'))->assertOk()
        ->assertSee('Local weather')
        ->assertSee('ARSO, the Slovenian Environment Agency')
        ->assertSee('consent, Art. 6(1)(a)');
});
