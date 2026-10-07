<?php

namespace App\Support;

use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Cookie;

// Anonymous visitor id in the "kai_visitor" cookie: views, wishlist and contact reveals are per visitor.
// The same cookie the old site used, so returning visitors keep their wishlist and history.
class Visitor
{
    public const COOKIE = 'kai_visitor';

    private static ?string $id = null;

    public static function id(): string
    {
        return self::$id ??= self::fromRequest(request());
    }

    public static function fromRequest(Request $request): string
    {
        $id = (string) $request->cookie(self::COOKIE, '');

        return preg_match('/^[a-f0-9]{32}$/', $id) ? $id : bin2hex(random_bytes(16));
    }

    public static function cookie(string $id): Cookie
    {
        return cookie(self::COOKIE, $id, 60 * 24 * 365, '/', null, null, true, false, 'lax');
    }

    public static function forget(): void
    {
        self::$id = null;
    }
}
