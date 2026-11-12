<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}" class="[scrollbar-gutter:stable]">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto max-w-md px-4 pb-8 pt-16 sm:pt-24">
            @php([$brandHead, $brandTail] = array_pad(\Illuminate\Support\Str::ucsplit(config('app.name')), 2, ''))
            <div class="mb-8">
                <a href="/" aria-label="{{ config('app.name') }}" class="inline-flex items-center gap-2.5 text-2xl tracking-tighter text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2" wire:navigate>
                    <img src="/icon.svg" alt="" class="h-10 w-10 rounded-[22%]">
                    <span aria-hidden="true"><span class="font-bold">{{ $brandHead }}</span><span class="font-normal">{{ $brandTail }}</span></span>
                </a>
                <p class="mt-3 text-slate-600">{{ __('Auto-sync your spending from bank emails.') }}</p>
            </div>

            <div>
                {{ $slot }}
            </div>
        </div>
    </body>
</html>
