<?php

namespace App\Models;

use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

#[Fillable(['name', 'email', 'password', 'phone', 'location', 'country'])]
#[Hidden(['password', 'remember_token'])]
class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, Notifiable;

    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'premium_since' => 'datetime',
            'premium_until' => 'datetime',
            'buyer_premium_since' => 'datetime',
            'buyer_premium_until' => 'datetime',
        ];
    }

    public function cars(): HasMany
    {
        return $this->hasMany(Car::class);
    }

    public function savedSearches(): HasMany
    {
        return $this->hasMany(SavedSearch::class)->orderBy('id');
    }

    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class)->latest('id');
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    // The running subscription of a kind (seller or buyer), if any
    public function currentSubscription(string $kind): ?Subscription
    {
        return $this->subscriptions()->where('kind', $kind)->current()->first();
    }

    public function wishlistItems(): HasMany
    {
        return $this->hasMany(WishlistItem::class);
    }

    // A premium seller account that hasn't run out
    public function hasPremium(): bool
    {
        return (bool) $this->premium_until?->isFuture();
    }

    // Premium for buyers: member prices, deal alerts and saved-search alerts
    public function hasBuyerPremium(): bool
    {
        return (bool) $this->buyer_premium_until?->isFuture();
    }

    public function firstName(): string
    {
        return explode(' ', $this->name)[0];
    }

    public function initial(): string
    {
        return mb_strtoupper(mb_substr($this->name, 0, 1));
    }
}
