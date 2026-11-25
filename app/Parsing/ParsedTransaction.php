<?php

namespace App\Parsing;

use App\Enums\Direction;
use Carbon\CarbonImmutable;

class ParsedTransaction
{
    public function __construct(
        public readonly Direction $direction,
        public readonly int $amount,
        public readonly ?string $counterparty,
        public readonly ?string $counterpartyAccount,
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly CarbonImmutable $occurredAt,
        public readonly ?string $currency = null,
        public readonly ?int $originalAmount = null,
        public readonly bool $amountEstimated = false,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'counterparty' => $this->counterparty,
            'counterparty_account' => $this->counterpartyAccount,
            'description' => $this->description,
            'reference' => $this->reference,
            'occurred_at' => $this->occurredAt->toIso8601String(),
            'currency' => $this->currency,
            'original_amount' => $this->originalAmount,
            'amount_estimated' => $this->amountEstimated,
        ];
    }
}
