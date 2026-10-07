<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// A car changing hands, and the marketplace's commission on it.
//  site:    pending (buyer is paying the commission) → reserved → completed, or canceled (commission refunded)
//  offline: due (seller owes the commission) → paid
#[Fillable(['car_id', 'seller_id', 'buyer_id', 'via', 'status', 'price', 'rate', 'commission'])]
class CarSale extends Model
{
    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'rate' => 'decimal:2',
            'commission' => 'decimal:2',
            'completed_at' => 'datetime',
            'canceled_at' => 'datetime',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class)->withTrashed();
    }

    public function seller(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seller_id');
    }

    public function buyer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'buyer_id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    // What the buyer still pays the seller at handover
    public function remainder(): float
    {
        return round((float) $this->price - (float) $this->commission, 2);
    }

    public static function rate(): float
    {
        return (float) config('pricing.commission_rate');
    }

    public static function commissionFor(float $price): float
    {
        return round($price * self::rate() / 100, 2);
    }
}
