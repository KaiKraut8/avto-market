<?php

namespace Database\Factories;

use App\Models\Car;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Car> */
class CarFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => fake()->randomElement(['BMW 320d', 'Mercedes C220', 'Audi A4', 'VW Golf', 'Škoda Octavia', 'Renault Clio']),
            'price' => fake()->numberBetween(30, 600) * 100,
            'year' => fake()->numberBetween(2008, (int) date('Y')),
            'description' => fake()->optional()->paragraph(),
            'location' => fake()->randomElement(['Ljubljana', 'Maribor', 'Celje', 'Kranj', 'Koper']),
            'country' => 'Slovenia',
        ];
    }

    public function unowned(): static
    {
        return $this->state(['user_id' => null]);
    }

    public function boosted(int $days = 7): static
    {
        return $this->state(['boosted_until' => now()->addDays($days)]);
    }
}
