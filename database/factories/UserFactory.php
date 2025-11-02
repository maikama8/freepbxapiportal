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
            'phone' => fake()->phoneNumber(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('password'),
            'role' => fake()->randomElement(['customer', 'operator']),
            'account_type' => fake()->randomElement(['prepaid', 'postpaid']),
            'balance' => fake()->randomFloat(4, 0, 1000),
            'credit_limit' => fake()->randomFloat(4, 0, 500),
            'status' => 'active',
            'timezone' => fake()->timezone(),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'sip_username' => fake()->unique()->userName(),
            'sip_password' => Str::random(12),
            'failed_login_attempts' => 0,
            'remember_token' => Str::random(10),
        ];
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

    /**
     * Create an admin user.
     */
    public function admin(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'admin',
            'account_type' => 'postpaid',
            'balance' => 0,
            'credit_limit' => 10000,
        ]);
    }

    /**
     * Create a customer user.
     */
    public function customer(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'customer',
        ]);
    }

    /**
     * Create an operator user.
     */
    public function operator(): static
    {
        return $this->state(fn (array $attributes) => [
            'role' => 'operator',
            'account_type' => 'postpaid',
            'balance' => 0,
            'credit_limit' => 1000,
        ]);
    }

    /**
     * Create a prepaid user.
     */
    public function prepaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'prepaid',
            'balance' => fake()->randomFloat(4, 10, 500),
            'credit_limit' => 0,
        ]);
    }

    /**
     * Create a postpaid user.
     */
    public function postpaid(): static
    {
        return $this->state(fn (array $attributes) => [
            'account_type' => 'postpaid',
            'balance' => fake()->randomFloat(4, -100, 100),
            'credit_limit' => fake()->randomFloat(4, 100, 1000),
        ]);
    }

    /**
     * Create a locked user.
     */
    public function locked(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'locked',
            'failed_login_attempts' => 3,
            'locked_until' => now()->addMinutes(15),
        ]);
    }
}
