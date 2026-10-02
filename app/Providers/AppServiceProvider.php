<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Vite;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Vite::prefetch(concurrency: 3);

        $this->definePasswordRules();
        $this->restrictRouteIdsToNumbers();
        $this->allowWindowsEnvironmentVariablesThroughServeCommand();
    }

    /**
     * Parameter ID di alamat hanya menerima angka.
     *
     * Tanpa ini, `/rekening/1 OR 1=1` sampai ke basis data. Itu BUKAN SQL
     * injection — nilainya dikirim lewat binding — tetapi PostgreSQL menolak
     * teks sebagai ID bigint dan permintaannya berakhir 500, lengkap dengan
     * kueri SQL-nya bila APP_DEBUG menyala. Dengan pola ini, alamat seperti
     * itu langsung 404 sebelum menyentuh basis data. Ditemukan
     * RequestSecurityTest, 30 Sep 2026.
     *
     * Parameter baru yang berupa ID model wajib ditambahkan di sini —
     * RequestSecurityTest gagal bila ada parameter route yang belum dikenalnya.
     */
    private function restrictRouteIdsToNumbers(): void
    {
        Route::patterns(array_fill_keys(
            ['account', 'debt', 'transaction', 'financialGoal', 'reminder', 'calendarNote'],
            '[0-9]+',
        ));
    }

    /**
     * Aturan kata sandi untuk seluruh aplikasi.
     *
     * Disetel sekali di sini karena ketiga tempat yang memvalidasi kata sandi —
     * pendaftaran, reset lewat email, dan ubah kata sandi di halaman profil —
     * sama-sama memakai `Password::defaults()`. Menuliskan aturannya di
     * masing-masing controller berarti membuka celah: cukup satu tempat
     * tertinggal saat aturannya diperketat, dan pengguna bisa memakai jalur itu
     * untuk memasang kata sandi lemah.
     */
    private function definePasswordRules(): void
    {
        Password::defaults(fn () => Password::min(8)
            ->mixedCase()
            ->numbers()
            ->symbols());
    }

    /**
     * Membuat `php artisan serve` bisa jalan di Windows.
     *
     * ServeCommand membuang setiap environment variable yang tidak terdaftar
     * di ServeCommand::$passthroughVariables sebelum menjalankan server PHP.
     * Daftar itu menulis nama variabel Windows dengan huruf besar semua
     * (SYSTEMROOT), sementara Windows sendiri menyimpannya sebagai SystemRoot.
     * Karena in_array() peka huruf besar-kecil, variabelnya tidak cocok lalu
     * ikut dibuang — dan tanpa SystemRoot, winsock gagal membuka socket
     * sehingga server melapor "Failed to listen on 127.0.0.1:8000 (reason: ?)"
     * di setiap port yang dicoba.
     *
     * Ditambahkan hanya di Windows agar tidak mengubah perilaku di Linux/macOS
     * (misalnya saat nanti dijalankan di CI atau container).
     */
    private function allowWindowsEnvironmentVariablesThroughServeCommand(): void
    {
        if (PHP_OS_FAMILY !== 'Windows' || ! class_exists(ServeCommand::class)) {
            return;
        }

        foreach (['SystemRoot', 'SystemDrive', 'ComSpec', 'windir', 'TEMP', 'TMP'] as $variable) {
            if (! in_array($variable, ServeCommand::$passthroughVariables, true)) {
                ServeCommand::$passthroughVariables[] = $variable;
            }
        }
    }
}
