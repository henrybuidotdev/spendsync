<?php

namespace Database\Seeders;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/** The read-only demo account (services.demo.email; password "password" locally, random elsewhere), populated by importing every fixture through the real parsing pipeline. */
class DemoSeeder extends Seeder
{
    private const RULES = [
        ['counterparty', 'CONG TY TNHH CONG NGHE ABC', 'Salary'],
        ['counterparty', 'CONG TY TNHH SHOPEE', 'Shopping'],
        ['counterparty', 'LE THI HOA', 'Rentals'],
        ['description', 'grab', 'Taxi'],
        ['description', 'highlands', 'Coffee'],
        ['description', 'tien dien', 'Electricity bill'],
    ];

    public function run(): void
    {
        $email = config('services.demo.email');

        User::where('email', $email)->first()?->delete();

        $user = User::factory()->create([
            'name' => 'Demo',
            'email' => $email,
            'password' => app()->isLocal() ? 'password' : Str::random(40),
        ]);

        foreach (self::RULES as [$field, $value, $categoryName]) {
            $user->categoryRules()->create([
                'match_field' => MatchField::from($field),
                'match_value' => $value,
                'category_id' => Category::system()->where('name', $categoryName)->value('id'),
            ]);
        }

        Artisan::call('spendsync:import-eml', [
            'user' => $user->id,
            'path' => base_path('tests/Fixtures/emails'),
        ]);

        $this->command?->info(trim(Artisan::output()));

        Artisan::call('spendsync:refresh-demo');
    }
}
