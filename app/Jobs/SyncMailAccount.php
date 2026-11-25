<?php

namespace App\Jobs;

use App\Enums\MailAccountStatus;
use App\Mail\Inbox\InvalidCredentialsException;
use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\MailboxConnectionException;
use App\Models\MailAccount;
use App\Models\RawEmail;
use App\Parsing\ParserRegistry;
use Carbon\CarbonInterface;
use Illuminate\Contracts\Queue\ShouldBeUniqueUntilProcessing;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Throwable;

/*
 * A sync starts services.mail_sync.first_sync_days ago (first sync) or resync_days before last_synced_at,
 * then each run fetches one WINDOW_DAYS window and queues the next window until it reaches today.
 * ponytail: with several workers, a scheduled run can overlap a queued continuation; it only refetches
 * already stored emails. Add a WithoutOverlapping middleware if that ever matters.
 */
class SyncMailAccount implements ShouldBeUniqueUntilProcessing, ShouldQueue
{
    use Queueable;

    public bool $deleteWhenMissingModels = true;

    public int $timeout = 600;

    public int $tries = 2;

    /** Releases the unique lock even if a worker dies mid-job. */
    public int $uniqueFor = 600;

    public const WINDOW_DAYS = 15;

    public function __construct(public MailAccount $account, public ?CarbonInterface $from = null) {}

    public function uniqueId(): string
    {
        return (string) $this->account->id;
    }

    public function handle(MailboxClient $client, ParserRegistry $registry): void
    {
        try {
            $nextFrom = $this->sync($client, $registry);
        } catch (Throwable $exception) {
            $this->account->finishSyncing();

            throw $exception;
        }

        if ($nextFrom === null) {
            $this->account->finishSyncing();

            return;
        }

        $this->account->isSyncing()
            ? $this->account->queueSync($nextFrom)
            : self::dispatch($this->account, $nextFrom);
    }

    /** Returns where the next window starts, or null when the sync has reached today. */
    private function sync(MailboxClient $client, ParserRegistry $registry): ?CarbonInterface
    {
        $account = $this->account->fresh();

        if (! $account?->isActive() || blank($account->app_password)) {
            return null;
        }

        try {
            $client->connect($account->email, $account->app_password);
        } catch (InvalidCredentialsException) {
            $account->update(['status' => MailAccountStatus::NeedsReconnect, 'last_error' => 'invalid_credentials']);

            return null;
        } catch (MailboxConnectionException $exception) {
            $account->update(['last_error' => 'connection_failed']);

            throw $exception;
        }

        $senders = $registry->senders();
        $since = $this->from ?? ($account->last_synced_at
            ? $account->last_synced_at->copy()->subDays(config('services.mail_sync.resync_days'))
            : now()->subDays(config('services.mail_sync.first_sync_days')));
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

        if ($isLastWindow) {
            $account->update(['last_synced_at' => now(), 'last_run_at' => now(), 'last_error' => null]);

            return null;
        }

        $movesForward = $account->last_synced_at === null || $account->last_synced_at->lt($before);

        $account->update([
            'last_synced_at' => $movesForward ? $before : $account->last_synced_at,
            'last_run_at' => now(),
            'last_error' => null,
        ]);

        return $before;
    }
}
