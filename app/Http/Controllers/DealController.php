<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\WishlistItem;
use App\Services\Alerts;
use App\Support\Visitor;
use Illuminate\Support\Collection;
use Illuminate\View\View;

// Every running special deal; the ones that suit this visitor's wishlist come first
class DealController extends Controller
{
    public function __invoke(Alerts $alerts): View
    {
        $deals = self::forVisitor($alerts);

        return view('deals', [
            'deals' => $deals,
            'forYou' => $deals->where('for_you', true)->count(),
            'wished' => WishlistItem::where('visitor_id', Visitor::id())->pluck('car_id')->flip(),
        ]);
    }

    // Cars with a running deal, each marked for_you when it is like a car this visitor saved
    public static function forVisitor(Alerts $alerts): Collection
    {
        $cars = Car::query()
            ->withPlacement()
            ->withPeopleCount()
            ->with(['coverPhoto', 'parts:id,car_id,name', 'activeDeal'])
            ->whereHas('deals', fn ($q) => $q->active())
            ->listingOrder()
            ->get();

        $saved = Car::whereIn('id', WishlistItem::where('visitor_id', Visitor::id())->select('car_id'))->get();
        foreach ($cars as $car) {
            $car->for_you = $saved->contains(fn (Car $s) => $s->id === $car->id || $alerts->similar($s, $car));
        }

        return $cars->sortByDesc('for_you')->values();
    }
}
