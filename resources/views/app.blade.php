<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Laravel') }}</title>

        <meta name="color-scheme" content="dark">
        <meta name="theme-color" content="#0B0C0B">

        <!-- Fonts — Plus Jakarta Sans untuk teks, JetBrains Mono untuk angka finansial -->
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,500,600,700|jetbrains-mono:500,700&display=swap" rel="stylesheet" />

        <!-- Scripts -->
        @routes
        @viteReactRefresh
        @vite(['resources/js/app.jsx', "resources/js/Pages/{$page['component']}.jsx"])
        @inertiaHead
    </head>
    <body class="bg-bg-base font-sans text-text-primary antialiased">
        @inertia
    </body>
</html>
