<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-8">
            <a href="/" class="mb-6 flex items-center justify-center gap-2 text-2xl font-bold text-emerald-700" wire:navigate>
                <img src="/icon.svg" alt="" class="h-10 w-10">
                {{ config('app.name') }}
            </a>

            <div class="rounded-2xl bg-white px-5 py-6 shadow-sm">
                {{ $slot }}
            </div>

            <p class="mt-6 text-center text-sm text-slate-500">
                <a href="{{ route('privacy') }}" class="underline hover:text-slate-700">{{ __('Quyền riêng tư') }}</a>
            </p>
        </div>
    </body>
</html>
