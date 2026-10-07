<?php

namespace App\Services\Payments;

use App\Models\Payment;
use App\Models\Payout;
use Illuminate\Support\Str;

// Stand-in while Mollie isn't connected: the balance is what test payments brought in minus test withdrawals.
// No money exists or moves.
class TestTreasury implements Treasury
{
    public function balance(): array
    {
        $in = (float) Payment::where('provider', 'test')->where('status', 'paid')->sum('amount');
        $out = (float) Payout::where('provider', 'test')->whereNotIn('status', ['failed', 'canceled'])->sum('amount');

        return [
            'id' => 'test_balance',
            'available' => round(max(0, $in - $out), 2),
            'pending' => 0.0,
            'currency' => 'EUR',
            'frequency' => null,
            'destination' => ['name' => config('company.name'), 'account' => 'SI56 0000 0000 0000 000 (test)'],
        ];
    }

    public function payout(?float $amount): array
    {
        return ['id' => 'tst_payout_'.Str::random(12), 'status' => 'completed', 'amount' => $amount ?? $this->balance()['available']];
    }

    public function payouts(): array
    {
        return Payout::where('provider', 'test')->latest('id')->limit(20)->get()
            ->map(fn (Payout $p) => ['id' => $p->provider_id, 'status' => $p->status, 'amount' => (float) $p->amount, 'created_at' => $p->created_at->toIso8601String()])
            ->all();
    }
}
