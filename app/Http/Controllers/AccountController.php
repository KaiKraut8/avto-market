<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

// The seller's profile: their cars, premium status and contact details
class AccountController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();
        $cars = $user->cars()
            ->withPlacement()
            ->with('coverPhoto')
            ->withPeopleCount()
            ->withCount('inquiries')
            ->orderByDesc('cars.id')
            ->get();

        return view('account', ['user' => $user, 'cars' => $cars]);
    }
}
