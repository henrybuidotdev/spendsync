<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#F2F0E5" media="(prefers-color-scheme: light)">
<meta name="theme-color" content="#100F0F" media="(prefers-color-scheme: dark)">
<link rel="preconnect" href="https://fonts.bunny.net">
<link href="https://fonts.bunny.net/css?family=inter:400,500,600,700&display=swap" rel="stylesheet">
<script>
    // Runs before paint so a saved theme never flashes; wire:navigate resets <html> attributes, so re-apply after each visit.
    (() => {
        const applyTheme = () => {
            try {
                const theme = localStorage.getItem('theme');

                if (theme) {
                    document.documentElement.dataset.theme = theme;
                }
            } catch (e) {}
        };

        applyTheme();
        document.addEventListener('livewire:navigated', applyTheme);
    })();
</script>
<meta name="apple-mobile-web-app-capable" content="yes">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icon.svg">
@vite(['resources/css/app.css', 'resources/js/app.js'])
