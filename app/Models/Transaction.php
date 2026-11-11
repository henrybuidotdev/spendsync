<?php

namespace App\Models;

use App\Enums\Direction;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['user_id', 'raw_email_id', 'bank_code', 'direction', 'amount', 'balance_after', 'counterparty', 'description', 'reference', 'occurred_at', 'category_id', 'note'])]
class Transaction extends Model
{
    use HasFactory;

    protected function casts(): array
    {
        return [
            'direction' => Direction::class,
            'amount' => 'integer',
            'balance_after' => 'integer',
            'occurred_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function rawEmail(): BelongsTo
    {
        return $this->belongsTo(RawEmail::class);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    public function isIncome(): bool
    {
        return $this->direction === Direction::In;
    }

    public function signedAmount(): int
    {
        return $this->isIncome() ? $this->amount : -$this->amount;
    }

    public function label(): string
    {
        return $this->counterparty ?: ($this->description ?: $this->bank_code);
    }
}
