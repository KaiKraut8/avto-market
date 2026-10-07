<?php

namespace App\Http\Controllers;

use App\Services\ViewTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Carbon;
use Illuminate\View\View;

// Ranking of the cars by how many people looked at them, with who is watching right now
class MostWatchedController extends Controller
{
    public function index(ViewTracker $tracker): View
    {
        $cars = $tracker->ranking();

        return view('most-watched', [
            'cars' => $cars,
            'maxPeople' => max(1, (int) $cars->max('people')),
        ]);
    }

    public function stats(ViewTracker $tracker): JsonResponse
    {
        return response()->json([
            'cars' => $tracker->ranking()->map(fn ($c) => [
                'id' => $c->id,
                'total' => (int) $c->total,
                'people' => (int) $c->people,
                'watching' => (int) $c->watching,
                'last_viewed' => $c->last_viewed ? Carbon::parse($c->last_viewed)->format('Y-m-d H:i') : __('Never'),
            ])->values(),
            'updated' => '· '.__('last :time', ['time' => now()->format('H:i:s')]),
        ]);
    }
}
