<?php

namespace App\Console\Commands;

use App\Models\Car;
use App\Models\User;
use Illuminate\Console\Command;

// Gives cars that were listed before seller accounts existed to a seller account
class AssignLegacyCars extends Command
{
    protected $signature = 'cars:assign {email : the seller account} {car* : car ids}';

    protected $description = 'Assign unowned (legacy) cars to a seller account';

    public function handle(): int
    {
        $user = User::where('email', mb_strtolower($this->argument('email')))->first();
        if (! $user) {
            $this->error('No account with that email.');

            return self::FAILURE;
        }
        foreach ($this->argument('car') as $id) {
            $car = Car::find($id);
            if (! $car) {
                $this->warn("Car #$id not found, skipped.");
            } elseif ($car->user_id !== null && (int) $car->user_id !== (int) $user->id) {
                $this->warn("Car #$id ({$car->name}) already belongs to another seller, skipped.");
            } else {
                $car->user()->associate($user)->save();
                $this->info("Car #$id ({$car->name}) now belongs to {$user->name}.");
            }
        }

        return self::SUCCESS;
    }
}
