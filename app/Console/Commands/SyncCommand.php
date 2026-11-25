<?php

namespace App\Console\Commands;

use App\Enums\MailAccountStatus;
use App\Jobs\SyncMailAccount;
use App\Models\MailAccount;
use Illuminate\Console\Command;

class SyncCommand extends Command
{
    protected $signature = 'spendsync:sync {account? : Mail account id (default: all active accounts)}';

    protected $description = 'Sync mail accounts now (parsing jobs go to the queue)';

    public function handle(): int
    {
        $accounts = MailAccount::query()
            ->when($this->argument('account'), fn ($query, $id) => $query->whereKey($id))
            ->where('status', MailAccountStatus::Active)
            ->get();

        foreach ($accounts as $account) {
            $this->info("Syncing account `{$account->id}`...");

            SyncMailAccount::dispatchSync($account);

            $account->refresh();
            $this->line($account->last_error ? "  <fg=red>{$account->last_error}</>" : '  ok');
        }

        $this->comment("Synced {$accounts->count()} accounts.");

        return self::SUCCESS;
    }
}
