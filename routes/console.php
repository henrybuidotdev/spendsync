<?php

use App\Enums\MailAccountStatus;
use App\Jobs\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    MailAccount::where('status', MailAccountStatus::Active)
        ->whereRaw('id % 60 = ?', [now()->minute])
        ->where(fn ($query) => $query
            ->whereNull('last_synced_at')
            ->orWhere('last_synced_at', '<', now()->subMinutes(30)))
        ->each(fn (MailAccount $account) => SyncMailAccount::dispatch($account));
})->name('sync-mail-accounts')->everyMinute();

Schedule::command('spendsync:update-exchange-rates')->dailyAt('08:00');
