<?php

namespace App\Parsing;

use App\Parsing\Support\TextNormalizer;
use Carbon\CarbonImmutable;

class ParsedEmailInput
{
    public function __construct(
        public readonly string $from,
        public readonly string $subject,
        public readonly ?string $html,
        public readonly string $text,
        public readonly CarbonImmutable $receivedAt,
    ) {}

    /** Builds the normalized text from HTML when present, otherwise from the plain-text part. */
    public static function make(string $from, string $subject, ?string $html, ?string $text, CarbonImmutable $receivedAt): self
    {
        $normalized = filled($html)
            ? TextNormalizer::htmlToText($html)
            : TextNormalizer::clean((string) $text);

        return new self(strtolower(trim($from)), TextNormalizer::clean($subject), $html, $normalized, $receivedAt);
    }
}
