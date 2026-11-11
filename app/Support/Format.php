<?php

namespace App\Support;

use Carbon\CarbonImmutable;
use Carbon\CarbonInterface;

class Format
{
    /** 1250000 -> "1.250.000 ₫"; with $signed, prefixes "+" or "−". */
    public static function money(int $amount, bool $signed = false): string
    {
        $formatted = number_format(abs($amount), 0, ',', '.').' ₫';

        if (! $signed) {
            return $amount < 0 ? "−{$formatted}" : $formatted;
        }

        return ($amount < 0 ? '−' : '+').$formatted;
    }

    public static function local(?CarbonInterface $date): ?CarbonInterface
    {
        return $date?->copy()->setTimezone(config('app.display_timezone'))->locale(app()->getLocale());
    }

    /** "2026-11" -> [start, end) of that month in local time, falling back to the current month. */
    public static function month(?string $month): CarbonImmutable
    {
        $timezone = config('app.display_timezone');

        if ($month && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month)) {
            return CarbonImmutable::createFromFormat('!Y-m', $month, $timezone);
        }

        return CarbonImmutable::now($timezone)->startOfMonth();
    }
}
