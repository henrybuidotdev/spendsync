<?php

namespace App\Parsing;

interface BankEmailParser
{
    public function bankCode(): string;

    /** @return array<int, string> lowercase sender addresses */
    public function senders(): array;

    public function supports(string $from, string $subject): bool;

    /** Null means "bank email, but not a transaction" (OTP, promo, statement). */
    public function parse(ParsedEmailInput $input): ?ParsedTransaction;
}
