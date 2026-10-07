<?php

namespace App\Http\Controllers;

use App\Http\Requests\DealRequest;
use App\Models\Car;
use App\Services\Alerts;
use Illuminate\Http\RedirectResponse;

// Premium sellers start and end special deals on their cars
class CarDealController extends Controller
{
    public function store(DealRequest $request, Car $car, Alerts $alerts): RedirectResponse
    {
        $car->deals()->active()->update(['ends_at' => now()]);   // one deal at a time: a new one replaces the old
        $deal = $car->deals()->create([
            'regular_price' => $car->price,
            'deal_price' => $request->validated('deal_price'),
            'member_price' => $request->validated('member_price'),
            'ends_at' => now()->addDays((int) $request->validated('days')),
        ]);
        $alerts->dealStarted($deal);

        return redirect()->route('cars.show', $car)->with('status', __('Your deal is live until :date. Buyers who saved this car or similar cars have been told.', [
            'date' => $deal->ends_at->format('Y-m-d H:i'),
        ]));
    }

    public function destroy(Car $car): RedirectResponse
    {
        $car->deals()->active()->update(['ends_at' => now()]);

        return redirect()->route('cars.show', $car)->with('status', __('The deal has ended. The car is back at its regular price.'));
    }
}
