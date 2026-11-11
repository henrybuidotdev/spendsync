<?php

namespace App\Providers;

use App\Mail\Inbox\MailboxClient;
use App\Mail\Inbox\WebklexGmailClient;
use App\Parsing\ParserRegistry;
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
        //
    }
}
