<?php

namespace App\Http\Controllers;

use App\Http\Requests\CarRequest;
use App\Models\Car;
use App\Models\CarInquiry;
use App\Models\CarLog;
use App\Models\PartCategory;
use App\Models\WishlistItem;
use App\Services\PhotoStore;
use App\Services\Pricing;
use App\Services\ViewTracker;
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
        $q = trim(mb_substr((string) $request->query('q', ''), 0, 80));
        $cars = $this->listing($q);
        $noMatch = $q !== '' && $cars->isEmpty();
        if ($noMatch) {
            $cars = $this->listing('');   // still offer every car, as other options
        }

        return view('cars.index', [
            'q' => $q,
            'noMatch' => $noMatch,
            'cars' => $cars,
            'premium' => $cars->filter->isPremium()->values(),
            'regular' => $cars->reject->isPremium()->values(),
            'wished' => WishlistItem::where('visitor_id', Visitor::id())->pluck('car_id')->flip(),
        ]);
    }

    private function listing(string $q)
    {
        return Car::query()
            ->withPlacement()
            ->withPeopleCount()
            ->with(['coverPhoto', 'parts:id,car_id,name'])
            ->search($q)
            ->listingOrder()
            ->get();
    }

    public function create(Request $request): View
    {
        $user = $request->user();

        return view('cars.create', [
            'car' => new Car(['location' => $user->location, 'country' => $user->country ?? config('countries')[0]]),
        ]);
    }

    public function store(CarRequest $request, Pricing $pricing, PhotoStore $photos): RedirectResponse
    {
        $user = $request->user();
        $car = $user->cars()->create($request->safe()->only(['name', 'price', 'location', 'country', 'description']));

        // a premium seller's cars are premium already; the others choose free, a push, or premium for the account
        $messages = [__('Saved.')];
        if (! $user->hasPremium() && $request->input('plan') === 'premium') {
            $pricing->activatePremium($user, $request->input('billing', 'monthly'));
            $user->refresh();
            $messages = [__('Premium activated for your account (:plan, until :date): all your cars are now shown first in All cars, in gold.', [
                'plan' => __($user->premium_plan === 'yearly' ? 'yearly' : 'monthly'),
                'date' => $user->premium_until->format('Y-m-d'),
            ])];
        } elseif (! $user->hasPremium() && $request->input('plan') === 'boost') {
            $pricing->boost($car);
            $messages = [__('Pushed forward until :date: shown first among the regular cars.', ['date' => $car->boosted_until->format('Y-m-d H:i')])];
        }

        if ($request->hasFile('photos')) {
            $messages[] = $this->photoMessage($photos->store($car, $request->file('photos')));
        }

        return redirect()->route('cars.show', $car)->with('status', implode(' ', array_filter($messages)));
    }

    public function show(Car $car, ViewTracker $tracker): View
    {
        $car = Car::withPlacement()->with(['user', 'photos', 'parts'])->findOrFail($car->id);

        // a visit counts as a view, the reload after saving or uploading doesn't
        if (! session()->has('status')) {
            $tracker->record($car);
        }

        $user = auth()->user();

        return view('cars.show', [
            'car' => $car,
            'canEdit' => $user && Gate::allows('update', $car),
            'isOwner' => $user && $car->user_id !== null && (int) $car->user_id === (int) $user->id,
            'wished' => WishlistItem::where('visitor_id', Visitor::id())->where('car_id', $car->id)->exists(),
            'contacted' => CarInquiry::where('visitor_id', Visitor::id())->where('car_id', $car->id)->exists(),
            'contact' => $car->sellerContact(),
            'stats' => $tracker->stats($car),
            'categories' => PartCategory::orderBy('id')->pluck('name'),
        ]);
    }

    public function update(CarRequest $request, Car $car): RedirectResponse
    {
        $old = $car->name;
        $car->update($request->safe()->only(['name', 'price', 'location', 'country', 'description']));   // never changes premium or a push
        if ($old !== $car->name) {
            CarLog::create(['car_id' => $car->id, 'action' => 'edit', 'old_name' => $old, 'new_name' => $car->name]);
        }

        return redirect()->route('cars.show', $car)->with('status', __('Saved.'));
    }

    // Soft delete: the car disappears from the site but stays in the database
    public function destroy(Car $car): RedirectResponse
    {
        $car->delete();
        CarLog::create(['car_id' => $car->id, 'action' => 'delete']);

        return redirect()->route('home')->with('status', __('The car was removed from the site.'));
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
