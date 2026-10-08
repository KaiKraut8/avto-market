<?php

use App\Models\Car;
use App\Models\CarDeal;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Visitor;

function dealFor(Car $car, array $values = []): CarDeal
{
    return $car->deals()->create($values + [
        'regular_price' => $car->price, 'deal_price' => $car->price * 0.9, 'member_price' => null, 'ends_at' => now()->addWeek(),
    ]);
}

function save(User $user, Car $car): void
{
    WishlistItem::create(['visitor_id' => bin2hex(random_bytes(16)), 'user_id' => $user->id, 'car_id' => $car->id]);
}

it('lets only premium sellers run deals on their own priced cars', function () {
    $free = User::factory()->create();
    $premium = User::factory()->premium()->create();
    $freeCar = Car::factory()->for($free)->create(['price' => 20000]);
    $premiumCar = Car::factory()->for($premium)->create(['price' => 20000]);
    $deal = ['deal_price' => '18.000', 'days' => 7];

    $this->actingAs($free)->post(route('cars.deal.store', $freeCar), $deal)->assertForbidden();
    $this->actingAs($free)->post(route('cars.deal.store', $premiumCar), $deal)->assertForbidden();   // not theirs
    $this->actingAs($premium)->post(route('cars.deal.store', Car::factory()->unowned()->create(['price' => 9000])), $deal)->assertForbidden();
    $this->actingAs($premium)->post(route('cars.deal.store', $premiumCar), $deal)->assertRedirect(route('cars.show', $premiumCar));

    $active = $premiumCar->activeDeal()->first();
    expect((float) $active->deal_price)->toBe(18000.0)
        ->and((float) $active->regular_price)->toBe(20000.0)
        ->and($active->percentOff())->toBe(10);
});

it('keeps deal prices between 1 and 50 percent off, the member price lower still', function () {
    $seller = User::factory()->premium()->create();
    $car = Car::factory()->for($seller)->create(['price' => 20000]);

    foreach ([['deal_price' => '20000'], ['deal_price' => '9999'], ['deal_price' => '18000', 'member_price' => '19000'], ['deal_price' => '18000', 'days' => 30]] as $bad) {
        $this->actingAs($seller)->post(route('cars.deal.store', $car), $bad + ['days' => 7])->assertSessionHasErrorsIn('deal');
    }
    $this->actingAs($seller)->post(route('cars.deal.store', $car), ['deal_price' => '18000', 'member_price' => '17000', 'days' => 3])->assertSessionHasNoErrors();
    expect($car->deals()->count())->toBe(1);
});

it('runs one deal at a time, and ends it when the seller changes the price', function () {
    $seller = User::factory()->premium()->create();
    $car = Car::factory()->for($seller)->create(['price' => 20000]);

    $this->actingAs($seller)->post(route('cars.deal.store', $car), ['deal_price' => '19000', 'days' => 7]);
    $this->actingAs($seller)->post(route('cars.deal.store', $car), ['deal_price' => '18000', 'days' => 7]);
    expect(CarDeal::active()->where('car_id', $car->id)->count())->toBe(1)
        ->and((float) $car->activeDeal()->first()->deal_price)->toBe(18000.0);

    $this->actingAs($seller)->put(route('cars.update', $car), ['name' => $car->name, 'price' => '25000', 'location' => 'Celje', 'country' => 'Slovenia']);
    expect($car->activeDeal()->first())->toBeNull();
});

it('shows the deal to everyone and the member price only to premium buyers', function () {
    $car = Car::factory()->create(['price' => 20000]);
    dealFor($car, ['deal_price' => 18000, 'member_price' => 17000]);

    $this->get(route('deals.index'))->assertSee($car->name)->assertSee('18.000 €')->assertSee('Premium buyers pay 17.000 €');
    $this->get(route('cars.show', $car))->assertSee('20.000 €')->assertSee('18.000 €');
    $this->actingAs(User::factory()->premiumBuyer()->create())->get(route('cars.show', $car))->assertSee('Your member price');
    $this->get(route('home'))->assertSee("Deals you can't miss")->assertSee('Grab it');
});

it('stops showing a deal when it ends', function () {
    $car = Car::factory()->create(['price' => 20000]);
    dealFor($car, ['ends_at' => now()->subMinute()]);

    $this->get(route('deals.index'))->assertDontSee($car->name);
    expect($car->activeDeal()->first())->toBeNull();
});

it('tells everyone who saved a car about its deal, and premium buyers about similar cars', function () {
    $seller = User::factory()->premium()->create();
    $bmw = Car::factory()->for($seller)->create(['name' => 'BMW 530d', 'price' => 41000]);
    $otherBmw = Car::factory()->create(['name' => 'BMW X5', 'price' => 60000]);
    $clio = Car::factory()->create(['name' => 'Renault Clio', 'price' => 12000]);

    $saver = User::factory()->create();               // free account, saved this car
    save($saver, $bmw);
    $fan = User::factory()->premiumBuyer()->create(); // premium buyer, saved another BMW
    save($fan, $otherBmw);
    $freeFan = User::factory()->create();             // free account, saved another BMW: no "similar" alerts
    save($freeFan, $otherBmw);
    $other = User::factory()->premiumBuyer()->create(); // premium buyer, saved something unrelated
    save($other, $clio);

    $this->actingAs($seller)->post(route('cars.deal.store', $bmw), ['deal_price' => '38000', 'days' => 7]);

    expect($saver->notifications()->pluck('type')->all())->toBe(['deal_saved_car'])
        ->and($fan->notifications()->pluck('type')->all())->toBe(['deal_for_you'])
        ->and($freeFan->notifications()->count())->toBe(0)
        ->and($other->notifications()->count())->toBe(0)
        ->and($seller->notifications()->count())->toBe(0);

    $this->actingAs($saver)->get(route('notifications.index'))->assertSee('A car you saved is on a deal')->assertSee('38.000 €');
    expect($saver->unreadNotifications()->count())->toBe(0);   // opening the list reads them
});

it('alerts premium buyers about new cars and deals that match a saved search', function () {
    $buyer = User::factory()->premiumBuyer()->create();
    $this->actingAs($buyer)->post(route('saved-searches.store'), ['query' => 'Audi', 'max_price' => '30.000'])->assertSessionHasNoErrors();

    $seller = User::factory()->premium()->create();
    $this->actingAs($seller)->post(route('cars.store'), ['name' => 'Audi A4 Avant', 'price' => '27.400', 'location' => 'Celje', 'country' => 'Slovenia', 'terms' => 1]);
    $this->actingAs($seller)->post(route('cars.store'), ['name' => 'Audi Q7', 'price' => '45.000', 'location' => 'Celje', 'country' => 'Slovenia', 'terms' => 1]);
    $this->actingAs($seller)->post(route('cars.store'), ['name' => 'BMW 320d', 'price' => '20.000', 'location' => 'Celje', 'country' => 'Slovenia', 'terms' => 1]);
    expect($buyer->notifications()->where('type', 'search_match')->count())->toBe(1);

    // the Q7 is too expensive, until a deal brings it under the limit
    $q7 = Car::where('name', 'Audi Q7')->first();
    $this->actingAs($seller)->post(route('cars.deal.store', $q7), ['deal_price' => '29.500', 'days' => 7]);
    expect($buyer->notifications()->where('type', 'deal_for_you')->count())->toBe(1);
});

it('keeps saved searches for premium buyers only', function () {
    $this->actingAs(User::factory()->create())->post(route('saved-searches.store'), ['query' => 'BMW'])->assertForbidden();

    $buyer = User::factory()->premiumBuyer()->create();
    $this->actingAs($buyer)->post(route('saved-searches.store'), ['query' => '', 'max_price' => ''])->assertSessionHasErrorsIn('search', ['query']);
    $this->actingAs($buyer)->post(route('saved-searches.store'), ['query' => 'BMW']);
    $search = $buyer->savedSearches()->first();
    $this->actingAs(User::factory()->premiumBuyer()->create())->delete(route('saved-searches.destroy', $search))->assertNotFound();
    $this->actingAs($buyer)->delete(route('saved-searches.destroy', $search));
    expect($buyer->savedSearches()->count())->toBe(0);
});

it('tells premium sellers when someone saves their car, in one updated alert', function () {
    $premium = User::factory()->premium()->create();
    $free = User::factory()->create();
    $car = Car::factory()->for($premium)->create();
    $freeCar = Car::factory()->for($free)->create();

    foreach (['1', '2'] as $who) {
        $this->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, str_repeat($who, 32))->postJson(route('wishlist.toggle', $car));
    }
    $this->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, str_repeat('3', 32))->postJson(route('wishlist.toggle', $freeCar));

    expect($premium->notifications()->count())->toBe(1)
        ->and($premium->notifications()->first()->data['saves'])->toBe(2)
        ->and($free->notifications()->count())->toBe(0);
});

it('tells the seller about inquiries, and that a premium buyer gets the member price', function () {
    $seller = User::factory()->premium()->create();
    $car = Car::factory()->for($seller)->create(['price' => 20000]);
    dealFor($car, ['member_price' => 17000]);

    $this->actingAs(User::factory()->premiumBuyer()->create())->post(route('cars.inquiries.store', $car), [
        'buyer_name' => 'Ana Kovač', 'buyer_email' => 'ana@example.org', 'buyer_phone' => '040 123 456',
    ])->assertSessionHasNoErrors();

    $alert = $seller->notifications()->first();
    expect($alert->type)->toBe('inquiry')->and($alert->data['member'])->toBeTrue();
    $this->actingAs($seller)->get(route('notifications.index'))->assertSee('your member price applies');
});

it('links the wishlist of this browser to the account on login', function () {
    $user = User::factory()->create(['password' => 'secret-pass-1']);
    $car = Car::factory()->create();
    $visitor = str_repeat('9', 32);

    $this->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, $visitor)->postJson(route('wishlist.toggle', $car));
    expect(WishlistItem::first()->user_id)->toBeNull();

    $this->withUnencryptedCookie(Visitor::COOKIE, $visitor)->post(route('login'), ['email' => $user->email, 'password' => 'secret-pass-1']);
    expect(WishlistItem::first()->user_id)->toBe($user->id);
});

it('shows insights to premium sellers and a teaser to the rest', function () {
    $premium = User::factory()->premium()->create();
    Car::factory()->for($premium)->create();
    $free = User::factory()->create();
    Car::factory()->for($free)->create();

    $this->actingAs($premium)->get(route('account'))->assertSee('Views (30 days)');
    $this->actingAs($free)->get(route('account'))->assertDontSee('Views (30 days)')->assertSee('Insights are part of premium for sellers.');
});
