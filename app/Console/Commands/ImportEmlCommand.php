<?php

namespace App\Console\Commands;

use App\Jobs\ParseRawEmail;
use App\Mail\Inbox\FetchedMessage;
use App\Models\RawEmail;
use App\Models\User;
use App\Parsing\ParserRegistry;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Symfony\Component\Finder\SplFileInfo;

class ImportEmlCommand extends Command
{
    protected $signature = 'thuchi:import-eml {user : User id or email} {path : An .eml file or a folder of them (searched recursively)}';

    protected $description = 'Import .eml files as raw emails for a user and parse them (dev/demo path, no Gmail needed)';

    public function handle(ParserRegistry $registry): int
    {
        $user = User::where('email', $this->argument('user'))->orWhere('id', $this->argument('user'))->first();

        if ($user === null) {
            $this->error('User not found.');

            return self::FAILURE;
        }

        $path = $this->argument('path');
        $files = is_dir($path)
            ? collect(File::allFiles($path))->filter(fn (SplFileInfo $file) => $file->getExtension() === 'eml')->map->getPathname()->sort()->values()
            : collect(is_file($path) ? [$path] : []);

        if ($files->isEmpty()) {
            $this->error("No .eml files found at `{$path}`.");

            return self::FAILURE;
        }

        $imported = 0;

        foreach ($files as $file) {
            $this->line("Importing `{$file}`...");

            $message = FetchedMessage::fromEml(file_get_contents($file));

            if ($registry->findFor($message->from, $message->subject) === null) {
                $this->warn('  skipped: sender is not a supported bank');

                continue;
            }

            $rawEmail = RawEmail::storeFetched($user->id, null, $message);

            if (! $rawEmail->wasRecentlyCreated) {
                $this->line('  skipped: already imported');

                continue;
            }

            ParseRawEmail::dispatchSync($rawEmail);
            $rawEmail->refresh();
            $imported++;

            $this->line($rawEmail->parse_error ? "  <fg=red>failed: {$rawEmail->parse_error}</>" : '  parsed');
        }

        $this->comment("Imported {$imported} of {$files->count()} files.");

        return self::SUCCESS;
    }
}
