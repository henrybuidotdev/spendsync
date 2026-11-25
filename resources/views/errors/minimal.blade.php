@php($description = match (trim(View::yieldContent('code'))) {
    '401' => __('Please sign in to view this page.'),
    '402' => __('A payment is required to view this page.'),
    '403' => __("You don't have permission to view this page."),
    '404' => __("The page you're looking for doesn't exist."),
    '419' => __('This page has expired. Please refresh and try again.'),
    '429' => __("You're sending too many requests. Please wait a moment and try again."),
    '503' => __("We're doing some maintenance. Please check back soon."),
    default => __('Something went wrong on our end. Please try again later.'),
})
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head', ['title' => View::yieldContent('title')])
    </head>
    <body class="bg-white font-sans text-slate-900 antialiased">
        @include('partials.sa')

        @php([$brandHead, $brandTail] = array_pad(\Illuminate\Support\Str::ucsplit(config('app.name')), 2, ''))
        <header class="absolute inset-x-0 top-0 mx-auto max-w-lg px-4 pt-6 lg:max-w-5xl lg:px-8">
            <a href="{{ url('/') }}" aria-label="{{ config('app.name') }}" class="inline-flex min-h-11 items-center gap-2.5 text-xl tracking-tighter text-slate-950 focus:outline-none focus-visible:ring-2 focus-visible:ring-slate-950">
                <img src="/icon.svg" alt="" class="h-8 w-8 rounded-[22%]">
                <span aria-hidden="true"><span class="font-bold">{{ $brandHead }}</span><span class="font-normal">{{ $brandTail }}</span></span>
            </a>
        </header>

        <main class="flex min-h-dvh flex-col items-center justify-center px-4 text-center">
            <p class="font-num text-8xl font-semibold tracking-tighter">@yield('code')</p>
            <h1 class="mt-4 max-w-sm text-lg text-slate-600">{{ $description }}</h1>
            <a href="{{ url('/') }}" class="mt-8 inline-flex min-h-11 items-center justify-center rounded-xl bg-black px-5 text-sm font-semibold text-white hover:bg-slate-900 focus:outline-none focus-visible:ring-2 focus-visible:ring-emerald-500 focus-visible:ring-offset-2">
                {{ __('Return to homepage') }}
            </a>
        </main>
    </body>
</html>
