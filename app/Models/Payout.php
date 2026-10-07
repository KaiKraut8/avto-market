<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

// A withdrawal from the marketplace's balance to its bank account
#[Fillable(['user_id', 'provider', 'provider_id', 'amount', 'currency', 'status'])]
class Payout extends Model
{
    protected function casts(): array
    {
        return ['amount' => 'decimal:2'];
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'requested' => __('Requested'),
            'initiated', 'processing-at-bank' => __('On its way'),
            'completed' => __('Paid out'),
            'failed' => __('Failed'),
            'canceled' => __('Cancelled'),
            default => $this->status,
        };
    }
}
