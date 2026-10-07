<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

// The account: cars and their insights, premium plans, saved searches and contact details
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cars = $user->cars()
            ->withPlacement()
            ->with('coverPhoto')
            ->withPeopleCount()
            ->withCount([
                'inquiries',
                'wishlistItems as saves',
                'views as views_30' => fn ($q) => $q->where('viewed_at', '>=', now()->subDays(30)),
            ])
            ->with('activeDeal')
            ->orderByDesc('cars.id')
            ->get();

        return view('account', [
            'user' => $user,
            'cars' => $cars,
            'searches' => $user->savedSearches,
            'subscriptions' => $user->subscriptions()->current()->get(),
            'payments' => $user->payments()->where('status', '!=', 'open')->limit(20)->get(),
        ]);
    }
}
