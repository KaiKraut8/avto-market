<?php

namespace App\Http\Controllers;

use Illuminate\View\View;

// The two premium plans; buying goes through the checkout (CheckoutController)
class PremiumController extends Controller
{
    public function index(): View
    {
        return view('premium', ['user' => auth()->user()]);
    }
}
