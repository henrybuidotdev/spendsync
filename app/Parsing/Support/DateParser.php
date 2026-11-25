<?php

namespace App\Parsing\Support;

use Carbon\CarbonImmutable;

class DateParser
{
    private const DATE = '(\d{1,2})[\/.\-](\d{1,2})[\/.\-](\d{4}|\d{2})';

    private const ISO_DATE = '(\d{4})-(\d{1,2})-(\d{1,2})';

    private const TIME = '(\d{1,2}):(\d{2})(?::(\d{2}))?';

    /**
     * Find the first date (and optional time) in a string, interpreted in the display timezone.
     * Accepts "12/11/2026 14:03:21", "12-11-2026 14:03", "14:03 12/11/2026", "2026-11-12 14:03:21".
     */
    public static function parse(string $value, ?string $timezone = null): ?CarbonImmutable
    {
        $timezone ??= config('app.display_timezone', 'Asia/Ho_Chi_Minh');

        if (preg_match('/'.self::ISO_DATE.'/', $value, $date)) {
            [$year, $month, $day] = [(int) $date[1], (int) $date[2], (int) $date[3]];
        } elseif (preg_match('/'.self::DATE.'/', $value, $date)) {
            [$day, $month, $year] = [(int) $date[1], (int) $date[2], (int) $date[3]];
        } else {
            return null;
        }

        if ($year < 100) {
            $year += 2000;
        }

        if (! checkdate($month, $day, $year)) {
            return null;
        }

        $withoutDate = str_replace($date[0], ' ', $value);
        [$hour, $minute, $second] = [0, 0, 0];

        if (preg_match('/'.self::TIME.'/', $withoutDate, $time)) {
            [$hour, $minute, $second] = [(int) $time[1], (int) $time[2], (int) ($time[3] ?? 0)];
        }

        if ($hour > 23 || $minute > 59 || $second > 59) {
            return null;
        }

        return CarbonImmutable::create($year, $month, $day, $hour, $minute, $second, $timezone);
    }
}
