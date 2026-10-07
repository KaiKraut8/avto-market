<?php

namespace App\Http\Middleware;

use App\Support\Visitor;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

// Gives every browser a visitor id (and sets the cookie when it's new)
class EnsureVisitorId
{
    public function handle(Request $request, Closure $next): Response
    {
        Visitor::forget();
        $id = Visitor::id();
        $response = $next($request);
        if ($request->cookie(Visitor::COOKIE) !== $id) {
            $response->headers->setCookie(Visitor::cookie($id));
        }

        return $response;
    }
}
