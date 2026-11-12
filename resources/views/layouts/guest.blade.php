<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto flex min-h-screen max-w-md flex-col justify-center px-4 py-8">
            @php([$brandHead, $brandTail] = array_pad(\Illuminate\Support\Str::ucsplit(config('app.name')), 2, ''))
            <a href="/" aria-label="{{ config('app.name') }}" class="mb-6 flex items-center justify-center gap-2.5 text-2xl tracking-tighter text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950 focus-visible:ring-offset-2" wire:navigate>
                <img src="/icon.svg" alt="" class="h-10 w-10 rounded-[22%]">
                <span aria-hidden="true"><span class="font-bold">{{ $brandHead }}</span><span class="font-normal">{{ $brandTail }}</span></span>
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
