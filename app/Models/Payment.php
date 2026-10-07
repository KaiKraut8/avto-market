<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// One charge at the payment provider: the first payment of a subscription, a renewal, or a push forward
#[Fillable(['user_id', 'subscription_id', 'car_id', 'purpose', 'amount', 'method', 'description', 'provider'])]
class Payment extends Model
{
    public const FINAL = ['paid', 'failed', 'canceled', 'expired'];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'paid_at' => 'datetime',
            'applied_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    public function isFinal(): bool
    {
        return in_array($this->status, self::FINAL, true);
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            'paid' => __('Paid'),
            'failed' => __('Failed'),
            'canceled' => __('Cancelled'),
            'expired' => __('Expired'),
            default => __('Processing'),
        };
    }

    public static function methodName(string $method): string
    {
        return match ($method) {
            'creditcard' => __('Card'),
            'paypal' => 'PayPal',
            'paysafecard' => 'paysafecard',
            default => $method,
        };
    }
}
