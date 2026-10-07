<?php

// Paid placements. Purchases are simulated: there is no payment provider yet, so no money is taken.
//  - Premium: monthly or yearly subscription for a seller account; every car of a premium seller
//    is shown first on All cars and in the home page highlights, in gold.
//    Premium sellers can also run special deals, see insights per car and get an alert when someone saves a car.
//  - Premium for buyers: monthly or yearly; member prices on deals, alerts for deals on similar cars,
//    and saved searches that alert on new matching cars.
//  - Push forward: weekly, per car, shown first among the regular (non-premium) cars.
return [
    'premium_monthly' => 44.59,
    'premium_yearly_saving' => 39,   // percent saved against paying monthly for a year (both premium plans)
    'buyer_monthly' => 4.99,
    'boost_weekly' => 6.99,
    'boost_days' => 7,

    // someone counts as "watching" a car if its open page checked in within this many seconds
    'watch_window' => 40,
];
