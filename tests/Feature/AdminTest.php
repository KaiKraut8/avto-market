<?php

use App\Models\Car;
use App\Models\Payment;
use App\Models\Payout;
use App\Models\User;
use App\Services\Payments\Treasury;
use Illuminate\Support\Facades\Http;

function paidTestPayment(User $user, float $amount): Payment
{
    $p = Payment::create(['user_id' => $user->id, 'purpose' => 'boost', 'amount' => $amount, 'method' => 'paysafecard',
        'description' => 'test', 'provider' => 'test']);
    $p->forceFill(['status' => 'paid', 'paid_at' => now()])->save();

    return $p;
}

it('makes exactly one admin, only from the command line', function () {
    $first = User::factory()->create(['email' => 'first@example.com']);
    $this->artisan('users:admin', ['email' => 'first@example.com'])->assertSuccessful();
    $this->artisan('users:admin', ['email' => 'boss@example.com'])->assertFailed();   // no such account
    $this->artisan('users:admin', ['email' => 'Boss@Example.com', '--create' => true])->assertSuccessful();

    expect(User::where('is_admin', true)->pluck('email')->all())->toBe(['boss@example.com'])
        ->and($first->fresh()->isAdmin())->toBeFalse();

    // signing up or editing the profile can't make anyone admin
    $this->post('/register', ['name' => 'Eve Sneaky', 'email' => 'eve@example.com', 'phone' => '040 123 456', 'password' => 'password123',
        'password_confirmation' => 'password123', 'location' => 'Celje', 'country' => 'Slovenia', 'is_admin' => 1]);
    expect(User::where('email', 'eve@example.com')->first()->isAdmin())->toBeFalse();
});

it('keeps the admin pages for the admin', function () {
    $this->get(route('admin.dashboard'))->assertRedirect('/login');
    $this->actingAs(User::factory()->create())->get(route('admin.dashboard'))->assertForbidden();
    $this->actingAs(User::factory()->create())->post(route('admin.withdraw'), ['confirm' => 1])->assertForbidden();

    $admin = User::factory()->create(['is_admin' => true]);
    paidTestPayment(User::factory()->create(), 6.99);
    $this->actingAs($admin)->get(route('admin.dashboard'))->assertOk()->assertSee('6,99 €')->assertSee('Withdraw to bank');
    $this->actingAs($admin)->get(route('home'))->assertSee(route('admin.dashboard'));
    $this->actingAs(User::factory()->create())->get(route('home'))->assertDontSee(route('admin.dashboard'));
});

it('lets only the admin manage cars that have no seller', function () {
    $car = Car::factory()->unowned()->create();

    $this->actingAs(User::factory()->create())->delete(route('cars.destroy', $car))->assertForbidden();
    $this->actingAs(User::factory()->create(['is_admin' => true]))->put(route('cars.update', $car), [
        'name' => 'Fixed', 'location' => 'Ljubljana', 'country' => 'Slovenia',
    ])->assertRedirect(route('cars.show', $car));
    expect($car->fresh()->name)->toBe('Fixed');
});

it('withdraws only after the password is confirmed, and not more than the balance', function () {
    $admin = User::factory()->create(['is_admin' => true, 'password' => 'admin-pass-1']);
    paidTestPayment(User::factory()->create(), 44.59);

    $this->actingAs($admin)->get(route('admin.withdraw.create'))->assertRedirect(route('password.confirm'));
    $this->actingAs($admin)->post(route('password.confirm.store'), ['password' => 'wrong'])->assertSessionHasErrors();

    $session = ['auth.password_confirmed_at' => time()];
    $this->actingAs($admin)->withSession($session)->get(route('admin.withdraw.create'))->assertOk()->assertSee('44,59 €');
    $this->actingAs($admin)->withSession($session)->post(route('admin.withdraw'), ['amount' => '50'])->assertSessionHasErrors('confirm');
    $this->actingAs($admin)->withSession($session)->post(route('admin.withdraw'), ['amount' => '50', 'confirm' => 1])->assertSessionHas('error');
    $this->actingAs($admin)->withSession($session)->post(route('admin.withdraw'), ['amount' => '40,00', 'confirm' => 1])->assertRedirect(route('admin.dashboard'));

    expect((float) Payout::first()->amount)->toBe(40.0)
        ->and(app(Treasury::class)->balance()['available'])->toBe(4.59);
});

it('asks Mollie for the balance and the payout', function () {
    config(['payments.driver' => 'mollie', 'payments.mollie.key' => 'live_x', 'payments.mollie.access_token' => 'access_y']);
    app()->forgetInstance(Treasury::class);
    Http::fake([
        'api.mollie.com/v2/balances/primary' => Http::response([
            'id' => 'bal_1', 'currency' => 'EUR', 'availableAmount' => ['value' => '120.00', 'currency' => 'EUR'],
            'pendingAmount' => ['value' => '10.00', 'currency' => 'EUR'], 'transferFrequency' => 'daily',
            'transferDestination' => ['type' => 'bank-account', 'beneficiaryName' => 'KAI Garage d.o.o.', 'bankAccount' => 'SI56 1910 0000 0123 438'],
        ]),
        'api.mollie.com/v2/payouts*' => Http::sequence()
            ->push(['_embedded' => ['payouts' => []]])
            ->push(['id' => 'payout_1', 'status' => 'requested', 'amount' => ['value' => '100.00', 'currency' => 'EUR']]),
    ]);
    $admin = User::factory()->create(['is_admin' => true]);

    $this->actingAs($admin)->get(route('admin.dashboard'))->assertSee('120,00 €')->assertSee('SI56 1910 0000 0123 438');
    $this->actingAs($admin)->withSession(['auth.password_confirmed_at' => time()])
        ->post(route('admin.withdraw'), ['amount' => '100', 'confirm' => 1])->assertRedirect(route('admin.dashboard'));

    Http::assertSent(fn ($r) => $r->method() === 'POST' && $r->url() === 'https://api.mollie.com/v2/payouts'
        && $r['balanceId'] === 'bal_1' && $r['amount'] === ['currency' => 'EUR', 'value' => '100.00'] && $r->hasHeader('Authorization', 'Bearer access_y'));
    expect(Payout::first()->provider_id)->toBe('payout_1');
});
