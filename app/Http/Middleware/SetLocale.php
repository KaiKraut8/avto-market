<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

// The site's language: the one picked in the language menu, otherwise the browser's preferred one
class SetLocale
{
    public function handle(Request $request, Closure $next): Response
    {
        $supported = array_keys(config('locales'));
        $locale = $request->session()->get('locale');
        if (! in_array($locale, $supported, true)) {
            $locale = $request->getPreferredLanguage($supported) ?? config('app.locale');
        }
        App::setLocale($locale);   // Carbon follows, so weekday and month names are translated too

        return $next($request);
    }
}
