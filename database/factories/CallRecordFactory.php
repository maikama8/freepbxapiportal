<?php

namespace Database\Factories;

use App\Models\CallRecord;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\CallRecord>
 */
class CallRecordFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $startTime = fake()->dateTimeBetween('-30 days', 'now');
        $duration = fake()->numberBetween(10, 3600); // 10 seconds to 1 hour
        $endTime = (clone $startTime)->modify("+{$duration} seconds");
        
        return [
            'user_id' => User::factory(),
            'call_id' => 'call_' . fake()->unique()->randomNumber(8),
            'caller_id' => fake()->phoneNumber(),
            'destination' => fake()->phoneNumber(),
            'start_time' => $startTime,
            'end_time' => $endTime,
            'duration' => $duration,
            'cost' => fake()->randomFloat(4, 0.01, 50.00),
            'status' => fake()->randomElement(['initiated', 'ringing', 'answered', 'completed', 'failed', 'cancelled']),
            'freepbx_response' => [
                'channel' => 'SIP/' . fake()->randomNumber(4),
                'uniqueid' => fake()->uuid(),
                'response' => 'Success'
            ]
        ];
    }

    /**
     * Create a completed call.
     */
    public function completed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'completed',
            'end_time' => fake()->dateTimeBetween($attributes['start_time'] ?? '-1 hour', 'now'),
        ]);
    }

    /**
     * Create a failed call.
     */
    public function failed(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'failed',
            'end_time' => null,
            'duration' => 0,
            'cost' => 0,
        ]);
    }

    /**
     * Create an active call.
     */
    public function active(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => 'answered',
            'end_time' => null,
            'start_time' => fake()->dateTimeBetween('-10 minutes', 'now'),
        ]);
    }

    /**
     * Create an expensive call.
     */
    public function expensive(): static
    {
        return $this->state(fn (array $attributes) => [
            'duration' => fake()->numberBetween(1800, 7200), // 30 minutes to 2 hours
            'cost' => fake()->randomFloat(4, 10.00, 100.00),
        ]);
    }
}