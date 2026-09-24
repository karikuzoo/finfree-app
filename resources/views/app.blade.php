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

        {{-- Tidak ada font web sama sekali. Teks dan angka sama-sama memakai font
             sistem (lihat tailwind.config.js); angka dirapikan lewat .num-tabular,
             yang mengatur LEBAR digit, bukan jenis hurufnya. Plus Jakarta Sans dan
             JetBrains Mono dulu diunduh tiap muat halaman padahal praktis tidak
             terpakai — dan mono yang hanya dipakai di dua tempat justru membuat
             angka yang sama terlihat beda antar halaman. --}}

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
