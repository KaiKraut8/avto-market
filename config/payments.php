<?php

// Payments go through Mollie (cards, PayPal, paysafecard; EU-based, works for Slovenian businesses).
// Without MOLLIE_KEY the "test" driver is used: a local page where you choose whether the payment succeeds.
// It is refused when APP_ENV=production, so a live site can't hand out premium for free.
return [
    'driver' => env('PAYMENT_DRIVER', env('MOLLIE_KEY') ? 'mollie' : 'test'),

    'mollie' => [
        'key' => env('MOLLIE_KEY'),          // test_... while trying it out, live_... for real money
        'api' => 'https://api.mollie.com/v2',
        // for the admin's balance and withdrawals: an access token with balances.read, payouts.read, payouts.write
        'access_token' => env('MOLLIE_ACCESS_TOKEN'),
    ],

    // the order they are offered in; only cards and PayPal can be charged again without the customer
    'methods' => ['creditcard', 'paypal', 'paysafecard'],
    'recurring' => ['creditcard', 'paypal'],

    // renewals are charged this long before the paid period ends, and retried daily if they fail
    'renew_before_hours' => 24,
    'max_failed_renewals' => 3,
    // paysafecard can't be charged again: the customer is reminded this many days before the end
    'remind_before_days' => 3,
];
