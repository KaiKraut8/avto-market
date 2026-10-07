<?php

namespace App\Http\Controllers;

use App\Services\Pricing;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

// Premium for sellers and for buyers (simulated purchase: nothing is charged)
class PremiumController extends Controller
{
    public function index(): View
    {
        return view('premium', ['user' => auth()->user()]);
    }

    public function store(Request $request, Pricing $pricing): RedirectResponse
    {
        $data = $request->validate([
            'billing' => ['required', 'in:monthly,yearly'],
            'kind' => ['sometimes', 'in:seller,buyer'],
        ]);
        $user = $request->user();

        if (($data['kind'] ?? 'seller') === 'buyer') {
            if (! $pricing->activateBuyerPremium($user, $data['billing'])) {
                return redirect()->to(route('premium.index').'#buyers')->with('error', __('Premium couldn\'t be activated. Your account may already be premium.'));
            }

            return redirect()->to(route('premium.index').'#buyers')->with('status', __('You are now a premium buyer (:plan, until :date).', [
                'plan' => __($user->buyer_premium_plan),
                'date' => $user->buyer_premium_until->format('Y-m-d'),
            ]));
        }

        if (! $pricing->activatePremium($user, $data['billing'])) {
            return redirect()->route('premium.index')->with('error', __('Premium couldn\'t be activated. Your account may already be premium.'));
        }

        return redirect()->route('premium.index')->with('status', __('Your account is now premium (:plan, until :date).', [
            'plan' => __($user->premium_plan),
            'date' => $user->premium_until->format('Y-m-d'),
        ]));
    }
}
