<?php

use App\Enums\MailAccountStatus;
use App\Jobs\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Support\Facades\Schedule;

Schedule::call(function () {
    MailAccount::where('status', MailAccountStatus::Active)
        ->each(fn (MailAccount $account) => SyncMailAccount::dispatch($account));
})->name('sync-mail-accounts')->hourly()->withoutOverlapping();

Schedule::command('spendsync:update-exchange-rates')->dailyAt('08:00');
