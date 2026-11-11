<?php

namespace App\Console\Commands;

use App\Jobs\ParseRawEmail;
use App\Models\RawEmail;
use Illuminate\Console\Command;

class ReparseCommand extends Command
{
    protected $signature = 'thuchi:reparse {--failed : Only emails that failed to parse} {--bank= : Only this bank code, e.g. VCB}';

    protected $description = 'Re-run parsing over stored raw emails (use after fixing a parser)';

    public function handle(): int
    {
        $query = RawEmail::query()
            ->when($this->option('failed'), fn ($query) => $query->whereNotNull('parse_error'))
            ->when($this->option('bank'), fn ($query, $bank) => $query->where('bank_code', strtoupper($bank)));

        $total = $query->count();
        $failed = 0;

        $query->lazyById()->each(function (RawEmail $rawEmail) use (&$failed) {
            $this->line("Parsing raw email `{$rawEmail->id}`...");

            ParseRawEmail::dispatchSync($rawEmail);

            if ($rawEmail->refresh()->parse_error) {
                $failed++;
                $this->line("  <fg=red>{$rawEmail->parse_error}</>");
            }
        });

        $this->comment("Reparsed {$total} emails, {$failed} failed.");

        return self::SUCCESS;
    }
}
