<?php

namespace App\Parsing;

use App\Enums\Direction;
use Carbon\CarbonImmutable;

class ParsedTransaction
{
    public function __construct(
        public readonly Direction $direction,
        public readonly int $amount,
        public readonly ?int $balanceAfter,
        public readonly ?string $counterparty,
        public readonly ?string $description,
        public readonly ?string $reference,
        public readonly CarbonImmutable $occurredAt,
    ) {}

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return [
            'direction' => $this->direction->value,
            'amount' => $this->amount,
            'balance_after' => $this->balanceAfter,
            'counterparty' => $this->counterparty,
            'description' => $this->description,
            'reference' => $this->reference,
            'occurred_at' => $this->occurredAt->toIso8601String(),
        ];
    }
}
