<?php

use App\Models\Car;
use App\Models\User;

it('orders listings premium first, then pushed, then the rest', function () {
    $regular = Car::factory()->create(['name' => 'Regular']);
    $pushed = Car::factory()->boosted()->create(['name' => 'Pushed']);
    $premium = Car::factory()->for(User::factory()->premium())->create(['name' => 'Premium']);
    $legacyPremium = Car::factory()->unowned()->create(['name' => 'Legacy', 'legacy_premium_until' => now()->addDay()]);

    expect(Car::withPlacement()->listingOrder()->pluck('name')->all())
        ->toBe(['Premium', 'Legacy', 'Pushed', 'Regular']);
});

it('searches name, description, location and country, matching text literally', function () {
    $bmw = Car::factory()->create(['name' => 'BMW 320d', 'description' => 'Full service history', 'location' => 'Celje', 'country' => 'Slovenia']);
    $audi = Car::factory()->create(['name' => 'Audi A4', 'description' => '100% original', 'location' => 'Zagreb', 'country' => 'Croatia']);

    expect(Car::search('bmw')->pluck('id')->all())->toBe([$bmw->id])
        ->and(Car::search('service')->pluck('id')->all())->toBe([$bmw->id])
        ->and(Car::search('Zagreb')->pluck('id')->all())->toBe([$audi->id])
        ->and(Car::search('croatia')->pluck('id')->all())->toBe([$audi->id])
        ->and(Car::search('100%')->pluck('id')->all())->toBe([$audi->id])
        ->and(Car::search('%')->pluck('id')->all())->toBe([$audi->id])     // only the car whose text contains "%"
        ->and(Car::search('_')->count())->toBe(0)
        ->and(Car::search('')->count())->toBe(2);
});

it('takes seller contact from the profile, or the legacy fields for unowned cars', function () {
    $owned = Car::factory()->for(User::factory()->state(['name' => 'Ana Kovač', 'phone' => '+386 40 111 222', 'email' => 'ana@example.com']))->create();
    $legacy = Car::factory()->unowned()->create(['legacy_seller_name' => 'Old Seller', 'legacy_seller_phone' => '+386 41 000 000', 'legacy_seller_email' => 'old@example.com']);
    $none = Car::factory()->unowned()->create();

    expect($owned->sellerContact())->toBe(['name' => 'Ana Kovač', 'phone' => '+386 40 111 222', 'email' => 'ana@example.com'])
        ->and($legacy->sellerContact()['email'])->toBe('old@example.com')
        ->and($none->sellerContact())->toBeNull();
});
