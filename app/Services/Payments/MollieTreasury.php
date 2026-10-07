<?php

namespace App\Services\Payments;

use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;

// Mollie's Balances and Payouts APIs. Reading the balance needs an access token (Mollie dashboard →
// Developers → Access tokens) with the scopes balances.read, payouts.read and payouts.write.
// Money is only ever sent to the bank account verified in the Mollie dashboard; it can't be changed from here.
class MollieTreasury implements Treasury
{
    public function __construct(private string $token, private string $api) {}

    private function http(): PendingRequest
    {
        return Http::withToken($this->token)->acceptJson()->asJson()->timeout(20)->throw();
    }

    public function balance(): array
    {
        $b = $this->http()->get($this->api.'/balances/primary')->json();

        return [
            'id' => $b['id'],
            'available' => (float) ($b['availableAmount']['value'] ?? 0),
            'pending' => (float) ($b['pendingAmount']['value'] ?? 0),
            'currency' => $b['currency'] ?? 'EUR',
            'frequency' => $b['transferFrequency'] ?? null,
            'destination' => isset($b['transferDestination']) ? [
                'name' => $b['transferDestination']['beneficiaryName'] ?? null,
                'account' => $b['transferDestination']['bankAccount'] ?? null,
            ] : null,
        ];
    }

    public function payout(?float $amount): array
    {
        $body = ['balanceId' => $this->balance()['id'], 'description' => config('app.name').' payout'];
        if ($amount !== null) {
            $body['amount'] = ['currency' => 'EUR', 'value' => number_format($amount, 2, '.', '')];
        }
        $p = $this->http()->post($this->api.'/payouts', $body)->json();

        return ['id' => $p['id'], 'status' => $p['status'], 'amount' => (float) $p['amount']['value']];
    }

    public function payouts(): array
    {
        $list = $this->http()->get($this->api.'/payouts', ['limit' => 20])->json('_embedded.payouts') ?? [];

        return array_map(fn ($p) => [
            'id' => $p['id'], 'status' => $p['status'], 'amount' => (float) $p['amount']['value'], 'created_at' => $p['createdAt'],
        ], $list);
    }
}
