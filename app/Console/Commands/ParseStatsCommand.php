<?php

namespace App\Console\Commands;

use App\Models\RawEmail;
use Illuminate\Console\Command;

class ParseStatsCommand extends Command
{
    protected $signature = 'beanpulse:parse-stats';

    protected $description = 'Parse success rate per bank';

    public function handle(): int
    {
        $rows = RawEmail::query()
            ->select('bank_code')
            ->selectRaw('count(*) as total')
            ->selectRaw('sum(case when parsed_at is not null then 1 else 0 end) as parsed')
            ->selectRaw('sum(case when parse_error is not null then 1 else 0 end) as failed')
            ->groupBy('bank_code')
            ->orderBy('bank_code')
            ->get()
            ->map(fn ($row) => [
                $row->bank_code ?? '—',
                $row->total,
                (int) $row->parsed,
                (int) $row->failed,
                round(100 * $row->parsed / max(1, $row->total), 1).'%',
            ]);

        if ($rows->isEmpty()) {
            $this->comment('No raw emails yet.');

            return self::SUCCESS;
        }

        $this->table(['Bank', 'Total', 'Parsed', 'Failed', 'Success'], $rows);

        return self::SUCCESS;
    }
}
