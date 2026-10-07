<?php

// Company details shown in the footer and the home page call band. Placeholders: replace with the real ones.
return [
    'name' => 'KAI Garage d.o.o.',
    'tagline' => 'Hand-picked used cars, checked and documented part by part.',
    'email' => 'kaigarage.info@gmail.com',
    'phone' => '+386 40 123 456',
    'address' => 'Dunajska cesta 100, 1000 Ljubljana, Slovenia',
    'hours' => 'Mon–Fri 8:00–18:00, Sat 9:00–13:00',

    // used for the "open now" status on the contact page (1 = Monday ... 7 = Sunday, null = closed)
    'timezone' => 'Europe/Ljubljana',
    'opening_hours' => [
        1 => ['08:00', '18:00'],
        2 => ['08:00', '18:00'],
        3 => ['08:00', '18:00'],
        4 => ['08:00', '18:00'],
        5 => ['08:00', '18:00'],
        6 => ['09:00', '13:00'],
        7 => null,
    ],
    // a link to the address on a map
    'map_url' => 'https://www.google.com/maps/search/?api=1&query='.rawurlencode('Dunajska cesta 100, 1000 Ljubljana, Slovenia'),
];
