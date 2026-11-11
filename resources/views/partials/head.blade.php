<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
<meta name="csrf-token" content="{{ csrf_token() }}">
<meta name="theme-color" content="#059669">
<meta name="apple-mobile-web-app-capable" content="yes">
<title>{{ isset($title) ? $title.' · ' : '' }}{{ config('app.name') }}</title>
<link rel="manifest" href="/manifest.webmanifest">
<link rel="icon" href="/icon.svg" type="image/svg+xml">
<link rel="apple-touch-icon" href="/icon.svg">
@vite(['resources/css/app.css', 'resources/js/app.js'])
