<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;

// Push forward for a week (simulated purchase). Premium sellers' cars don't need it.
class CarBoostController extends Controller
{
    public function __invoke(Car $car, Pricing $pricing): RedirectResponse
    {
        if (! $pricing->boost($car)) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already premium, so it is shown first anyway.'));
        }

        return redirect()->route('cars.show', $car)
            ->with('status', __('Pushed forward until :date: shown first among the regular cars.', ['date' => $car->boosted_until->format('Y-m-d H:i')]));
    }
}
