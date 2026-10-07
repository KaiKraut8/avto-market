<?php

use App\Http\Middleware\EnsureVisitorId;
use App\Http\Middleware\SetLocale;
use App\Support\Visitor;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->web(append: [
            EnsureVisitorId::class,
            SetLocale::class,
        ]);
        // the visitor cookie is shared with the old site and read by plain PHP there, so keep it unencrypted
        $middleware->encryptCookies(except: [Visitor::COOKIE, 'kai_cookies_seen']);
        // Mollie can't send a CSRF token; the webhook only takes a payment id and asks Mollie for the status
        $middleware->validateCsrfTokens(except: ['webhooks/mollie']);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
