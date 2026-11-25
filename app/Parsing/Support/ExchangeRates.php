<?php

namespace App\Parsing\Support;

use Illuminate\Support\Facades\Storage;

/** VND per one unit of a foreign currency: the daily downloaded table first, then config/services.php as a fallback. */
class ExchangeRates
{
    public const FILE = 'fx-rates.json';

    public static function vndPer(string $currency): ?float
    {
        $rates = Storage::json(self::FILE) ?? [];
        $currency = strtoupper($currency);

        return $rates[$currency] ?? config('services.fx.'.strtolower($currency).'_vnd');
    }

    /** @param array<string, float> $rates VND per unit, keyed by currency code */
    public static function save(array $rates): void
    {
        Storage::put(self::FILE, json_encode($rates));
    }
}
