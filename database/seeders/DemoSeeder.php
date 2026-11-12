<?php

namespace Database\Seeders;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;

/** demo@spendsync.test / password, populated by importing every fixture through the real parsing pipeline. */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@spendsync.test';

    private const RULES = [
        ['counterparty', 'CONG TY TNHH CONG NGHE ABC', 'Salary'],
        ['counterparty', 'CONG TY TNHH SHOPEE', 'Shopping'],
        ['counterparty', 'LE THI HOA', 'Home'],
        ['description', 'grab', 'Transport'],
        ['description', 'highlands', 'Food & drink'],
        ['description', 'tien dien', 'Bills'],
    ];

    public function run(): void
    {
        User::where('email', self::EMAIL)->first()?->delete();

        $user = User::factory()->create([
            'name' => 'Demo',
            'email' => self::EMAIL,
            'password' => 'password',
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
    }
}
