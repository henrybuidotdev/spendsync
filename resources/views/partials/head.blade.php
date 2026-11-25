<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#FFFFFF" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#171717" media="(prefers-color-scheme: dark)">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=be-vietnam-pro:400,500,600,700|ibm-plex-mono:400,500,600&display=swap" rel="stylesheet">
<script>
    // Runs before paint so the saved theme never flashes; wire:navigate resets <html> attributes, so re-apply after each visit.
    window.appTheme = @js(($theme ?? \App\Enums\Theme::System)->value);

    (() => {
        const applyTheme = () => window.appTheme === 'system'
            ? delete document.documentElement.dataset.theme
            : document.documentElement.dataset.theme = window.appTheme;

        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    })();
</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
@php($description ??= __('Connect Gmail once. Whenever your bank emails you about a transaction, SpendSync records the amount, the payee and the time.'))
<meta name="description" content="{{ $description }}">
<meta property="og:type" content="website">
<meta property="og:site_name" content="{{ config('app.name') }}">
<meta property="og:title" content="{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}">
<meta property="og:description" content="{{ $description }}">
<meta property="og:url" content="{{ url()->current() }}">
<meta property="og:image" content="{{ asset('og-image.png') }}">
<meta property="og:image:width" content="1200">
<meta property="og:image:height" content="630">
<meta name="twitter:card" content="summary_large_image">
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/apple-touch-icon.png">
@vite(['resources/css/app.css', 'resources/js/app.js'])
