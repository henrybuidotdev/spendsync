<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
    </head>
    <body class="bg-slate-50 font-sans text-slate-900 antialiased">
        <div class="mx-auto min-h-screen max-w-lg bg-slate-50 pb-24">
            @if(\App\Support\Demo::isActive() && request()->routeIs('transactions*', 'reports'))
                <div role="status" class="flex flex-wrap items-center justify-between gap-x-3 gap-y-1 bg-amber-100 px-4 py-2 text-sm text-amber-900">
                    <p>{{ __('You are viewing demo data. Changes are not saved.') }}</p>
                    <form method="POST" action="{{ route('demo.stop') }}">
                        @csrf
                        @method('DELETE')
                        <button type="submit" class="inline-flex min-h-11 items-center font-semibold underline focus:outline-none focus-visible:ring-2 focus-visible:ring-amber-600">{{ __('Back to my data') }}</button>
                    </form>
                </div>
            @endif

            @if(isset($header))
                <header class="sticky top-0 z-10 border-b border-slate-200 bg-white/95 px-4 py-3 backdrop-blur">
                    {{ $header }}
                </header>
            @endif

            <main class="px-4 py-4">
                {{ $slot }}
            </main>
        </div>

        @if(\App\Support\Demo::isActive())
            <div role="alert" x-data="{ shown: false, timer: null }" x-cloak x-show="shown" x-transition.opacity.duration.300ms
                 @demo-read-only.window="shown = true; clearTimeout(timer); timer = setTimeout(() => shown = false, 8000)"
                 class="fixed inset-x-4 bottom-[calc(5rem+env(safe-area-inset-bottom))] z-30 rounded-2xl bg-amber-50 px-4 py-3 text-sm text-amber-900 shadow-lg ring-1 ring-amber-200 sm:left-auto sm:right-6 sm:w-96">
                {{ __('This is demo data, so changes can\'t be saved or deleted. Switch back to your account to make changes.') }}
                <form method="POST" action="{{ route('demo.stop') }}" class="inline">
                    @csrf
                    @method('DELETE')
                    <button type="submit" class="font-semibold underline">{{ __('Back to my data') }}</button>
                </form>
            </div>
        @endif

        @persist('navigation')
            <livewire:layout.navigation />
        @endpersist
    </body>
</html>
