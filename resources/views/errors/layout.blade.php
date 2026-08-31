{{--
    Kerangka bersama seluruh halaman error.

    SENGAJA memakai Blade dengan CSS tertanam, bukan halaman Inertia/React.
    Setiap halaman Inertia memuat chunk React lewat @vite; bila error-nya justru
    berasal dari build atau manifest yang rusak, halaman error berbasis React
    ikut gagal dan pengguna melihat layar putih — tepat di saat halaman error
    paling dibutuhkan. Berkas ini tidak bergantung pada hasil build sama sekali,
    jadi ia tetap tampil meski seluruh pipeline frontend mati.

    Warnanya disalin dari tema "Malam" di tailwind.config.js. Duplikasi ini
    disengaja dan merupakan harga dari kemandirian di atas — bila token tema
    berubah, berkas ini ikut disesuaikan manual.
--}}
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="robots" content="noindex">

    <title>@yield('judul') · {{ config('app.name', 'FinGoal') }}</title>

    <meta name="color-scheme" content="dark">
    <meta name="theme-color" content="#0B0C0B">

    {{-- Font boleh gagal dimuat tanpa merusak apa pun — tumpukan cadangannya nyata. --}}
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=plus-jakarta-sans:400,600,700,800|jetbrains-mono:700&display=swap" rel="stylesheet">

    <style>
        :root {
            --bg-base: #0B0C0B;
            --bg-card: #16181A;
            --bg-alt: #252825;
            --line: #282C28;
            --line-strong: #666D61;
            --lime-500: #CFF04A;
            --lime-400: #DEF76F;
            --lime-soft: #1E2610;
            --on-primary: #10130A;
            --tx-1: #F0F1EC;
            --tx-2: #A3A99E;
            --tx-3: #8B917F;
            --sans: 'Plus Jakarta Sans', ui-sans-serif, system-ui, -apple-system, 'Segoe UI', sans-serif;
            --mono: 'JetBrains Mono', ui-monospace, Consolas, monospace;
        }

        * { box-sizing: border-box; }

        html, body { height: 100%; }

        body {
            margin: 0;
            background: var(--bg-base);
            color: var(--tx-1);
            font-family: var(--sans);
            font-size: 16px;
            line-height: 1.6;
            -webkit-font-smoothing: antialiased;
            display: flex;
            flex-direction: column;
        }

        .bar {
            padding: 20px 24px;
            border-bottom: 1px solid var(--line);
        }

        .mark {
            display: inline-flex;
            align-items: center;
            gap: 9px;
            font-weight: 800;
            font-size: 17px;
            letter-spacing: -.01em;
            color: var(--tx-1);
            text-decoration: none;
        }
        .mark svg { color: var(--lime-500); }
        .mark:focus-visible { outline: 2px solid var(--lime-500); outline-offset: 4px; border-radius: 4px; }

        main {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 48px 24px 72px;
        }

        .panel {
            width: 100%;
            max-width: 520px;
            text-align: center;
        }

        .code {
            font-family: var(--mono);
            font-size: 13px;
            font-weight: 700;
            letter-spacing: .16em;
            color: var(--lime-500);
            background: var(--lime-soft);
            border: 1px solid var(--line-strong);
            border-radius: 999px;
            padding: 6px 15px;
            display: inline-block;
            margin-bottom: 24px;
        }

        h1 {
            font-size: clamp(26px, 5vw, 34px);
            font-weight: 800;
            letter-spacing: -.025em;
            line-height: 1.2;
            margin: 0 0 14px;
            text-wrap: balance;
        }

        .say {
            margin: 0 auto;
            max-width: 42ch;
            color: var(--tx-2);
            font-size: 16px;
        }

        .acts {
            margin-top: 32px;
            display: flex;
            flex-wrap: wrap;
            gap: 12px;
            justify-content: center;
        }

        .btn {
            display: inline-block;
            border-radius: 10px;
            padding: 12px 22px;
            font-size: 14.5px;
            font-weight: 700;
            text-decoration: none;
            border: 1px solid transparent;
            cursor: pointer;
            font-family: inherit;
            transition: background-color .15s, color .15s, border-color .15s;
        }
        .btn--primary { background: var(--lime-500); color: var(--on-primary); }
        .btn--primary:hover { background: var(--lime-400); }
        .btn--ghost { border-color: var(--line-strong); color: var(--tx-2); background: transparent; }
        .btn--ghost:hover { color: var(--tx-1); border-color: var(--tx-3); }
        .btn:focus-visible { outline: 2px solid var(--lime-500); outline-offset: 3px; }

        .hint {
            margin: 26px auto 0;
            max-width: 46ch;
            font-size: 13px;
            color: var(--tx-3);
            padding-top: 20px;
            border-top: 1px solid var(--line);
        }

        @media (prefers-reduced-motion: reduce) {
            * { transition: none !important; }
        }
    </style>
</head>
<body>

    <div class="bar">
        <a class="mark" href="{{ url('/') }}">
            <svg width="21" height="21" viewBox="0 0 32 32" fill="none" stroke="currentColor"
                 stroke-width="2" stroke-linecap="round" aria-hidden="true">
                <circle cx="16" cy="16" r="12"></circle>
                <circle cx="16" cy="16" r="6.5"></circle>
                <circle cx="16" cy="16" r="1.5"></circle>
            </svg>
            {{ config('app.name', 'FinGoal') }}
        </a>
    </div>

    <main>
        <div class="panel">
            <span class="code">@yield('kode')</span>

            <h1>@yield('judul')</h1>

            <p class="say">@yield('penjelasan')</p>

            <div class="acts">
                @hasSection('aksi')
                    @yield('aksi')
                @else
                    {{-- Pengguna yang sudah masuk lebih berguna diantar ke dashboard
                         daripada ke halaman depan pemasaran. --}}
                    @auth
                        <a class="btn btn--primary" href="{{ url('/dashboard') }}">Kembali ke Dashboard</a>
                    @else
                        <a class="btn btn--primary" href="{{ url('/') }}">Kembali ke Beranda</a>
                    @endauth
                    <a class="btn btn--ghost" href="{{ url('/kalkulator') }}">Buka Kalkulator</a>
                @endif
            </div>

            @hasSection('petunjuk')
                <p class="hint">@yield('petunjuk')</p>
            @endif
        </div>
    </main>

</body>
</html>
