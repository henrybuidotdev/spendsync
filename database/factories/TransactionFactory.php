<?php

namespace Database\Factories;

use App\Enums\Direction;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'bank_code' => 'VCB',
            'direction' => Direction::Out,
            'amount' => fake()->numberBetween(10, 2000) * 1000,
            'balance_after' => fake()->numberBetween(1000, 50000) * 1000,
            'counterparty' => fake()->company(),
            'description' => fake()->sentence(4),
            'occurred_at' => now()->subMinutes(fake()->numberBetween(1, 10000)),
        ];
    }

    public function income(): static
    {
        return $this->state(['direction' => Direction::In]);
    }
}
