<?php

namespace App\Providers;

use Illuminate\Foundation\Console\ServeCommand;
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
        $this->allowWindowsEnvironmentVariablesThroughServeCommand();
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
        Password::defaults(fn () => Password::min(6)
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
