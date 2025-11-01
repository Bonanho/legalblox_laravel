<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\User>
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
            'password' => static::$password ??= Hash::make('password'),
            'remember_token' => Str::random(10),
            'is_active' => true,
            'is_superuser' => false,
            'is_org_superuser' => false,
        ];
    }
    
    /**
     * Indicate that the user is a global superuser.
     */
    public function superuser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_superuser' => true,
        ]);
    }
    
    /**
     * Indicate that the user is an org superuser.
     */
    public function orgSuperuser(): static
    {
        return $this->state(fn (array $attributes) => [
            'is_org_superuser' => true,
        ]);
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }
}
