<?php

namespace App\Jobs;

use App\Enums\MailAccountStatus;
use App\Mail\Inbox\InvalidCredentialsException;
use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\MailboxConnectionException;
use App\Models\MailAccount;
use App\Models\RawEmail;
use App\Parsing\ParserRegistry;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/*
 * Each run fetches one window of at most WINDOW_DAYS days, starting from last_synced_at
 * (or FIRST_SYNC_DAYS ago), saves its progress there, and queues the next run until it reaches today.
 * ponytail: with several workers, a scheduled run can overlap a queued continuation; it only refetches
 * already stored emails. Add a WithoutOverlapping middleware if that ever matters.
 */
class SyncMailAccount implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 120;

    public int $tries = 2;

    /** Releases the unique lock even if a worker dies mid-job. */
    public int $uniqueFor = 600;

    public const FIRST_SYNC_DAYS = 90;

    public const WINDOW_DAYS = 15;

    public function __construct(public MailAccount $account) {}

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(MailboxClient $client, ParserRegistry $registry): void
    {
        try {
            $hasMore = $this->sync($client, $registry);
        } catch (Throwable $exception) {
            $this->account->finishSyncing();

            throw $exception;
        }

        if (! $hasMore) {
            $this->account->finishSyncing();

            return;
        }

        $this->account->isSyncing()
            ? $this->account->queueSync()
            : self::dispatch($this->account);
    }

    /** Returns whether older-than-today windows are still left to fetch. */
    private function sync(MailboxClient $client, ParserRegistry $registry): bool
    {
        $account = $this->account->fresh();

        if (! $account?->isActive() || blank($account->app_password)) {
            return false;
        }

        try {
            $client->connect($account->email, $account->app_password);
        } catch (InvalidCredentialsException) {
            $account->update(['status' => MailAccountStatus::NeedsReconnect, 'last_error' => 'invalid_credentials']);

            return false;
        } catch (MailboxConnectionException $exception) {
            $account->update(['last_error' => 'connection_failed']);

            throw $exception;
        }

        $senders = $registry->senders();
        $since = $account->last_synced_at
            ? $account->last_synced_at->copy()->subDays(config('services.mail_sync.resync_days'))
            : now()->subDays(self::FIRST_SYNC_DAYS);
        $before = $since->copy()->addDays(self::WINDOW_DAYS)->startOfDay();
        $isLastWindow = $before->isFuture();

        try {
            foreach ($client->fetchFrom($senders, $since, $isLastWindow ? null : $before) as $message) {
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

        $account->update(['last_synced_at' => $isLastWindow ? now() : $before, 'last_error' => null]);

        return ! $isLastWindow;
    }
}
