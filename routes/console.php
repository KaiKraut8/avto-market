<?php

use App\Services\Billing;
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
