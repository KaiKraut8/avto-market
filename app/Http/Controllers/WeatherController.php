<?php

namespace App\Http\Controllers;

use App\Services\Weather;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

// The weather widget next to the menu button, for pages that fetch it themselves (weather.js): at the visitor's
// location when the browser shares it (?lat=&lon=), otherwise Maribor's, and again every 15 minutes
class WeatherController extends Controller
{
    public function __invoke(Request $request, Weather $weather): JsonResponse
    {
        $lat = $request->query('lat');
        $lon = $request->query('lon');
        $reading = is_numeric($lat) && is_numeric($lon)
            ? $weather->near((float) $lat, (float) $lon)
            : $weather->current();

        return response()->json([
            'html' => view('components.weather', ['reading' => $reading])->render(),
            'place' => $reading ? ($reading['place'] ?? Weather::HOME) : null,   // readings stored before places were named are Maribor's
        ])->header('Cache-Control', 'no-store');
    }
}
