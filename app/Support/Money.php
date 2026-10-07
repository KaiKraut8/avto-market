<?php

namespace App\Support;

// Prices are shown in the Slovenian format: 41.000 € for car prices, 44,59 € for fees
class Money
{
    public static function price(float|string|null $amount): string
    {
        if ($amount === null || $amount === '') {
            return __('Price on request');
        }

        return number_format((float) $amount, 0, ',', '.').' €';
    }

    // What people type into a price field: "24.900", "24 900 €" and "24900" all mean 24900; empty means none
    public static function parse(mixed $input): ?string
    {
        $price = str_replace(['.', ' ', '€'], '', trim((string) $input));

        return $price === '' ? null : str_replace(',', '.', $price);
    }

    public static function eur(float|string $amount): string
    {
        return number_format((float) $amount, 2, ',', '.').' €';
    }
}
