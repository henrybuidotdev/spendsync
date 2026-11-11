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
}
