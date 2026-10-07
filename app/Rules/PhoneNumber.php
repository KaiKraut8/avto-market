<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// International format: 8 to 15 digits, optional leading +, spaces / dashes / brackets / dots / slashes allowed
class PhoneNumber implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $digits = is_string($value) ? preg_replace('/[\s\-().\/]/', '', $value) : '';

        if (! preg_match('/^\+?[0-9]{8,15}$/', $digits)) {
            $fail('Enter a valid phone number with 8 to 15 digits, like +386 40 123 456.');
        }
    }
}
