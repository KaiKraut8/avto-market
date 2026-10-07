<?php

namespace App\Support;

// Prices are shown in the Slovenian format: 41.000 € for car prices, 44,59 € for fees
class Money
{
    public static function price(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return 'Price on request';
        }

        return number_format((float) $amount, 0, ',', '.').' €';
    }

    public static function eur(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
