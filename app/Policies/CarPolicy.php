<?php

namespace App\Policies;

use App\Models\Car;
use App\Models\User;

class CarPolicy
{
    public function create(User $user): bool
    {
        return true;
    }

    // The seller may change their car. Cars listed before accounts existed have no seller yet,
    // so any logged-in user may manage those until they're assigned to an account.
    public function update(User $user, Car $car): bool
    {
        return $car->user_id === null || (int) $car->user_id === (int) $user->id;
    }

    public function delete(User $user, Car $car): bool
    {
        return $this->update($user, $car);
    }

    // Special deals are a premium seller perk, for their own priced cars
    public function runDeal(User $user, Car $car): bool
    {
        return $car->user_id !== null && (int) $car->user_id === (int) $user->id
            && $user->hasPremium() && $car->price !== null;
    }
}
