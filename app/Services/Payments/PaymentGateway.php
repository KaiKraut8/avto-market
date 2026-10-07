<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\User;

// A payment provider. "first" payments let the customer approve future charges (a mandate),
// "recurring" ones charge that mandate without the customer, "oneoff" ones are single payments.
interface PaymentGateway
{
    public function name(): string;

    public function createCustomer(User $user): string;

    /**
     * @param  'first'|'oneoff'|'recurring'  $sequence
     * @param  array{customer_id?:?string, mandate_id?:?string, redirect_url?:string, webhook_url?:?string}  $options
     * @return array{id:string, checkout_url:?string, status:string}
     */
    public function create(Payment $payment, string $sequence, array $options): array;

    /** @return array{status:string, customer_id:?string, mandate_id:?string} */
    public function fetch(Payment $payment): array;

    // Gives the whole payment back to the customer
    public function refund(Payment $payment): void;
}
