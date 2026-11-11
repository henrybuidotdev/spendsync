<?php

namespace App\Models;

use App\Mail\Inbox\FetchedMessage;
use App\Parsing\ParsedEmailInput;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Str;

#[Fillable(['user_id', 'mail_account_id', 'message_id', 'from_address', 'subject', 'received_at', 'body_html', 'body_text', 'bank_code', 'parsed_at', 'parse_error'])]
#[Hidden(['body_html', 'body_text'])]
class RawEmail extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'received_at' => 'datetime',
            'parsed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function mailAccount(): BelongsTo
    {
        return $this->belongsTo(MailAccount::class);
    }

    public function transaction(): HasOne
    {
        return $this->hasOne(Transaction::class);
    }

    /** Dedupes on (user_id, message_id); check `wasRecentlyCreated` to know if it is new. */
    public static function storeFetched(int $userId, ?int $mailAccountId, FetchedMessage $message): self
    {
        return static::firstOrCreate(
            ['user_id' => $userId, 'message_id' => Str::limit($message->messageId, 250, '')],
            [
                'mail_account_id' => $mailAccountId,
                'from_address' => Str::limit($message->from, 250, ''),
                'subject' => Str::limit($message->subject, 250, ''),
                'received_at' => $message->receivedAt->utc(),
                'body_html' => $message->html,
                'body_text' => $message->text,
            ],
        );
    }

    public function toParserInput(): ParsedEmailInput
    {
        return ParsedEmailInput::make(
            $this->from_address,
            $this->subject,
            $this->body_html,
            $this->body_text,
            $this->received_at->toImmutable(),
        );
    }
}
