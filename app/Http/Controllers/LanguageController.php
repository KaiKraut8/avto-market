<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

// The language picker: remember the choice and go back to the page you were on
class LanguageController extends Controller
{
    public function __invoke(Request $request, string $locale): RedirectResponse
    {
        abort_unless(array_key_exists($locale, config('locales')), 404);
        $request->session()->put('locale', $locale);

        return redirect()->back(fallback: route('home'));
    }
}
