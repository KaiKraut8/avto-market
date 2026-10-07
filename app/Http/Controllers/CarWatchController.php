<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Services\ViewTracker;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Response;
use Illuminate\Support\Carbon;

// Live interest for one car: the open page checks in, and the eye button asks for the numbers
class CarWatchController extends Controller
{
    public function ping(Car $car, ViewTracker $tracker): JsonResponse
    {
        $tracker->ping($car);

        return response()->json(['watching' => $tracker->watching($car)]);
    }

    public function leave(Car $car, ViewTracker $tracker): Response
    {
        $tracker->leave($car);

        return response()->noContent();
    }

    // The numbers and sentences for the eye button's panel, already translated
    public function stats(Car $car, ViewTracker $tracker): JsonResponse
    {
        $s = $tracker->stats($car);
        $history = trans_choice(':count person looked at it in total|:count people looked at it in total', $s['people'])
            .' · '.trans_choice(':count view|:count views', $s['total'])
            .($s['last_viewed'] ? ' · '.__('last :date', ['date' => Carbon::parse($s['last_viewed'])->format('Y-m-d H:i')]) : '');

        return response()->json([
            'watching' => $s['watching'],
            'people' => $s['people'],
            'total' => $s['total'],
            'days' => array_map(fn ($d) => [
                'n' => $d['n'],
                'label' => Carbon::parse($d['date'])->translatedFormat('D'),
                'title' => $d['date'].': '.trans_choice(':count view|:count views', $d['n']),
            ], $s['days']),
            'text' => [
                'watching' => trans_choice('person is viewing this car right now|people are viewing this car right now', $s['watching']),
                'history' => $history,
                'caption' => __('Views, last 7 days'),
            ],
        ]);
    }
}
