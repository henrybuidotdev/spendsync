<?php

namespace App\Providers;

use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\WebklexGmailClient;
use App\Parsing\ParserRegistry;
use App\Support\Demo;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class, fn () => ParserRegistry::fromConfig());

        $this->app->bind(MailboxClient::class, WebklexGmailClient::class);
    }

    public function boot(): void
    {
        Event::listen(['eloquent.saving: *', 'eloquent.deleting: *'], function (string $event, array $payload): void {
            $model = $payload[0];

            if ($model instanceof Model) {
                Demo::ensureWritable($model);
            }
        });
    }
}
