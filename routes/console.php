<?php

use App\Services\Billing;
use App\Services\Weather;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Premium renewals, paysafecard reminders and ending cancelled subscriptions.
// Needs the scheduler running: a cron entry "* * * * * php /path/to/artisan schedule:run".
Artisan::command('subscriptions:renew', function (Billing $billing) {
    $done = $billing->renewDue();
    $this->info("Renewals charged: {$done['charged']}, reminders sent: {$done['reminded']}, subscriptions ended: {$done['ended']}");
})->purpose('Charge due premium renewals, remind paysafecard customers, end cancelled subscriptions');

Schedule::command('subscriptions:renew')->hourly()->withoutOverlapping();

// The current weather in Maribor from ARSO, shown next to the menu button. Pages also fetch it themselves
// once a reading is 15 minutes old, so the widget stays current even without the scheduler.
Artisan::command('weather:refresh', function (Weather $weather) {
    $reading = $weather->refresh();
    $reading
        ? $this->info("Maribor: {$reading['temp']} °C, {$reading['condition']}, measured {$reading['observed_at']}")
        : $this->error('Could not read the weather from ARSO; the last reading is kept.');
})->purpose('Fetch the current weather in Maribor from ARSO');

Schedule::command('weather:refresh')->everyFifteenMinutes()->withoutOverlapping();
