<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A premium seller's special deal: a lower price until ends_at, and optionally an even lower one for premium buyers.
// regular_price is the car's price when the deal started, so the crossed-out price can't be inflated afterwards.
#[Fillable(['regular_price', 'deal_price', 'member_price', 'ends_at'])]
class CarDeal extends Model
{
    public const DURATIONS = [3, 7, 14];   // days a seller can choose

    protected function casts(): array
    {
        return [
            'regular_price' => 'decimal:2',
            'deal_price' => 'decimal:2',
            'member_price' => 'decimal:2',
            'ends_at' => 'datetime',
        ];
    }

    public function car(): BelongsTo
    {
        return $this->belongsTo(Car::class);
    }

    public function scopeActive(Builder $query): void
    {
        $query->where('car_deals.ends_at', '>', now());
    }

    // Saving in whole percent, e.g. 12 for 41.000 € → 36.080 €
    public function percentOff(?float $price = null): int
    {
        $price ??= (float) $this->deal_price;

        return (int) round(100 * (1 - $price / (float) $this->regular_price));
    }

    // The price this viewer pays: the member price for premium buyers, otherwise the deal price
    public function priceFor(?User $user): float
    {
        return $this->member_price !== null && $user?->hasBuyerPremium()
            ? (float) $this->member_price
            : (float) $this->deal_price;
    }
}
