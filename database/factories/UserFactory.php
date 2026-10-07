<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'phone' => '+386 40 '.fake()->numerify('### ###'),
            'location' => fake()->randomElement(['Ljubljana', 'Maribor', 'Celje']),
            'country' => 'Slovenia',
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
        ];
    }

    public function premium(string $plan = 'monthly'): static
    {
        return $this->state([
            'premium_plan' => $plan,
            'premium_since' => now(),
            'premium_until' => now()->addMonths($plan === 'yearly' ? 12 : 1),
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function premiumBuyer(string $plan = 'monthly'): static
    {
        return $this->state(fn () => [
            'buyer_premium_plan' => $plan,
            'buyer_premium_since' => now(),
            'buyer_premium_until' => now()->addMonths($plan === 'yearly' ? 12 : 1),
        ]);
    }

    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
