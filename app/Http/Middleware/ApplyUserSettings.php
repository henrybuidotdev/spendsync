<?php

namespace App\Http\Middleware;

use App\Enums\Locale;
use App\Enums\Theme;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cookie;
use Illuminate\Support\Facades\View;
use Symfony\Component\HttpFoundation\Response;

/**
 * Logged-in users get their saved settings; the same values are mirrored into cookies
 * so pages shown after logout (login, sign up) keep the last language and theme.
 */
class ApplyUserSettings
{
    private const COOKIE_MINUTES = 60 * 24 * 365;

    public function handle(Request $request, Closure $next): Response
    {
        $settings = $request->user()?->settings;

        $locale = $settings?->locale ?? Locale::tryFrom((string) $request->cookie('locale')) ?? Locale::Vi;
        $theme = $settings?->theme ?? Theme::tryFrom((string) $request->cookie('theme')) ?? Theme::System;

        app()->setLocale($locale->value);
        View::share('theme', $theme);

        if ($settings === null) {
            return $next($request);
        }

        if ($request->cookie('locale') !== $locale->value) {
            Cookie::queue('locale', $locale->value, self::COOKIE_MINUTES);
        }

        if ($request->cookie('theme') !== $theme->value) {
            Cookie::queue('theme', $theme->value, self::COOKIE_MINUTES);
        }

        return $next($request);
    }
}
