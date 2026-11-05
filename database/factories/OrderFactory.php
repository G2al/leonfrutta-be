<?php

namespace Database\Factories;

use App\Enums\OrderStatus;
use App\Models\Order;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Order>
 */
class OrderFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'first_name' => fake()->firstName(),
            'last_name' => fake()->lastName(),
            'business_name' => fake()->company(),
            'phone' => '+393'.fake()->numerify('#########'),
            'message' => fake()->paragraph(),
        ];
    }

    public function confirmed(string $amount = '45.50'): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Confirmed,
            'amount' => $amount,
            'confirmed_at' => now(),
        ]);
    }

    public function paid(): static
    {
        return $this->confirmed()->state(fn (): array => [
            'paid_at' => now(),
        ]);
    }

    public function cancelled(): static
    {
        return $this->state(fn (): array => [
            'status' => OrderStatus::Cancelled,
            'cancelled_at' => now(),
        ]);
    }
}
