<?php

namespace App\Services;

use App\Models\Car;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Paid placements (simulated: nothing is charged). Prices live in config/pricing.php.
class Pricing
{
    // Premium billing periods, in months
    public const PLANS = ['monthly' => 1, 'quarterly' => 3, 'yearly' => 12];

    // Percent saved against paying monthly for the same months
    public static function saving(string $plan): int
    {
        return match ($plan) {
            'quarterly' => (int) config('pricing.premium_quarterly_saving'),
            'yearly' => self::yearlySaving(),
            default => 0,
        };
    }

    // The price of one period of a premium plan ($kind: seller or buyer)
    public static function price(string $kind, string $plan): float
    {
        return round(self::fullPrice($kind, $plan) * (1 - self::saving($plan) / 100), 2);
    }

    // The same months at the monthly price
    public static function fullPrice(string $kind, string $plan): float
    {
        $monthly = $kind === 'buyer' ? self::buyerMonthly() : self::monthly();

        return round($monthly * (self::PLANS[$plan] ?? 1), 2);
    }

    public static function quarterlySaving(): int
    {
        return self::saving('quarterly');
    }

    public static function monthly(): float
    {
        return (float) config('pricing.premium_monthly');
    }

    public static function yearlySaving(): int
    {
        return (int) config('pricing.premium_yearly_saving');
    }

    // 12 months at the monthly price, minus the saving
    public static function yearly(): float
    {
        return round(self::monthly() * 12 * (1 - self::yearlySaving() / 100), 2);
    }

    public static function yearlyAtMonthlyRate(): float
    {
        return round(self::monthly() * 12, 2);
    }

    public static function buyerMonthly(): float
    {
        return (float) config('pricing.buyer_monthly');
    }

    public static function buyerYearly(): float
    {
        return round(self::buyerMonthly() * 12 * (1 - self::yearlySaving() / 100), 2);
    }

    public static function buyerYearlyAtMonthlyRate(): float
    {
        return round(self::buyerMonthly() * 12, 2);
    }

    public static function boostWeekly(): float
    {
        return (float) config('pricing.boost_weekly');
    }

    // Premium for a seller account. Refused while a subscription is still running.
    public function activatePremium(User $user, string $plan): bool
    {
        if ($user->hasPremium()) {
            return false;
        }
        $plan = isset(self::PLANS[$plan]) ? $plan : 'monthly';
        $user->forceFill([
            'premium_plan' => $plan,
            'premium_since' => now(),
            'premium_until' => now()->addMonths(self::PLANS[$plan]),
        ])->save();

        return true;
    }

    // Premium for a buyer account. Refused while a subscription is still running.
    public function activateBuyerPremium(User $user, string $plan): bool
    {
        if ($user->hasBuyerPremium()) {
            return false;
        }
        $plan = isset(self::PLANS[$plan]) ? $plan : 'monthly';
        $user->forceFill([
            'buyer_premium_plan' => $plan,
            'buyer_premium_since' => now(),
            'buyer_premium_until' => now()->addMonths(self::PLANS[$plan]),
        ])->save();

        return true;
    }

    // One more week of "pushed forward"; extends a push that is still running. Refused for premium cars,
    // unless it was already paid for ($force): then the week is given anyway.
    public function boost(Car $car, bool $force = false): bool
    {
        if (! $force && $car->isPremium()) {
            return false;
        }
        $days = (int) config('pricing.boost_days');
        $affected = DB::table('cars')
            ->where('id', $car->id)
            ->whereNull('deleted_at')
            ->update(['boosted_until' => DB::raw("GREATEST(COALESCE(boosted_until, NOW()), NOW()) + INTERVAL {$days} DAY")]);
        $car->refresh();

        return $affected === 1;
    }
}
