<?php

namespace App\Services;

use App\Models\Car;
use App\Models\User;
use Illuminate\Support\Facades\DB;

// Paid placements (simulated: nothing is charged). Prices live in config/pricing.php.
class Pricing
{
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
        $plan = $plan === 'yearly' ? 'yearly' : 'monthly';
        $user->forceFill([
            'premium_plan' => $plan,
            'premium_since' => now(),
            'premium_until' => now()->addMonths($plan === 'yearly' ? 12 : 1),
        ])->save();

        return true;
    }

    // One more week of "pushed forward"; extends a push that is still running. Refused for premium cars.
    public function boost(Car $car): bool
    {
        if ($car->isPremium()) {
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
