<?php

namespace App\Http\Controllers;

use App\Models\Car;
use Illuminate\Http\RedirectResponse;

// Push forward for a week: paid at the checkout. Premium sellers' cars don't need it.
class CarBoostController extends Controller
{
    public function __invoke(Car $car): RedirectResponse
    {
        if ($car->isPremium()) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already premium, so it is shown first anyway.'));
        }

        return redirect()->route('checkout.create', ['product' => 'boost', 'car' => $car->id]);
    }
}
