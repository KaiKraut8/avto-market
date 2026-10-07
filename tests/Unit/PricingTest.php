<?php

use App\Models\Car;
use App\Models\User;
use App\Services\Pricing;

it('prices premium like the old site', function () {
    expect(Pricing::monthly())->toBe(44.59)
        ->and(Pricing::yearlyAtMonthlyRate())->toBe(535.08)
        ->and(Pricing::yearly())->toBe(326.40)      // 39 % off 12 months
        ->and(Pricing::boostWeekly())->toBe(6.99);
});

it('activates premium for an account once, monthly or yearly', function () {
    $user = User::factory()->create();

    expect(app(Pricing::class)->activatePremium($user, 'yearly'))->toBeTrue();
    $user->refresh();
    expect($user->hasPremium())->toBeTrue()
        ->and($user->premium_plan)->toBe('yearly')
        ->and((int) round(now()->diffInMonths($user->premium_until)))->toBe(12)
        ->and(app(Pricing::class)->activatePremium($user, 'monthly'))->toBeFalse();   // still running
});

it('lets premium run out', function () {
    $user = User::factory()->create(['premium_until' => now()->subMinute()]);
    $car = Car::factory()->for($user)->create();

    expect($user->hasPremium())->toBeFalse()
        ->and(Car::withPlacement()->find($car->id)->isPremium())->toBeFalse();
});

it('pushes a car for a week and extends a running push', function () {
    $car = Car::factory()->create();
    $pricing = app(Pricing::class);

    $pricing->boost($car);
    $first = $car->boosted_until;
    $pricing->boost($car);

    expect($car->isBoosted())->toBeTrue()
        ->and((int) round($first->diffInDays($car->boosted_until)))->toBe(7);
});

it('does not push a premium seller\'s car', function () {
    $car = Car::factory()->for(User::factory()->premium())->create();

    expect(app(Pricing::class)->boost($car))->toBeFalse()
        ->and($car->fresh()->boosted_until)->toBeNull();
});
