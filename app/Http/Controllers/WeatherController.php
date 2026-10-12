<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;

// The weather widget next to the menu button, for open pages that refresh it every 15 minutes (weather.js)
class WeatherController extends Controller
{
    public function __invoke(): JsonResponse
    {
        return response()->json(['html' => view('components.weather')->render()])
            ->header('Cache-Control', 'no-store');
    }
}
