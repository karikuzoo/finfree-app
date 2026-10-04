<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that is loaded on the first page visit.
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determine the current asset version.
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        $user = $request->user();

        // Selama login, riwayat browser dienkripsi. Inertia menyimpan props
        // setiap halaman di history.state, jadi tanpa ini orang lain di
        // komputer yang sama bisa menekan Back sesudah pengguna logout dan
        // melihat saldo, transaksi, dan utangnya. Kuncinya dibuang saat logout
        // atau hapus akun (Inertia::clearHistory di AuthenticatedSessionController
        // dan ProfileController::destroy), sehingga riwayat lama tak terbaca.
        //
        // Butuh secure context (window.crypto.subtle): HTTPS, localhost, atau
        // 127.0.0.1. Server produksi WAJIB HTTPS — itu pun sudah syarat untuk
        // aplikasi keuangan.
        //
        // Disetel di SETIAP permintaan — true atau false — bukan hanya saat
        // login: ResponseFactory hidup sepanjang proses PHP, dan penanda dari
        // permintaan sebelumnya ikut terbawa bila prosesnya dipakai ulang
        // (test, Octane).
        Inertia::encryptHistory($user !== null);

        return [
            ...parent::share($request),
            // Pemberitahuan sekali tampil (flash) untuk bingkai halaman — saat
            // ini hanya dari penanganan 419 di bootstrap/app.php.
            'notice' => fn () => $request->session()->get('notice'),
            'auth' => [
                // HANYA yang dipakai bingkai halaman (sidebar, topbar, sapaan).
                //
                // Prop bersama ikut tertanam di HTML setiap halaman (atribut
                // data-page) dan di riwayat browser. Sebelumnya seluruh model
                // User dikirim di sini — email, telepon, tanggal lahir,
                // kewarganegaraan, pekerjaan — ke SEMUA halaman, termasuk
                // kalkulator publik yang dibuka sambil login. Data identitas
                // kini hanya dikirim ke halaman Profil (ProfileController::edit,
                // prop `profile`), satu-satunya yang membutuhkannya (minimasi
                // data, UU 27/2022 PDP).
                'user' => $user ? [
                    'name' => $user->name,
                    'avatar_url' => $user->avatar_url,
                    'initials' => $user->initials,
                ] : null,
            ],
        ];
    }
}
