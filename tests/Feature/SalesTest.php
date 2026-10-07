<?php

use App\Models\Car;
use App\Models\CarSale;
use App\Models\Payment;
use App\Models\User;
use App\Services\Billing;
use App\Services\Payments\TestGateway;

// A buyer pays the commission at the checkout (test gateway) and comes back
function reserveCar($test, User $buyer, Car $car, string $method = 'creditcard', string $outcome = 'paid'): Payment
{
    $test->actingAs($buyer)->post(route('checkout.store'), ['product' => 'reserve', 'car' => $car->id, 'method' => $method, 'terms' => 1]);
    $payment = Payment::latest('id')->first();
    $test->actingAs($buyer)->post(route('checkout.test.complete', $payment), ['outcome' => $outcome]);
    $test->actingAs($buyer)->get(route('checkout.return', $payment));

    return $payment->fresh();
}

it('explains the commission before anyone buys or sells', function () {
    $this->get(route('how-buying'))->assertOk()->assertSee('1.000,00 €')->assertSee('19.000 €');   // 5 % of 20.000 €

    $car = Car::factory()->create(['price' => 30000]);
    $this->get(route('cars.show', $car))->assertSee('Buy this car')->assertSee('1.500,00 €')->assertSee('28.500 €');

    $this->actingAs(User::factory()->create())->get(route('cars.create'))->assertSee('Selling costs 5% of the price');
    $this->actingAs(User::factory()->create())->post(route('cars.store'), ['name' => 'X', 'location' => 'Celje', 'country' => 'Slovenia'])
        ->assertSessionHasErrors('terms');
});

it('reserves a car once the buyer paid 5 %, and sends that to the marketplace', function () {
    $seller = User::factory()->create();
    $car = Car::factory()->for($seller)->create(['price' => 20000]);
    $buyer = User::factory()->create();

    // the rules have to be accepted at the checkout
    $this->actingAs($buyer)->post(route('checkout.store'), ['product' => 'reserve', 'car' => $car->id, 'method' => 'creditcard'])
        ->assertSessionHasErrors('terms');

    $payment = reserveCar($this, $buyer, $car);
    $sale = $payment->sale;
    expect((float) $payment->amount)->toBe(1000.0)->and($payment->status)->toBe('paid')
        ->and($sale->status)->toBe('reserved')->and($sale->remainder())->toBe(19000.0)
        ->and($seller->notifications()->first()->type)->toBe('car_reserved')
        ->and($buyer->notifications()->first()->type)->toBe('reservation_paid');

    // nobody else can buy it now, and the seller sees the buyer's details
    $this->actingAs(User::factory()->create())->get(route('checkout.create', ['product' => 'reserve', 'car' => $car->id]))->assertRedirect(route('cars.show', $car));
    $this->actingAs($seller)->get(route('cars.show', $car))->assertSee($buyer->phone)->assertSee('Confirm the sale');

    $this->actingAs($buyer)->post(route('sales.complete', $sale))->assertForbidden();   // only the seller
    $this->actingAs($seller)->post(route('sales.complete', $sale))->assertRedirect(route('cars.show', $car));
    expect($car->fresh()->isSold())->toBeTrue()->and($sale->fresh()->status)->toBe('completed');
    $this->get(route('cars.index'))->assertDontSee($car->name);   // sold cars leave the lists
    $this->get(route('cars.show', $car))->assertOk()->assertSee('Sold');
});

it('refunds the buyer when the seller cancels, and the car is for sale again', function () {
    $seller = User::factory()->create();
    $car = Car::factory()->for($seller)->create(['price' => 20000]);
    $buyer = User::factory()->create();
    $payment = reserveCar($this, $buyer, $car);

    $this->actingAs($seller)->post(route('sales.cancel', $payment->sale));
    expect($payment->fresh()->status)->toBe('refunded')
        ->and($payment->sale->fresh()->status)->toBe('canceled')
        ->and($car->fresh()->load('activeSale')->isBuyable())->toBeTrue()
        ->and($buyer->notifications()->where('type', 'sale_canceled')->exists())->toBeTrue();
});

it('does not let sellers buy their own car or buy cars without a price', function () {
    $seller = User::factory()->create();
    $own = Car::factory()->for($seller)->create(['price' => 10000]);
    $this->actingAs($seller)->get(route('checkout.create', ['product' => 'reserve', 'car' => $own->id]))->assertRedirect(route('cars.show', $own));

    $onRequest = Car::factory()->create(['price' => null]);
    $this->actingAs(User::factory()->create())->get(route('checkout.create', ['product' => 'reserve', 'car' => $onRequest->id]))->assertRedirect(route('cars.show', $onRequest));
});

it('refunds the second of two buyers who paid at the same moment', function () {
    $car = Car::factory()->create(['price' => 10000]);
    [$first, $second] = User::factory()->count(2)->create();
    $this->actingAs($first)->post(route('checkout.store'), ['product' => 'reserve', 'car' => $car->id, 'method' => 'creditcard', 'terms' => 1]);
    $this->actingAs($second)->post(route('checkout.store'), ['product' => 'reserve', 'car' => $car->id, 'method' => 'creditcard', 'terms' => 1]);
    [$p1, $p2] = Payment::orderBy('id')->get();
    TestGateway::setStatus($p1->provider_id, 'paid');
    TestGateway::setStatus($p2->provider_id, 'paid');

    app(Billing::class)->sync($p1);
    app(Billing::class)->sync($p2);
    expect($p1->sale->fresh()->status)->toBe('reserved')
        ->and($p2->fresh()->status)->toBe('refunded')->and($p2->sale->fresh()->status)->toBe('canceled');
});

it('makes a seller who sold elsewhere pay 5 % before listing again', function () {
    $seller = User::factory()->create();
    $car = Car::factory()->for($seller)->create(['price' => 12000]);

    $this->actingAs($seller)->post(route('cars.sold', $car), ['price' => '11.000'])->assertSessionHasErrors('terms');
    $this->actingAs($seller)->post(route('cars.sold', $car), ['price' => '11.000', 'terms' => 1]);
    $sale = CarSale::first();
    expect($sale->status)->toBe('due')->and((float) $sale->commission)->toBe(550.0)->and($car->fresh()->isSold())->toBeTrue();

    $this->actingAs($seller)->get(route('cars.create'))->assertRedirect(route('checkout.create', ['product' => 'commission', 'sale' => $sale->id]));

    $this->actingAs($seller)->post(route('checkout.store'), ['product' => 'commission', 'sale' => $sale->id, 'method' => 'paysafecard', 'terms' => 1]);
    $payment = Payment::latest('id')->first();
    $this->actingAs($seller)->post(route('checkout.test.complete', $payment), ['outcome' => 'paid']);
    $this->actingAs($seller)->get(route('checkout.return', $payment));
    expect($sale->fresh()->status)->toBe('paid');
    $this->actingAs($seller)->get(route('cars.create'))->assertOk();

    $this->actingAs(User::factory()->create())->post(route('checkout.store'), ['product' => 'commission', 'sale' => $sale->id, 'method' => 'creditcard', 'terms' => 1])->assertNotFound();
});

it('offers paysafecard only up to its limit', function () {
    $buyer = User::factory()->create();
    $cheap = Car::factory()->create(['price' => 10000]);    // 500 €
    $dear = Car::factory()->create(['price' => 40000]);     // 2.000 €

    $this->actingAs($buyer)->get(route('checkout.create', ['product' => 'reserve', 'car' => $cheap->id]))->assertSee('Prepaid voucher');
    $this->actingAs($buyer)->get(route('checkout.create', ['product' => 'reserve', 'car' => $dear->id]))->assertDontSee('Prepaid voucher');
    $this->actingAs($buyer)->post(route('checkout.store'), ['product' => 'reserve', 'car' => $dear->id, 'method' => 'paysafecard', 'terms' => 1])
        ->assertSessionHasErrors('method');
});

it('counts commissions in the admin earnings', function () {
    $car = Car::factory()->create(['price' => 20000]);
    reserveCar($this, User::factory()->create(), $car);

    $this->actingAs(User::factory()->create(['is_admin' => true]))->get(route('admin.dashboard'))
        ->assertSee('Sales commission')->assertSee('1.000,00 €')->assertSee('Open sales');
});
