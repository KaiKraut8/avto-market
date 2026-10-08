<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarRequest;
use App\Models\Car;
use App\Models\CarInquiry;
use App\Models\CarLog;
use App\Models\CarSale;
use App\Models\WishlistItem;
use App\Services\Alerts;
use App\Services\PhotoStore;
use App\Services\ViewTracker;
use App\Support\CarSearch;
use App\Support\Visitor;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;

class CarController extends Controller
{
    // All cars, or search results. Premium cars come first in their own block.
    public function index(Request $request): View
    {
        $search = CarSearch::fromRequest($request);
        $cars = $this->listing($search);
        $noMatch = ! $search->isEmpty() && $cars->isEmpty();
        if ($noMatch) {
            $cars = $this->listing(new CarSearch(sort: $search->sort));   // still offer every car, as other options
        }

        return view('cars.index', [
            'search' => $search,
            'q' => $search->q,
            'noMatch' => $noMatch,
            'cars' => $cars,
            'bounds' => CarSearch::bounds(),
            // sorted by the visitor's choice: one list; otherwise premium cars in their own block first
            'premium' => $search->isSorted() ? collect() : $cars->filter->isPremium()->values(),
            'regular' => $search->isSorted() ? $cars : $cars->reject->isPremium()->values(),
            'wished' => WishlistItem::where('visitor_id', Visitor::id())->pluck('car_id')->flip(),
        ]);
    }

    private function listing(CarSearch $search)
    {
        return Car::query()
            ->forSale()
            ->withPlacement()
            ->withPeopleCount()
            ->with(['coverPhoto', 'activeDeal', 'activeSale'])
            ->tap(fn ($q) => $search->apply($q))
            ->tap(fn ($q) => $search->order($q))
            ->get();
    }

    public function create(Request $request): View|RedirectResponse
    {
        $user = $request->user();
        if ($due = $this->unpaidCommission($user)) {
            return $due;
        }

        return view('cars.create', [
            'car' => new Car(['location' => $user->location, 'country' => $user->country ?? config('countries')[0]]),
        ]);
    }

    public function store(CarRequest $request, PhotoStore $photos, Alerts $alerts): RedirectResponse
    {
        $user = $request->user();
        if ($due = $this->unpaidCommission($user)) {
            return $due;
        }
        $car = $user->cars()->create($request->safe()->only(['name', 'price', 'year', 'location', 'country', 'description']));

        $messages = [__('Saved.')];
        if ($request->hasFile('photos')) {
            $messages[] = $this->photoMessage($photos->store($car, $request->file('photos')));
        }
        $alerts->carListed($car);

        // a premium seller's cars are premium already; the others may pay for a push or premium for the account
        $status = implode(' ', array_filter($messages));
        $plan = $user->hasPremium() ? 'free' : $request->input('plan', 'free');
        if ($plan === 'boost') {
            return redirect()->route('checkout.create', ['product' => 'boost', 'car' => $car->id])->with('status', $status);
        }
        if ($plan === 'premium') {
            return redirect()->route('checkout.create', ['product' => 'seller', 'billing' => $request->input('billing', 'monthly')])->with('status', $status);
        }

        return redirect()->route('cars.show', $car)->with('status', $status);
    }

    public function show(Car $car, ViewTracker $tracker): View
    {
        $car = Car::withPlacement()->with(['user', 'photos', 'activeDeal', 'activeSale.buyer'])->findOrFail($car->id);

        // a visit counts as a view, the reload after saving or uploading doesn't
        if (! session()->has('status')) {
            $tracker->record($car);
        }

        $user = auth()->user();

        return view('cars.show', [
            'car' => $car,
            'canEdit' => $user && Gate::allows('update', $car),
            'canDeal' => $user && Gate::allows('runDeal', $car),
            'isOwner' => $user && $car->user_id !== null && (int) $car->user_id === (int) $user->id,
            'wished' => WishlistItem::where('visitor_id', Visitor::id())->where('car_id', $car->id)->exists(),
            'contacted' => CarInquiry::where('visitor_id', Visitor::id())->where('car_id', $car->id)->exists(),
            'contact' => $car->sellerContact(),
            'stats' => $tracker->stats($car),
        ]);
    }

    public function update(CarRequest $request, Car $car): RedirectResponse
    {
        $old = $car->name;
        $car->update($request->safe()->only(['name', 'price', 'year', 'location', 'country', 'description']));   // never changes premium or a push
        if ($old !== $car->name) {
            CarLog::create(['car_id' => $car->id, 'action' => 'edit', 'old_name' => $old, 'new_name' => $car->name]);
        }

        // a running deal compares against the price it started from, so a new price ends it
        $message = __('Saved.');
        if ($car->wasChanged('price') && $car->deals()->active()->update(['ends_at' => now()])) {
            $message .= ' '.__('The price changed, so the special deal has ended. Start a new one if you like.');
        }

        return redirect()->route('cars.show', $car)->with('status', $message);
    }

    // Soft delete: the car disappears from the site but stays in the database
    public function destroy(Car $car): RedirectResponse
    {
        $car->delete();
        CarLog::create(['car_id' => $car->id, 'action' => 'delete']);

        return redirect()->route('home')->with('status', __('The car was removed from the site.'));
    }

    // A seller who sold a car elsewhere lists again once its commission is paid
    private function unpaidCommission($user): ?RedirectResponse
    {
        $sale = CarSale::where('seller_id', $user->id)->where('status', 'due')->first();

        return $sale ? redirect()->route('checkout.create', ['product' => 'commission', 'sale' => $sale->id])
            ->with('error', __('Please pay the commission for your last sale first; then you can list cars again.')) : null;
    }

    public static function photoMessage(array $result): string
    {
        $parts = [];
        if ($result['added']) {
            $parts[] = trans_choice(':count photo added.|:count photos added.', $result['added']);
        }
        if ($result['skipped']) {
            $parts[] = trans_choice(':count file skipped (must be a JPG, PNG, WebP or GIF image under 8 MB).|:count files skipped (must be a JPG, PNG, WebP or GIF image under 8 MB).', $result['skipped']);
        }

        return implode(' ', $parts);
    }
}
