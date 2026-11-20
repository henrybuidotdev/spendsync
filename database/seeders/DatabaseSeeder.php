<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $this->call(SystemCategorySeeder::class);

        if (! app()->environment('local', 'testing')) {
            return;
        }

        if (User::where('email', 'test@spendsync.co')->exists()) {
            return;
        }

        User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@spendsync.co',
        ]);
    }
}
