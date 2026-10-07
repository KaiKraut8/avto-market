<?php

use App\Models\Car;
use App\Models\CarView;
use App\Models\User;
use App\Models\WishlistItem;
use App\Support\Visitor;

// The visitor cookie, also on JSON requests (Laravel's test client only sends cookies with those when asked to)
function asVisitor($test, string $id)
{
    return $test->withCredentials()->withUnencryptedCookie(Visitor::COOKIE, $id);
}

it('reveals the seller\'s phone and email only after valid buyer details', function () {
    $seller = User::factory()->create(['phone' => '+386 41 555 777', 'email' => 'seller@example.com']);
    $car = Car::factory()->for($seller)->create();
    $buyer = str_repeat('a', 32);

    asVisitor($this, $buyer)->get(route('cars.show', $car))->assertDontSee('seller@example.com')->assertDontSee('+386 41 555 777');

    asVisitor($this, $buyer)->post(route('cars.inquiries.store', $car), ['buyer_name' => 'X1', 'buyer_email' => 'bad', 'buyer_phone' => '1'])
        ->assertSessionHasErrorsIn('inquiry', ['buyer_name', 'buyer_email', 'buyer_phone']);

    asVisitor($this, $buyer)->post(route('cars.inquiries.store', $car), [
        'buyer_name' => 'Ana Kovač', 'buyer_email' => 'ana@example.org', 'buyer_phone' => '040 123 456', 'buyer_message' => 'Still available?',
    ])->assertRedirect(route('cars.show', $car).'#contact');

    asVisitor($this, $buyer)->get(route('cars.show', $car))->assertSee('seller@example.com')->assertSee('tel:+38641555777', false);
    asVisitor($this, str_repeat('b', 32))->get(route('cars.show', $car))->assertDontSee('seller@example.com');   // another visitor
    expect($car->inquiries()->first()->name)->toBe('Ana Kovač');
});

it('keeps a wishlist per visitor', function () {
    $car = Car::factory()->create();
    $me = str_repeat('c', 32);

    asVisitor($this, $me)->postJson(route('wishlist.toggle', $car))->assertJson(['wished' => true, 'count' => 1]);
    asVisitor($this, $me)->get(route('wishlist.index'))->assertSee($car->name);
    asVisitor($this, str_repeat('d', 32))->get(route('wishlist.index'))->assertDontSee($car->name);
    asVisitor($this, $me)->postJson(route('wishlist.toggle', $car))->assertJson(['wished' => false, 'count' => 0]);
    expect(WishlistItem::count())->toBe(0);
});

it('counts views per visit and people per browser, not reloads after saving', function () {
    $owner = User::factory()->create();
    $car = Car::factory()->for($owner)->create();

    asVisitor($this, str_repeat('e', 32))->get(route('cars.show', $car));
    asVisitor($this, str_repeat('e', 32))->get(route('cars.show', $car));
    asVisitor($this, str_repeat('f', 32))->get(route('cars.show', $car));
    // the page shown right after saving (it carries a flash message) isn't a new view
    $this->actingAs($owner)->withSession(['status' => 'Saved.'])->get(route('cars.show', $car));

    expect(CarView::where('car_id', $car->id)->count())->toBe(3);
    $this->getJson(route('cars.view-stats', $car))->assertJson(['people' => 2, 'total' => 3]);
});

it('tracks who is watching right now', function () {
    $car = Car::factory()->create();

    asVisitor($this, str_repeat('1', 32))->postJson(route('cars.watch', $car))->assertJson(['watching' => 1]);
    asVisitor($this, str_repeat('2', 32))->postJson(route('cars.watch', $car))->assertJson(['watching' => 2]);
    asVisitor($this, str_repeat('2', 32))->post(route('cars.leave', $car))->assertNoContent();
    asVisitor($this, str_repeat('1', 32))->postJson(route('cars.watch', $car))->assertJson(['watching' => 1]);
});

it('ranks cars on the most watched page', function () {
    $a = Car::factory()->create(['name' => 'Less watched']);
    $b = Car::factory()->create(['name' => 'Most watched car']);
    foreach (['1', '2', '3'] as $v) {
        CarView::create(['car_id' => $b->id, 'visitor_id' => str_repeat($v, 32), 'viewed_at' => now()]);
    }
    CarView::create(['car_id' => $a->id, 'visitor_id' => str_repeat('9', 32), 'viewed_at' => now()]);

    $this->get(route('most-watched'))->assertOk()->assertSeeInOrder(['Most watched car', 'Less watched']);
    $this->getJson(route('most-watched.stats'))->assertJsonPath('cars.0.id', $b->id)->assertJsonPath('cars.0.people', 3);
});
