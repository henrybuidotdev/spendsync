<?php

namespace App\Parsing\Support;

/**
 * Masks the account balance in a bank email body, so it is never stored.
 * Keeps the label ("Số dư cuối:", "SD:") and replaces only the amount with ***.
 */
class BalanceRedactor
{
    public const MASK = '***';

    private const PATTERN = '/
        (
            (?:
                Số\ dư(?:\ khả\ dụng|\ cuối|\ sau\ giao\ dịch)?(?:\s*\/\s*Balance)?\s*:?
                | (?<![\p{L}\d])SD\s*:
                | (?<![\p{L}\d])Balance\s*:
            )
            (?:\s|&nbsp;|<[^>]*>)*
        )
        (?:VND\s*)?[+-]?\d[\d.,]*(?:\s*(?:VND|đ))?
    /iux';

    public static function redact(?string $body): ?string
    {
        if ($body === null) {
            return null;
        }

        return preg_replace(self::PATTERN, '$1'.self::MASK, TextNormalizer::nfc($body)) ?? $body;
    }
}
