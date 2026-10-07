<?php

namespace App\Services\Payments;

// The marketplace's money at the payment provider: the balance customers' payments land on,
// the bank account it is paid out to, and withdrawals (payouts) to that bank account.
interface Treasury
{
    /** @return array{available:float, pending:float, currency:string, frequency:?string, destination:?array{name:?string, account:?string}} */
    public function balance(): array;

    /** Withdraw to the bank account; null amount = everything available. @return array{id:string, status:string, amount:float} */
    public function payout(?float $amount): array;

    /** @return array<int, array{id:string, status:string, amount:float, created_at:string}> */
    public function payouts(): array;
}
