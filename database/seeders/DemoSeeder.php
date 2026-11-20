<?php

namespace Database\Seeders;

use App\Enums\MatchField;
use App\Models\Category;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Str;

/** The read-only demo account (services.demo.email; password "password" locally, random elsewhere), populated by importing every fixture through the real parsing pipeline plus a generated DemoLedger. */
class DemoSeeder extends Seeder
{
    private const RULES = [
        ['counterparty', 'CONG TY TNHH CONG NGHE ABC', 'Salary'],
        ['counterparty', 'CONG TY TNHH SHOPEE', 'Shopping'],
        ['counterparty', 'LE THI HOA', 'Rentals'],
        ['description', 'grab', 'Taxi'],
        ['description', 'highlands', 'Coffee'],
        ['description', 'tien dien', 'Electricity bill'],
        ['counterparty', 'TRAN THI BICH', 'Rentals'],
        ['counterparty', 'QUAN AN NHAT', 'Restaurants'],
        ['counterparty', 'PAYOO Aeon Mall Binh Tan', 'Shopping'],
        ['counterparty', 'CONG AN THANH PHO DA NANG', 'Other'],
        ['description', 'topup viettel', 'Phone bill'],
    ];

    /** Imported fixtures that would skew the reports: a test transfer, transfers between own accounts, a cancelled card charge. */
    private const HIDDEN_COUNTERPARTIES = [
        'LE VAN CUONG' => 'Cho anh Cường mượn, theo dõi riêng',
        'NGUYEN VAN AN' => 'Chuyển giữa tài khoản của tôi',
        'ANTHROPIC* CLAUDE SUB +14152360599 US' => 'Giao dịch thẻ đã bị hủy và hoàn tiền',
    ];

    public function run(): void
    {
        $email = config('services.demo.email');

        User::where('email', $email)->first()?->delete();

        // Not a factory: production installs without dev dependencies, so Faker isn't there.
        $user = User::forceCreate([
            'name' => 'Demo User',
            'email' => $email,
            'email_verified_at' => now(),
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

        foreach (self::HIDDEN_COUNTERPARTIES as $counterparty => $note) {
            $user->transactions()->where('counterparty', $counterparty)->each(function (Transaction $transaction) use ($note): void {
                $transaction->update(['note' => $note]);
                $transaction->delete();
            });
        }

        $newest = CarbonImmutable::parse($user->transactions()->max('occurred_at') ?? now(), 'UTC');
        $user->transactions()->createMany((new DemoLedger)->build($newest));

        Artisan::call('spendsync:refresh-demo');
    }
}
