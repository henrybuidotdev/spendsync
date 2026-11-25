<?php

namespace App\Console\Commands;

use App\Models\RawEmail;
use App\Models\Transaction;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class RefreshDemoCommand extends Command
{
    protected $signature = 'spendsync:refresh-demo';

    protected $description = 'Move the demo account\'s transactions (and their emails\' received dates) by whole days so the newest one is today';

    public function handle(): int
    {
        $demo = User::firstWhere('email', config('services.demo.email'));

        if ($demo === null) {
            $this->comment('No demo account; nothing to refresh.');

            return self::SUCCESS;
        }

        $transactions = $demo->transactions()->withTrashed();
        $newest = $transactions->max('occurred_at');

        if ($newest === null) {
            $this->comment('The demo account has no transactions; nothing to refresh.');

            return self::SUCCESS;
        }

        $timezone = config('app.display_timezone');
        $today = CarbonImmutable::now($timezone)->startOfDay();
        $days = (int) CarbonImmutable::parse($newest, 'UTC')->setTimezone($timezone)->startOfDay()->diffInDays($today);

        if ($days === 0) {
            $this->comment('Demo transactions are already up to date.');

            return self::SUCCESS;
        }

        $this->info("Moving {$transactions->count()} demo transactions by {$days} days...");

        // ponytail: one save per row, fine for a demo-sized dataset; switch to a single UPDATE if it grows large.
        $transactions->each(function (Transaction $transaction) use ($days): void {
            $transaction->occurred_at = $transaction->occurred_at->addDays($days);
            $transaction->saveQuietly();
        });

        $demo->rawEmails()->each(function (RawEmail $rawEmail) use ($days): void {
            $rawEmail->received_at = $rawEmail->received_at->addDays($days);
            $rawEmail->saveQuietly();
        });

        $this->comment('Demo data refreshed.');

        return self::SUCCESS;
    }
}
