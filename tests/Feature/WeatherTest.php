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

it('shows the current weather in Maribor next to the menu button', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::response(arso())]);

    $this->get('/')->assertOk()
        ->assertSee('weather-mostly-cloudy', false)
        ->assertSee('11°')
        ->assertSee('Mostly cloudy')
        ->assertSee(Weather::PAGE, false);
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
    $reading = Weather::parse(arso($icon));

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
    expect(Weather::parse([]))->toBeNull()
        ->and(Weather::parse(['observation' => ['features' => [['properties' => ['days' => [['timeline' => [['valid' => '2026-01-01T00:00:00+00:00', 't' => '']]]]]]]]]))->toBeNull();
});

it('hands open pages the widget, and nothing when there is no reading', function () {
    Http::fake(['vreme.arso.gov.si/*' => Http::sequence()->push('down', 503)->push(arso('overcast_modSN_day', '0'))]);

    $this->getJson('/weather')->assertOk()->assertExactJson(['html' => '']);

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
