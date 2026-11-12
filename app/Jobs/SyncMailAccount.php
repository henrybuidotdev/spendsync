<?php

namespace App\Jobs;

use App\Enums\MailAccountStatus;
use App\Mail\Inbox\InvalidCredentialsException;
use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\MailboxConnectionException;
use App\Models\MailAccount;
use App\Models\RawEmail;
use App\Parsing\ParserRegistry;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncMailAccount implements ShouldBeUnique, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 120;

    public int $tries = 2;

    /** Releases the unique lock even if a worker dies mid-job. */
    public int $uniqueFor = 600;

    public const FIRST_SYNC_DAYS = 30;

    public function __construct(public MailAccount $account) {}

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(MailboxClient $client, ParserRegistry $registry): void
    {
        try {
            $this->sync($client, $registry);
        } finally {
            $this->account->finishSyncing();
        }
    }

    private function sync(MailboxClient $client, ParserRegistry $registry): void
    {
        $account = $this->account->fresh();

        if (! $account?->isActive() || blank($account->app_password)) {
            return;
        }

        try {
            $client->connect($account->email, $account->app_password);
        } catch (InvalidCredentialsException) {
            $account->update(['status' => MailAccountStatus::NeedsReconnect, 'last_error' => 'invalid_credentials']);

            return;
        } catch (MailboxConnectionException $exception) {
            $account->update(['last_error' => 'connection_failed']);

            throw $exception;
        }

        $senders = $registry->senders();
        $since = $account->last_synced_at
            ? $account->last_synced_at->copy()->subDay()
            : now()->subDays(self::FIRST_SYNC_DAYS);

        try {
            foreach ($client->fetchFrom($senders, $since) as $message) {
                if (! in_array($message->from, $senders, true)) {
                    continue;
                }

                $rawEmail = RawEmail::storeFetched($account->user_id, $account->id, $message);

                if ($rawEmail->wasRecentlyCreated) {
                    ParseRawEmail::dispatch($rawEmail);
                }
            }
        } finally {
            $client->disconnect();
        }

        $account->update(['last_synced_at' => now(), 'last_error' => null]);
    }
}
