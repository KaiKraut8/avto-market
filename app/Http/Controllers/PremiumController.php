<?php

namespace App\Http\Controllers;

use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Premium for a seller account (simulated purchase: nothing is charged)
class PremiumController extends Controller
{
    public function index(): View
    {
        return view('premium', ['user' => auth()->user()]);
    }

    public function store(Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate(['billing' => ['required', 'in:monthly,yearly']]);
        $user = $request->user();

        if (! $pricing->activatePremium($user, $data['billing'])) {
            return redirect()->route('premium.index')->with('error', __('Premium couldn\'t be activated. Your account may already be premium.'));
        }

        return redirect()->route('premium.index')->with('status', __('Your account is now premium (:plan, until :date).', [
            'plan' => __($user->premium_plan),
            'date' => $user->premium_until->format('Y-m-d'),
        ]));
    }
}
