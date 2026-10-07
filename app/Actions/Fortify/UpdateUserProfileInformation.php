<?php

namespace App\Actions\Fortify;

use App\Models\User;
use App\Rules\PersonName;
use App\Rules\PhoneNumber;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Laravel\Fortify\Contracts\UpdatesUserProfileInformation;

// The profile form: name, phone, location and country. The email is the login and stays as it is.
class UpdateUserProfileInformation implements UpdatesUserProfileInformation
{
    public function update(User $user, array $input): void
    {
        Validator::make($input, [
            'name' => ['required', new PersonName],
            'phone' => ['required', new PhoneNumber],
            'location' => ['nullable', 'string', 'max:80'],
            'country' => ['required', Rule::in(config('countries'))],
        ])->validateWithBag('updateProfileInformation');

        $user->forceFill([
            'name' => trim($input['name']),
            'phone' => trim($input['phone']),
            'location' => trim($input['location'] ?? '') ?: null,
            'country' => $input['country'],
        ])->save();
    }
}
