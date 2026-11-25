<?php

namespace App\Console\Commands;

use App\Jobs\ParseRawEmail;
use App\Models\RawEmail;
use App\Parsing\Support\ExchangeRates;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Http;

class UpdateExchangeRatesCommand extends Command
{
    protected $signature = 'spendsync:update-exchange-rates';

    protected $description = 'Download today\'s exchange rates for every currency and reparse emails that failed for a missing rate';

    public const URL = 'https://open.er-api.com/v6/latest/USD';

    public function handle(): int
    {
        $this->info('Downloading exchange rates...');

        $response = Http::timeout(15)->retry(2, 1000, throw: false)->get(self::URL);
        $usdRates = $response->json('rates');
        $vndPerUsd = $usdRates['VND'] ?? null;

        if (! $response->successful() || $response->json('result') !== 'success' || ! is_numeric($vndPerUsd)) {
            $this->error('Could not download exchange rates; keeping the previous ones.');

            return self::FAILURE;
        }

        $rates = collect($usdRates)
            ->filter(fn ($rate) => is_numeric($rate) && $rate > 0)
            ->map(fn ($rate) => $vndPerUsd / $rate)
            ->all();

        ExchangeRates::save($rates);

        $this->comment('Saved rates for '.count($rates).' currencies.');

        $failed = RawEmail::where('parse_error', 'currency_unsupported')->get();

        $failed->each(function (RawEmail $rawEmail) {
            $this->info("Reparsing raw email `{$rawEmail->id}`...");

            ParseRawEmail::dispatch($rawEmail);
        });

        $this->comment("Queued {$failed->count()} emails that failed for a missing rate.");

        return self::SUCCESS;
    }
}
