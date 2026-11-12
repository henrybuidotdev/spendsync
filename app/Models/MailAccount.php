<?php

namespace App\Models;

use App\Enums\MailAccountStatus;
use App\Jobs\SyncMailAccount;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Cache;

#[Fillable(['user_id', 'provider', 'email', 'app_password', 'status', 'last_synced_at', 'last_error'])]
#[Hidden(['app_password'])]
class MailAccount extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'app_password' => 'encrypted',
            'status' => MailAccountStatus::class,
            'last_synced_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rawEmails(): HasMany
    {
        return $this->hasMany(RawEmail::class);
    }

    public function isActive(): bool
    {
        return $this->status === MailAccountStatus::Active;
    }

    /** User-initiated sync: flags the account as syncing (the UI shows a live banner) until the job finishes. */
    public function queueSync(): void
    {
        Cache::put($this->syncingKey(), true, now()->addMinutes(5));

        SyncMailAccount::dispatch($this);
    }

    public function isSyncing(): bool
    {
        return Cache::has($this->syncingKey());
    }

    public function finishSyncing(): void
    {
        Cache::forget($this->syncingKey());
    }

    private function syncingKey(): string
    {
        return "mail-account-syncing:{$this->id}";
    }
}
