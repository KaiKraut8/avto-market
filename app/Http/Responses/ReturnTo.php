<?php

namespace App\Http\Responses;

use Illuminate\Http\Request;

// After logging in or signing up from the welcome panel, go back to the page the visitor was on.
// Only a path on this site is accepted, never another host.
trait ReturnTo
{
    protected function returnTo(Request $request): ?string
    {
        $to = (string) $request->input('return_to', '');

        return str_starts_with($to, '/') && ! str_starts_with($to, '//') && ! str_contains($to, '\\') ? $to : null;
    }
}
