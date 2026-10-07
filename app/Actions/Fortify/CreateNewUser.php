<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\CreatesNewUsers;

// Sign up as a seller: buyers reach you with these contact details, so they have to look real
class CreateNewUser implements CreatesNewUsers
{
    use PasswordValidationRules;

    public function create(array $input): User
    {
        $input['email'] = mb_strtolower(trim($input['email'] ?? ''));

        Validator::make($input, [
            'name' => ['required', new PersonName],
            'email' => ['required', 'string', 'email:rfc', 'max:120', Rule::unique(User::class)],
            'phone' => ['required', new PhoneNumber],
            'password' => $this->passwordRules(),
            'location' => ['nullable', 'string', 'max:80'],
            'country' => ['required', Rule::in(config('countries'))],
        ], [
            'email.unique' => __('An account with this email already exists. Log in instead.'),
            'email.email' => __('Enter a valid email address, like name@example.com.'),
        ])->validate();

        return User::create([
            'name' => trim($input['name']),
            'email' => $input['email'],
            'phone' => trim($input['phone']),
            'password' => $input['password'],   // hashed by the model cast
            'location' => trim($input['location'] ?? '') ?: null,
            'country' => $input['country'],
        ]);
    }
}
