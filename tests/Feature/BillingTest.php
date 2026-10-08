<?php

use App\Models\Car;
use App\Models\Payment;
use App\Models\Subscription;
use App\Models\User;
use App\Services\Billing;
use App\Services\Payments\MollieGateway;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\TestGateway;
use App\Services\Pricing;
use Illuminate\Support\Facades\Http;

// Pays a checkout with the test gateway: choose a method, "pay" on the test page, come back
function payCheckout($test, User $user, array $order, string $method = 'creditcard', string $outcome = 'paid'): Payment
{
    $test->actingAs($user)->post(route('checkout.store'), $order + ['method' => $method]);
    $payment = Payment::latest('id')->first();
    $test->actingAs($user)->post(route('checkout.test.complete', $payment), ['outcome' => $outcome])->assertRedirect(route('checkout.return', $payment));
    $test->actingAs($user)->get(route('checkout.return', $payment));

    return $payment->fresh();
}

it('shows the checkout with card, PayPal and paysafecard', function () {
    $this->actingAs(User::factory()->create())->get(route('checkout.create', ['product' => 'seller', 'billing' => 'yearly']))
        ->assertOk()->assertSee('326,40 €')->assertSee('Credit or debit card')->assertSee('PayPal')->assertSee('paysafecard')
        ->assertSee('Renews automatically every year');
});

it('offers 3 months with 14 % off, renewing every 3 months', function () {
    expect(Pricing::price('seller', 'quarterly'))->toBe(115.04)   // 3 × 44,59 = 133,77, minus 14 %
        ->and(Pricing::price('buyer', 'quarterly'))->toBe(12.87);  // 3 × 4,99 = 14,97, minus 14 %

    $this->get('/premium')->assertSee('Offer')->assertSee('115,04 €')->assertSee('12,87 €')->assertSee('Save 14%');

    $user = User::factory()->create();
    $this->actingAs($user)->get(route('checkout.create', ['product' => 'seller', 'billing' => 'quarterly']))
        ->assertOk()->assertSee('115,04 €')->assertSee('Renews automatically every 3 months');

    $sub = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'quarterly'])->subscription;
    expect($sub->plan)->toBe('quarterly')->and((float) $sub->payments->first()->amount)->toBe(115.04)
        ->and($sub->current_period_end->isSameDay(now()->addMonths(3)))->toBeTrue()
        ->and($user->fresh()->premium_plan)->toBe('quarterly');

    $this->travelTo($sub->current_period_end->copy()->subHours(2));
    app(Billing::class)->renewDue();
    expect($sub->fresh()->current_period_end->isSameDay(now()->addHours(2)->addMonths(3)))->toBeTrue();
});

it('switches premium on only once the payment is paid', function () {
    $user = User::factory()->create();

    $failed = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'monthly'], 'creditcard', 'failed');
    expect($failed->status)->toBe('failed')->and($user->fresh()->hasPremium())->toBeFalse()
        ->and($failed->subscription->status)->toBe('ended');

    $paid = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'monthly']);
    $user->refresh();
    $sub = $paid->subscription;
    expect($paid->status)->toBe('paid')->and($user->hasPremium())->toBeTrue()
        ->and($sub->status)->toBe('active')->and($sub->renewsAutomatically())->toBeTrue()
        ->and($user->premium_until->equalTo($sub->current_period_end))->toBeTrue()
        ->and($sub->current_period_end->isSameDay(now()->addMonth()))->toBeTrue();

    // coming back to the return page again doesn't add another month
    $this->actingAs($user)->get(route('checkout.return', $paid));
    expect($sub->fresh()->current_period_end->isSameDay(now()->addMonth()))->toBeTrue();

    // and a second subscription of the same kind can't be started
    $this->actingAs($user)->get(route('checkout.create', ['product' => 'seller']))->assertRedirect(route('account'));
});

it('renews automatically until cancelled, then lets premium run out', function () {
    $user = User::factory()->create();
    $sub = payCheckout($this, $user, ['product' => 'buyer', 'billing' => 'monthly'], 'paypal')->subscription;
    $billing = app(Billing::class);

    $this->travelTo($sub->current_period_end->copy()->subHours(2));
    expect($billing->renewDue()['charged'])->toBe(1);
    $sub->refresh();
    expect($sub->current_period_end->isSameDay(now()->addMonth()->addHours(2)))->toBeTrue()
        ->and($user->fresh()->hasBuyerPremium())->toBeTrue()
        ->and($sub->payments()->where('purpose', 'renewal')->where('status', 'paid')->count())->toBe(1);
    expect($billing->renewDue()['charged'])->toBe(0);   // nothing due any more

    // cancel: confirmation page first, then premium stays until the end of the period and isn't charged again
    $this->actingAs($user)->get(route('subscriptions.cancel.confirm', $sub))->assertOk()->assertSee('Yes, cancel the subscription');
    $this->actingAs($user)->post(route('subscriptions.cancel', $sub))->assertRedirect(route('account'));
    $this->travelTo($sub->current_period_end->copy()->subHour());
    expect($billing->renewDue()['charged'])->toBe(0)->and($user->fresh()->hasBuyerPremium())->toBeTrue();

    $this->travelTo($sub->current_period_end->copy()->addMinute());
    expect($billing->renewDue()['ended'])->toBe(1)
        ->and($sub->fresh()->status)->toBe('ended')
        ->and($user->fresh()->hasBuyerPremium())->toBeFalse();
});

it('can resume a cancelled subscription before it ends', function () {
    $user = User::factory()->create();
    $sub = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'yearly'])->subscription;

    $this->actingAs($user)->post(route('subscriptions.cancel', $sub));
    expect($sub->fresh()->cancel_at_period_end)->toBeTrue();
    $this->actingAs($user)->post(route('subscriptions.resume', $sub));
    expect($sub->fresh()->willRenew())->toBeTrue();

    $this->actingAs(User::factory()->create())->post(route('subscriptions.cancel', $sub))->assertNotFound();   // not theirs
});

it('reminds paysafecard customers instead of charging them', function () {
    $user = User::factory()->create();
    $sub = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'monthly'], 'paysafecard')->subscription;
    expect($sub->renewsAutomatically())->toBeFalse();

    $this->travelTo($sub->current_period_end->copy()->subDays(2));
    $done = app(Billing::class)->renewDue();
    expect($done['charged'])->toBe(0)->and($done['reminded'])->toBe(1)
        ->and($user->notifications()->first()->type)->toBe('renewal_due');
    expect(app(Billing::class)->renewDue()['reminded'])->toBe(0);   // once

    // paying the next period by hand extends it
    payCheckout($this, $user, ['product' => 'renew', 'subscription' => $sub->id], 'paysafecard');
    expect($sub->fresh()->current_period_end->isSameDay(now()->addDays(2)->addMonth()))->toBeTrue();
});

it('marks a failed renewal and tells the customer', function () {
    $user = User::factory()->create();
    $sub = payCheckout($this, $user, ['product' => 'seller', 'billing' => 'monthly'])->subscription;
    $renewal = Payment::create(['user_id' => $user->id, 'subscription_id' => $sub->id, 'purpose' => 'renewal', 'amount' => 44.59,
        'method' => 'creditcard', 'description' => 'renewal', 'provider' => 'test']);
    $renewal->forceFill(['provider_id' => 'tst_fail'])->save();
    TestGateway::setStatus('tst_fail', 'failed');

    app(Billing::class)->sync($renewal);
    expect($sub->fresh()->status)->toBe('past_due')->and($sub->fresh()->failed_renewals)->toBe(1)
        ->and($user->notifications()->first()->type)->toBe('payment_failed');
});

it('pushes a car forward once the push is paid', function () {
    $owner = User::factory()->create();
    $car = Car::factory()->for($owner)->create();

    $this->actingAs(User::factory()->create())->get(route('checkout.create', ['product' => 'boost', 'car' => $car->id]))->assertForbidden();
    $payment = payCheckout($this, $owner, ['product' => 'boost', 'car' => $car->id], 'paysafecard');
    expect($payment->status)->toBe('paid')->and($car->fresh()->isBoosted())->toBeTrue()
        ->and((float) $payment->amount)->toBe(6.99);
});

it('keeps other people away from a payment', function () {
    $user = User::factory()->create();
    $this->actingAs($user)->post(route('checkout.store'), ['product' => 'seller', 'billing' => 'monthly', 'method' => 'creditcard']);
    $payment = Payment::first();

    $stranger = User::factory()->create();
    $this->actingAs($stranger)->get(route('checkout.test', $payment))->assertNotFound();
    $this->actingAs($stranger)->post(route('checkout.test.complete', $payment), ['outcome' => 'paid'])->assertNotFound();
    $this->actingAs($stranger)->get(route('checkout.return', $payment))->assertNotFound();
    $this->actingAs($user)->post(route('checkout.store'), ['product' => 'seller', 'method' => 'bitcoin'])->assertSessionHasErrors('method');
});

it('talks to Mollie: first payment with a mandate, then a recurring charge', function () {
    config(['payments.driver' => 'mollie', 'payments.mollie.key' => 'test_abc']);
    app()->forgetInstance(PaymentGateway::class);
    expect(app(PaymentGateway::class))->toBeInstanceOf(MollieGateway::class);

    Http::fake([
        'api.mollie.com/v2/customers' => Http::response(['id' => 'cst_1']),
        'api.mollie.com/v2/payments/tr_first' => Http::response(['id' => 'tr_first', 'status' => 'paid', 'customerId' => 'cst_1', 'mandateId' => 'mdt_1']),
        'api.mollie.com/v2/payments/tr_again' => Http::response(['id' => 'tr_again', 'status' => 'paid', 'customerId' => 'cst_1', 'mandateId' => 'mdt_1']),
        'api.mollie.com/v2/payments' => Http::sequence()
            ->push(['id' => 'tr_first', 'status' => 'open', '_links' => ['checkout' => ['href' => 'https://www.mollie.com/checkout/tr_first']]])
            ->push(['id' => 'tr_again', 'status' => 'paid', '_links' => []]),
    ]);

    $user = User::factory()->create();
    $this->actingAs($user)->post(route('checkout.store'), ['product' => 'seller', 'billing' => 'monthly', 'method' => 'creditcard'])
        ->assertRedirect('https://www.mollie.com/checkout/tr_first');
    Http::assertSent(fn ($r) => $r->url() === 'https://api.mollie.com/v2/payments' && $r['sequenceType'] === 'first'
        && $r['amount'] === ['currency' => 'EUR', 'value' => '44.59'] && $r['customerId'] === 'cst_1' && $r['method'] === 'creditcard'
        && $r->hasHeader('Authorization', 'Bearer test_abc'));

    // Mollie's webhook only sends the id; the status comes from Mollie itself
    $this->post(route('webhooks.mollie'), ['id' => 'tr_first'])->assertOk();
    $sub = Subscription::first();
    expect($sub->status)->toBe('active')->and($sub->provider_mandate_id)->toBe('mdt_1')->and($user->fresh()->hasPremium())->toBeTrue();
    $this->post(route('webhooks.mollie'), ['id' => 'tr_unknown'])->assertOk();

    $this->travelTo($sub->current_period_end->copy()->subHour());
    app(Billing::class)->renewDue();
    Http::assertSent(fn ($r) => $r->url() === 'https://api.mollie.com/v2/payments' && ($r['sequenceType'] ?? null) === 'recurring' && $r['mandateId'] === 'mdt_1');
    expect($sub->fresh()->current_period_end->isAfter(now()->addWeeks(4)))->toBeTrue();
});

it('refuses test payments on a production site', function () {
    config(['payments.driver' => 'test']);
    app()->forgetInstance(PaymentGateway::class);
    app()->detectEnvironment(fn () => 'production');

    expect(fn () => app(PaymentGateway::class))->toThrow(RuntimeException::class);
});
