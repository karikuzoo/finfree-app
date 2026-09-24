<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">

        <title inertia>{{ config('app.name', 'Arus') }}</title>

        <meta name="color-scheme" content="dark">
        <meta name="theme-color" content="#101719">

        <link rel="icon" type="image/svg+xml" href="{{ asset('favicon.svg') }}">
        <meta name="description" content="Arus — rencanakan tujuan, pantau dana, dan wujudkan masa depan finansialmu.">

        {{-- JetBrains Mono SAJA. Teks memakai font sistem (lihat tailwind.config.js),
             jadi Plus Jakarta Sans tidak lagi diunduh — dulu ikut termuat padahal
             tidak pernah dipakai sejak palet Arus. Mono tetap perlu diunduh karena
             angka rupiah memakainya lewat .num-tabular + font-mono. --}}
        <link rel="preconnect" href="https://fonts.bunny.net">
        <link href="https://fonts.bunny.net/css?family=jetbrains-mono:500,700&display=swap" rel="stylesheet" />

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
