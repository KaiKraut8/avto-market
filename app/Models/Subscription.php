<?php

namespace App\Models;

use App\Services\Pricing;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

// A premium plan (seller or buyer) that renews every month or year until it is cancelled
#[Fillable(['kind', 'plan', 'method', 'status'])]
class Subscription extends Model
{
    protected function casts(): array
    {
        return [
            'current_period_end' => 'datetime',
            'canceled_at' => 'datetime',
            'reminded_at' => 'datetime',
            'cancel_at_period_end' => 'boolean',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function payments(): HasMany
    {
        return $this->hasMany(Payment::class)->latest('id');
    }

    // Running or waiting for a renewal payment: the one the customer can cancel or resume
    public function scopeCurrent(Builder $query): void
    {
        $query->whereIn('status', ['active', 'past_due']);
    }

    public function price(): float
    {
        return self::priceFor($this->kind, $this->plan);
    }

    public static function priceFor(string $kind, string $plan): float
    {
        return match ([$kind, $plan]) {
            ['seller', 'yearly'] => Pricing::yearly(),
            ['buyer', 'monthly'] => Pricing::buyerMonthly(),
            ['buyer', 'yearly'] => Pricing::buyerYearly(),
            default => Pricing::monthly(),
        };
    }

    public function months(): int
    {
        return $this->plan === 'yearly' ? 12 : 1;
    }

    // Cards and PayPal are charged again automatically; paysafecard is paid by hand each period
    public function renewsAutomatically(): bool
    {
        return in_array($this->method, config('payments.recurring'), true) && $this->provider_mandate_id !== null;
    }

    public function willRenew(): bool
    {
        return in_array($this->status, ['active', 'past_due'], true) && ! $this->cancel_at_period_end;
    }

    public function label(): string
    {
        return $this->kind === 'buyer' ? __('Premium buyer') : __('Premium seller');
    }
}
