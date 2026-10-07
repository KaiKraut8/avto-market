<?php

namespace App\Services;

use App\Models\Car;
use App\Models\CarDeal;
use App\Models\CarInquiry;
use App\Models\SavedSearch;
use App\Models\User;
use App\Models\WishlistItem;
use App\Notifications\SiteAlert;

// Who hears about what (the bell in the menu):
//  - everyone with an account: a deal on a car in their wishlist
//  - premium buyers: deals on cars like the ones they saved, and new cars or deals matching a saved search
//  - premium sellers: someone saved their car
//  - every seller: a buyer sent their details about a car
// Alerts are sent while the request runs. With many accounts this should move to a queue.
class Alerts
{
    // Cars count as similar when they share the first word of the name (the make)
    // or their prices are within this share of each other
    public const SIMILAR_PRICE = 0.15;

    public function dealStarted(CarDeal $deal): void
    {
        $car = $deal->car;
        $base = [
            'car_id' => $car->id,
            'car_name' => $car->name,
            'regular_price' => (float) $deal->regular_price,
            'ends_at' => $deal->ends_at->format('Y-m-d'),
        ];

        $savers = User::whereHas('wishlistItems', fn ($q) => $q->where('car_id', $car->id))
            ->when($car->user_id, fn ($q) => $q->whereKeyNot($car->user_id))
            ->get();
        foreach ($savers as $user) {
            $user->notify(new SiteAlert('deal_saved_car', $base + ['deal_price' => $deal->priceFor($user)]));
        }

        $buyers = User::where('buyer_premium_until', '>', now())
            ->whereKeyNot([...$savers->modelKeys(), $car->user_id ?? 0])
            ->with(['savedSearches', 'wishlistItems.car'])
            ->get();
        foreach ($buyers as $buyer) {
            $price = $deal->priceFor($buyer);
            $search = $buyer->savedSearches->first(fn (SavedSearch $s) => $s->matches($car, $price));
            $similar = $buyer->wishlistItems->contains(fn (WishlistItem $w) => $w->car && $this->similar($w->car, $car));
            if (! $search && ! $similar) {
                continue;
            }
            $buyer->notify(new SiteAlert('deal_for_you', $base + [
                'price' => $price,
                'reason' => $search ? 'search' : 'similar',
                'search_query' => $search?->query,
                'search_max' => $search?->max_price,
            ]));
        }
    }

    // A new car: premium buyers whose saved search it matches
    public function carListed(Car $car): void
    {
        $buyers = User::where('buyer_premium_until', '>', now())
            ->when($car->user_id, fn ($q) => $q->whereKeyNot($car->user_id))
            ->has('savedSearches')->with('savedSearches')
            ->get();
        foreach ($buyers as $buyer) {
            $search = $buyer->savedSearches->first(fn (SavedSearch $s) => $s->matches($car));
            if ($search) {
                $buyer->notify(new SiteAlert('search_match', [
                    'car_id' => $car->id,
                    'car_name' => $car->name,
                    'price' => $car->price !== null ? (float) $car->price : null,
                    'search_query' => $search->query,
                    'search_max' => $search->max_price,
                ]));
            }
        }
    }

    // Someone put the car in their wishlist: the premium seller hears about it.
    // An unread alert about the same car is updated instead of adding another one.
    public function carSaved(Car $car): void
    {
        $seller = $car->user;
        if (! $seller?->hasPremium()) {
            return;
        }
        $saves = $car->wishlistItems()->count();
        $unread = $seller->unreadNotifications()->where('type', 'car_saved')->get()
            ->first(fn ($n) => ($n->data['car_id'] ?? null) === $car->id);
        if ($unread) {
            $unread->forceFill(['data' => ['saves' => $saves] + $unread->data, 'created_at' => now()])->save();

            return;
        }
        $seller->notify(new SiteAlert('car_saved', ['car_id' => $car->id, 'car_name' => $car->name, 'saves' => $saves]));
    }

    public function inquiry(Car $car, CarInquiry $inquiry): void
    {
        $car->user?->notify(new SiteAlert('inquiry', [
            'car_id' => $car->id,
            'car_name' => $car->name,
            'name' => $inquiry->name,
            'phone' => $inquiry->phone,
            'email' => $inquiry->email,
            'member' => $inquiry->is_member,
        ]));
    }

    public function similar(Car $a, Car $b): bool
    {
        if ($a->id === $b->id) {
            return false;
        }
        if ($a->make() !== '' && $a->make() === $b->make()) {
            return true;
        }
        if ($a->price === null || $b->price === null) {
            return false;
        }

        return abs((float) $a->price - (float) $b->price) <= self::SIMILAR_PRICE * max((float) $a->price, (float) $b->price);
    }
}
