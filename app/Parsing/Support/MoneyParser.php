<?php

namespace App\Parsing\Support;

class MoneyParser
{
    /**
     * Parse a VND amount string into a signed integer.
     * Handles "1,250,000 VND", "-1.250.000 VND", "+500,000đ", "1.250.000,00", "1,250,000.00".
     */
    public static function parse(string $value): ?int
    {
        $value = TextNormalizer::nfc($value);
        $value = str_replace(['−', '–'], '-', $value);

        if (! preg_match('/([+-])?\s*(\d[\d.,\s]*)/u', $value, $match)) {
            return null;
        }

        $number = preg_replace('/\s+/', '', rtrim($match[2], '., '));
        $fraction = '';

        if (preg_match('/^(.*\d)[.,](\d{1,2})$/', $number, $parts)) {
            [$number, $fraction] = [$parts[1], $parts[2]];
        }

        $integer = (int) preg_replace('/\D/', '', $number);

        if ($fraction !== '' && (int) str_pad($fraction, 2, '0') >= 50) {
            $integer++;
        }

        return $match[1] === '-' ? -$integer : $integer;
    }

    /**
     * Signed amount plus ISO currency. VND (or no marker) gives whole dong; any other currency gives
     * minor units, e.g. "12.50 USD" -> ['amount' => 1250, 'currency' => 'USD'].
     *
     * @return array{
     *     amount: int,
     *     currency: string
     * }|null
     */
    public static function parseWithCurrency(string $value): ?array
    {
        $currency = self::currency(TextNormalizer::nfc($value));

        if ($currency === 'VND') {
            $amount = self::parse($value);

            return $amount === null ? null : ['amount' => $amount, 'currency' => 'VND'];
        }

        $value = str_replace(['−', '–'], '-', $value);

        if (! preg_match('/([+-])?\s*\$?\s*(\d[\d.,\s]*)/u', $value, $match)) {
            return null;
        }

        $number = preg_replace('/\s+/', '', rtrim($match[2], '., '));
        $fraction = '00';

        if (preg_match('/^(.*\d)[.,](\d{1,2})$/', $number, $parts)) {
            [$number, $fraction] = [$parts[1], str_pad($parts[2], 2, '0')];
        }

        // ponytail: assumes 2 decimal places for every foreign currency; JPY/KRW are stored x100, which display reverses.
        $minor = (int) preg_replace('/\D/', '', $number) * 100 + (int) $fraction;

        return ['amount' => $match[1] === '-' ? -$minor : $minor, 'currency' => $currency];
    }

    /** First currency marker wins, so "12.50 USD (325,000 VND)" is USD; no marker means VND. */
    private static function currency(string $value): string
    {
        if (! preg_match('/VN[DĐ]|[đ₫]|\$|\b[A-Z]{3}\b/u', $value, $match)) {
            return 'VND';
        }

        return match (true) {
            str_contains($match[0], '$') => 'USD',
            preg_match('/^(VN[DĐ]|[đ₫])$/u', $match[0]) === 1 => 'VND',
            default => $match[0],
        };
    }
}
