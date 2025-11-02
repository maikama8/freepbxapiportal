<?php

namespace Database\Factories;

use App\Models\PaymentTransaction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\PaymentTransaction>
 */
class PaymentTransactionFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gateway = fake()->randomElement(['nowpayments', 'paypal']);
        $paymentMethod = $gateway === 'nowpayments' 
            ? fake()->randomElement(['btc', 'eth', 'usdt', 'ltc'])
            : 'paypal';

        return [
            'user_id' => User::factory(),
            'amount' => fake()->randomFloat(2, 1.00, 500.00),
            'currency' => fake()->randomElement(['USD', 'EUR', 'GBP']),
            'gateway' => $gateway,
            'gateway_transaction_id' => fake()->uuid(),
            'status' => fake()->randomElement(['pending', 'completed', 'failed', 'cancelled']),
            'payment_method' => $paymentMethod,
            'payment_url' => fake()->url(),
            'expires_at' => fake()->dateTimeBetween('now', '+24 hours'),
            'metadata' => [
                'ip_address' => fake()->ipv4(),
                'user_agent' => fake()->userAgent(),
                'created_via' => 'api'
            ]
        ];
    }

    /**
     * Create a completed payment.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'completed_at' => fake()->dateTimeBetween('-30 days', 'now'),
        ]);
    }

    /**
     * Create a pending payment.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'pending',
            'expires_at' => fake()->dateTimeBetween('now', '+24 hours'),
        ]);
    }

    /**
     * Create a failed payment.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'metadata' => array_merge($attributes['metadata'] ?? [], [
                'failure_reason' => fake()->randomElement([
                    'insufficient_funds',
                    'payment_declined',
                    'expired',
                    'cancelled_by_user'
                ])
            ])
        ]);
    }

    /**
     * Create a NowPayments transaction.
     */
    public function nowPayments(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'nowpayments',
            'payment_method' => fake()->randomElement(['btc', 'eth', 'usdt', 'ltc']),
            'gateway_transaction_id' => fake()->randomNumber(8),
        ]);
    }

    /**
     * Create a PayPal transaction.
     */
    public function paypal(): static
    {
        return $this->state(fn (array $attributes) => [
            'gateway' => 'paypal',
            'payment_method' => 'paypal',
            'gateway_transaction_id' => 'PAYID-' . fake()->bothify('???????-??????????'),
        ]);
    }

    /**
     * Create a large payment.
     */
    public function large(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 100.00, 1000.00),
        ]);
    }

    /**
     * Create a small payment.
     */
    public function small(): static
    {
        return $this->state(fn (array $attributes) => [
            'amount' => fake()->randomFloat(2, 1.00, 10.00),
        ]);
    }
}