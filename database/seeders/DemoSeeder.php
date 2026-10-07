<?php

namespace Database\Seeders;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Seeder;

// Sample sellers and cars for a local setup. Never run this on real data.
class DemoSeeder extends Seeder
{
    public function run(): void
    {
        $premium = User::factory()->create([
            'name' => 'Premium Seller', 'email' => 'premium@example.com', 'phone' => '+386 40 100 200',
            'premium_plan' => 'monthly', 'premium_since' => now(), 'premium_until' => now()->addMonth(),
        ]);
        $regular = User::factory()->create(['name' => 'Regular Seller', 'email' => 'seller@example.com', 'phone' => '+386 40 300 400']);

        Car::factory()->for($premium)->create(['name' => 'BMW 530d Touring', 'price' => 41000]);
        Car::factory()->for($regular)->boosted()->create(['name' => 'Mercedes C220', 'price' => 47300]);
        Car::factory()->for($regular)->create(['name' => 'Audi A4 Avant', 'price' => 25600]);

        Car::all()->each(function (Car $car) {
            foreach (collect(['Volan', 'Bremza', 'Pedali', 'Sedezi', 'Key'])->shuffle()->take(rand(2, 4)) as $name) {
                $car->parts()->create(['name' => $name, 'description' => $name]);
            }
        });
    }
}
