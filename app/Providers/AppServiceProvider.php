<?php

namespace App\Providers;

use App\Parsing\ParserRegistry;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(ParserRegistry::class, fn () => ParserRegistry::fromConfig());
    }

    public function boot(): void
    {
        //
    }
}
