<?php

namespace Database\Factories;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class CategoryRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'match_field' => MatchField::Counterparty,
            'match_value' => fake()->company(),
            'category_id' => Category::factory(),
        ];
    }
}
