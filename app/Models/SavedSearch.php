<?php

namespace App\Models;

use App\Support\Money;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

// A premium buyer's search ("BMW under 30.000 €"): new cars and deals that match send an alert
#[Fillable(['query', 'max_price'])]
class SavedSearch extends Model
{
    public const LIMIT = 10;   // per account

    protected function casts(): array
    {
        return ['max_price' => 'decimal:2'];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    // Same matching as the search box (name, description, location, country, parts) plus the price limit.
    // $price is what the buyer would pay, so a deal can bring a car under the limit.
    public function matches(Car $car, ?float $price = null): bool
    {
        $price ??= $car->price !== null ? (float) $car->price : null;
        if ($this->max_price !== null && ($price === null || $price > (float) $this->max_price)) {
            return false;
        }

        return $this->query === null || Car::whereKey($car->id)->search($this->query)->exists();
    }

    public function label(): string
    {
        return self::describe($this->query, $this->max_price);
    }

    // “BMW” up to 30.000 €; also used for alerts, which store the search's values rather than its text
    public static function describe(?string $query, float|string|null $maxPrice): string
    {
        $parts = [];
        if ($query !== null && $query !== '') {
            $parts[] = '“'.$query.'”';
        }
        if ($maxPrice !== null) {
            $parts[] = __('up to :price', ['price' => Money::price($maxPrice)]);
        }

        return $parts ? implode(' ', $parts) : __('Any car');
    }
}
