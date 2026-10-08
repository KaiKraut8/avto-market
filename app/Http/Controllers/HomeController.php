<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Services\Alerts;
use App\Support\CarSearch;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class HomeController extends Controller
{
    public function __invoke(Alerts $alerts): View
    {
        $cars = Car::query()
            ->forSale()
            ->withPlacement()
            ->withPeopleCount()
            ->with(['coverPhoto', 'activeDeal', 'activeSale'])
            ->orderBy('cars.id')
            ->get();

        // Highlights: premium cars only. If there are fewer than three, fill up with pushed cars, then the rest.
        $premium = $cars->filter->isPremium()->values();
        $highlights = $premium;
        if ($highlights->count() < 3) {
            $others = $cars->reject->isPremium()->sortBy([fn ($a, $b) => $b->isBoosted() <=> $a->isBoosted(), ['id', 'asc']]);
            $highlights = $highlights->concat($others->take(3 - $highlights->count()))->values();
        }

        // Badges: cheapest = best price, most expensive = premium pick, most viewed = most watched, newest = new arrival
        $badges = $cars->mapWithKeys(fn ($c) => [$c->id => []])->all();
        $priced = $cars->whereNotNull('price')->sortBy(fn ($c) => (float) $c->price)->values();
        $priciest = null;
        if ($priced->count() > 1) {
            $badges[$priced->first()->id][] = [__('Best price'), 'deal'];
            $priciest = $priced->last();
            $badges[$priciest->id][] = [__('Premium pick'), 'premium'];
        }
        $mostWatched = $cars->sortByDesc('people')->first();
        $watched = $mostWatched && $mostWatched->people > 0;
        if ($watched) {
            $badges[$mostWatched->id][] = [__('Most watched'), 'hot'];
        }
        $newest = $cars->sortByDesc('created_at')->first();
        if ($newest && ! $badges[$newest->id]) {
            $badges[$newest->id][] = [__('New arrival'), 'new'];
        }

        return view('home', [
            'cars' => $cars,
            'deals' => DealController::forVisitor($alerts)->take(3),
            'premiumCount' => $premium->count(),
            'highlights' => $highlights,
            'badges' => $badges,
            // hero: the most watched car if anyone looked, otherwise the priciest, otherwise the first
            'topPick' => $watched ? $mostWatched : ($priciest ?? $cars->first()),
            'topLabel' => $watched ? __('Most watched right now') : __('Top pick of the week'),
            'lowest' => $priced->first()?->price,
            'bounds' => CarSearch::bounds(),
            // distinct people across all listed cars (one person looking at three cars is one person)
            'interested' => (int) DB::table('car_views')->join('cars', 'cars.id', '=', 'car_views.car_id')
                ->whereNull('cars.deleted_at')->distinct()->count('car_views.visitor_id'),
        ]);
    }
}
