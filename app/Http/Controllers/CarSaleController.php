<?php

namespace App\Http\Controllers;

use App\Models\Car;
use App\Models\CarSale;
use App\Services\Billing;
use App\Support\Money;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

// After a buyer reserved a car: the seller confirms the handover or cancels (the buyer is refunded).
// A car sold elsewhere is marked sold here and its commission paid by the seller.
class CarSaleController extends Controller
{
    public function complete(CarSale $sale, Billing $billing): RedirectResponse
    {
        $this->authorizeSale($sale, 'reserved');
        $billing->completeSale($sale);

        return redirect()->route('cars.show', $sale->car_id)->with('status', __('Sale confirmed. Congratulations! The car is now marked as sold.'));
    }

    public function cancel(CarSale $sale, Billing $billing): RedirectResponse
    {
        $this->authorizeSale($sale, 'reserved');
        $billing->cancelSale($sale);

        return redirect()->route('cars.show', $sale->car_id)->with('status', __('The sale is cancelled and the buyer is being refunded. The car is for sale again.'));
    }

    public function soldForm(Car $car): View|RedirectResponse
    {
        if ($redirect = $this->unsellable($car)) {
            return $redirect;
        }

        return view('cars.sold', ['car' => $car, 'rate' => CarSale::rate()]);
    }

    public function markSold(Request $request, Car $car, Billing $billing): RedirectResponse
    {
        if ($redirect = $this->unsellable($car)) {
            return $redirect;
        }
        $request->merge(['price' => Money::parse($request->input('price'))]);
        $data = $request->validate([
            'price' => ['required', 'numeric', 'min:1', 'max:99999999'],
            'terms' => ['accepted'],
        ], ['terms.accepted' => __('Please confirm that you have read how buying works.')]);

        $sale = $billing->markSoldElsewhere($car, $car->user ?? $request->user(), (float) $data['price']);

        return redirect()->route('checkout.create', ['product' => 'commission', 'sale' => $sale->id])
            ->with('status', __('The car is marked as sold. Please pay the :rate% commission (:amount) to keep listing cars.', [
                'rate' => (float) $sale->rate, 'amount' => Money::eur($sale->commission),
            ]));
    }

    // The seller (or the admin) of a sale in the given state
    private function authorizeSale(CarSale $sale, string $status): void
    {
        Gate::authorize('update', $sale->car);
        abort_unless($sale->status === $status, 404);
    }

    private function unsellable(Car $car): ?RedirectResponse
    {
        Gate::authorize('update', $car);
        $car->loadMissing('activeSale');
        if ($car->isSold() || $car->isReserved()) {
            return redirect()->route('cars.show', $car)->with('status', __('This car is already reserved or sold.'));
        }

        return null;
    }
}
