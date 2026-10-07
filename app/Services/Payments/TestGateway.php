<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

// Stand-in for Mollie while there is no API key: the "checkout" is a local page where you choose
// whether the payment succeeds. Renewals succeed straight away, as a saved card would.
class TestGateway implements PaymentGateway
{
    public function name(): string
    {
        return 'test';
    }

    public function createCustomer(User $user): string
    {
        return 'tst_cst_'.Str::random(10);
    }

    public function create(Payment $payment, string $sequence, array $options): array
    {
        $id = 'tst_'.Str::random(16);
        $status = $sequence === 'recurring' ? 'paid' : 'open';
        self::setStatus($id, $status);

        return [
            'id' => $id,
            'checkout_url' => $sequence === 'recurring' ? null : route('checkout.test', $payment),
            'status' => $status,
        ];
    }

    public function fetch(Payment $payment): array
    {
        $status = Cache::get('test-payment:'.$payment->provider_id, 'expired');

        return [
            'status' => $status,
            'customer_id' => $payment->subscription?->provider_customer_id,
            'mandate_id' => $status === 'paid' && in_array($payment->method, config('payments.recurring'), true) ? 'tst_mdt_'.$payment->id : null,
        ];
    }

    // The test checkout page records the choice made there
    public static function setStatus(string $id, string $status): void
    {
        Cache::put('test-payment:'.$id, $status, now()->addDays(2));
    }
}
