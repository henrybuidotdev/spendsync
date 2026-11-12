@props(['title', 'sections'])

<!DOCTYPE html>
<html lang="{{ app()->getLocale() }}">
    <head>
        @include('partials.head', ['title' => $title])
    </head>
    <body class="bg-slate-100 font-sans text-slate-900 antialiased">
        <main class="mx-auto max-w-md space-y-4 px-4 py-6">
            <a href="{{ url('/') }}" class="inline-flex min-h-11 items-center font-medium text-emerald-700 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500">‹ {{ config('app.name') }}</a>
            <h1 class="text-2xl font-semibold tracking-tight">{{ $title }}</h1>

            {{ $intro }}

            @foreach($sections as $heading => $items)
                <section class="rounded-2xl bg-white p-4 shadow-sm">
                    <h2 class="font-semibold">{{ $heading }}</h2>
                    <ul class="mt-2 list-disc space-y-1.5 pl-5 text-sm text-slate-700">
                        @foreach($items as $item)
                            <li>{{ $item }}</li>
                        @endforeach
                    </ul>
                </section>
            @endforeach

            {{ $slot }}
        </main>
    </body>
</html>
