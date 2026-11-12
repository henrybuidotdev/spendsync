<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto min-h-screen max-w-md bg-slate-50 pb-24">
            @if(isset($header))
                <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
                    {{ $header }}
                </header>
            @endif

            <main class="px-4 py-4">
                {{ $slot }}
            </main>
        </div>

        <livewire:layout.navigation />
    </body>
</html>
