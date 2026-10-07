<?php

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Validation\ValidationRule;

// A real-looking name: starts with a letter, then letters, spaces, dots, apostrophes or dashes (2 to 60 characters)
class PersonName implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        if (! is_string($value) || ! preg_match("/^\p{L}[\p{L} .'\-]{1,59}$/u", $value)) {
            $fail('Enter a real name (letters only, 2 to 60 characters).');
        }
    }
}
