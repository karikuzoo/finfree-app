<?php

namespace App\Jobs;

use App\Services\NewsIngestService;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

/**
 * Pengambilan berita terjadwal (PRD FR-18), dijadwalkan tiap jam di
 * routes/console.php.
 *
 * `ShouldBeUnique`: bila satu pengambilan macet dan jadwal berikutnya tiba,
 * yang kedua tidak ikut berjalan dan memakan kuota yang sama dua kali.
 *
 * Tidak dicoba ulang otomatis (`$tries = 1`). Kegagalan paling umum adalah
 * kuota habis, dan mencoba lagi lima menit kemudian hanya membakar kredit
 * yang sudah tidak ada — jadwal jam berikutnya sudah menjadi percobaan ulang.
 */
class FetchLatestNewsJob implements ShouldQueue, ShouldBeUnique
{
    use Queueable;

    public int $tries = 1;

    public int $uniqueFor = 1800;

    public function handle(NewsIngestService $berita): void
    {
        $berita->run();
    }
}
