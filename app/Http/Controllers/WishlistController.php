<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\WishlistItem;
use App\Support\Visitor;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

// Each browser has its own wishlist (the visitor cookie)
class WishlistController extends Controller
{
    public function index(): View
    {
        $cars = Car::query()
            ->withPlacement()
            ->with(['coverPhoto', 'parts:id,car_id,name'])
            ->join('wishlist_items', 'wishlist_items.car_id', '=', 'cars.id')
            ->where('wishlist_items.visitor_id', Visitor::id())
            ->addSelect('wishlist_items.created_at as wished_at')
            ->orderByDesc('wishlist_items.created_at')->orderBy('cars.id')
            ->get();

        return view('wishlist', [
            'cars' => $cars,
            'sum' => $cars->sum(fn ($c) => (float) $c->price),
        ]);
    }

    public function toggle(Car $car): JsonResponse
    {
        $removed = WishlistItem::where('visitor_id', Visitor::id())->where('car_id', $car->id)->delete();
        if (! $removed) {
            WishlistItem::create(['visitor_id' => Visitor::id(), 'car_id' => $car->id]);
        }
        $wished = ! $removed;

        return response()->json([
            'wished' => $wished,
            'count' => WishlistItem::where('visitor_id', Visitor::id())->whereHas('car')->count(),
            'label' => $wished ? __('In your wishlist') : __('Add to wishlist'),
            'tooltip' => $wished ? __('Remove from wishlist') : __('Add to wishlist'),
        ]);
    }
}
