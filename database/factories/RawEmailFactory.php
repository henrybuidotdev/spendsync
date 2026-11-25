<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class RawEmailFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'message_id' => '<'.fake()->uuid().'@bank.test>',
            'from_address' => 'info@vietcombank.com.vn',
            'subject' => 'Thông báo biến động số dư',
            'received_at' => now(),
            'body_text' => 'Số tiền: -50,000 VND',
            'bank_code' => 'VCB',
        ];
    }
}
