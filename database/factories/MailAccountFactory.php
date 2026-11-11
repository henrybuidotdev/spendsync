<?php

namespace Database\Factories;

use App\Enums\MailAccountStatus;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class MailAccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'provider' => 'gmail',
            'email' => fake()->unique()->userName().'@gmail.com',
            'app_password' => 'abcdabcdabcdabcd',
            'status' => MailAccountStatus::Active,
        ];
    }

    public function needsReconnect(): static
    {
        return $this->state(['status' => MailAccountStatus::NeedsReconnect, 'last_error' => 'invalid_credentials']);
    }
}
