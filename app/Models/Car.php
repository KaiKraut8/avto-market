<?php

namespace App\Models;

use Database\Factories\CarFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Facades\DB;

#[Fillable(['name', 'price', 'description', 'location', 'country'])]
class Car extends Model
{
    /** @use HasFactory<CarFactory> */
    use HasFactory, SoftDeletes;

    protected function casts(): array
    {
        return [
            'price' => 'decimal:2',
            'boosted_until' => 'datetime',
            'legacy_premium_until' => 'datetime',
            'is_premium' => 'boolean',
            'is_boosted' => 'boolean',
        ];
    }

    // ---- relationships ----

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(CarPhoto::class)->orderBy('position')->orderBy('id');
    }

    public function coverPhoto(): HasOne
    {
        return $this->hasOne(CarPhoto::class)->oldestOfMany();
    }

    public function parts(): HasMany
    {
        return $this->hasMany(CarPart::class)->orderBy('id');
    }

    public function views(): HasMany
    {
        return $this->hasMany(CarView::class);
    }

    public function inquiries(): HasMany
    {
        return $this->hasMany(CarInquiry::class);
    }

    public function deals(): HasMany
    {
        return $this->hasMany(CarDeal::class);
    }

    // The special deal running right now, if any
    public function activeDeal(): HasOne
    {
        return $this->hasOne(CarDeal::class)->ofMany(['id' => 'max'], fn (Builder $q) => $q->where('car_deals.ends_at', '>', now()));
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    // First word of the name, lower case: "BMW 530d" and "bmw12" are different words, "BMW" and "bmw x5" share "bmw"
    public function make(): string
    {
        return mb_strtolower(strtok(trim($this->name), ' ') ?: '');
    }

    // ---- premium / push ----

    // "This car is premium right now": its seller's account has premium, or it still has
    // per-car premium carried over from the old site.
    public static function premiumSql(): string
    {
        return '(EXISTS (SELECT 1 FROM users pu WHERE pu.id = cars.user_id AND pu.premium_until > NOW())
                 OR (cars.legacy_premium_until IS NOT NULL AND cars.legacy_premium_until > NOW()))';
    }

    public static function boostedSql(): string
    {
        return '(cars.boosted_until IS NOT NULL AND cars.boosted_until > NOW())';
    }

    // Adds is_premium and is_boosted to the selected columns
    public function scopeWithPlacement(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('cars.*');
        }
        $query->selectRaw(self::premiumSql().' AS is_premium')
            ->selectRaw(self::boostedSql().' AS is_boosted');
    }

    public function scopePremium(Builder $query): void
    {
        $query->whereRaw(self::premiumSql());
    }

    public function scopeNotPremium(Builder $query): void
    {
        $query->whereRaw('NOT '.self::premiumSql());
    }

    // Premium first, then pushed, then the rest (needs withPlacement)
    public function scopeListingOrder(Builder $query): void
    {
        $query->orderByDesc('is_premium')->orderByDesc('is_boosted')->orderBy('cars.id');
    }

    public function isPremium(): bool
    {
        if (array_key_exists('is_premium', $this->attributes)) {
            return (bool) $this->attributes['is_premium'];
        }

        return (bool) $this->user?->hasPremium() || (bool) $this->legacy_premium_until?->isFuture();
    }

    public function isBoosted(): bool
    {
        if (array_key_exists('is_boosted', $this->attributes)) {
            return (bool) $this->attributes['is_boosted'];
        }

        return (bool) $this->boosted_until?->isFuture();
    }

    // ---- search ----

    // Name, description, location, country or part name; the text is matched literally (% and _ included)
    public function scopeSearch(Builder $query, ?string $term): void
    {
        $term = trim((string) $term);
        if ($term === '') {
            return;
        }
        $like = '%'.addcslashes($term, '%_\\').'%';
        $query->where(function (Builder $q) use ($like) {
            $q->where('cars.name', 'like', $like)
                ->orWhere('cars.description', 'like', $like)
                ->orWhere('cars.location', 'like', $like)
                ->orWhere('cars.country', 'like', $like)
                ->orWhereHas('parts', fn (Builder $p) => $p->where('name', 'like', $like));
        });
    }

    // Distinct people who looked at the car (one browser = one person)
    public function scopeWithPeopleCount(Builder $query): void
    {
        if ($query->getQuery()->columns === null) {
            $query->select('cars.*');
        }
        $query->selectSub(
            DB::table('car_views')->selectRaw('COUNT(DISTINCT visitor_id)')->whereColumn('car_views.car_id', 'cars.id'),
            'people'
        );
    }

    // ---- presentation helpers ----

    public function locationLabel(): ?string
    {
        if (! $this->location) {
            return null;
        }

        return $this->location.($this->country ? ', '.$this->country : '');
    }

    // Who buyers contact: the seller's profile, or the details stored on cars from the old site
    public function sellerContact(): ?array
    {
        if ($this->user) {
            return ['name' => $this->user->name, 'phone' => $this->user->phone, 'email' => $this->user->email];
        }
        if ($this->legacy_seller_email && $this->legacy_seller_phone) {
            return ['name' => $this->legacy_seller_name ?: 'Seller', 'phone' => $this->legacy_seller_phone, 'email' => $this->legacy_seller_email];
        }

        return null;
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
