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

    // The seller may change their car; the admin may change any car, including ones without a seller
    public function update(User $user, Car $car): bool
    {
        return $user->isAdmin() || ($car->user_id !== null && (int) $car->user_id === (int) $user->id);
    }

    public function delete(User $user, Car $car): bool
    {
        return $this->update($user, $car);
    }

    // Special deals are a premium seller perk, for their own priced cars
    public function runDeal(User $user, Car $car): bool
    {
        return $car->user_id !== null && (int) $car->user_id === (int) $user->id
            && $user->hasPremium() && $car->price !== null && $car->sold_at === null;
    }
}
