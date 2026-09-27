<?php

use App\Jobs\FetchLatestNewsJob;
use App\Services\NewsIngestService;
use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * Berita (PRD FR-18). Satu kueri per kategori per jam = 5 × 24 = 120 kredit
 * dari 200 per hari pada paket gratis NewsData.io — sisanya cadangan untuk
 * `news:fetch` manual dan percobaan.
 */
Schedule::job(new FetchLatestNewsJob)->hourly();

/*
 * Mengambil berita SEKARANG, tanpa antrean — untuk mengisi cache pertama kali
 * dan untuk memeriksa apakah kuncinya bekerja. Memakai 5 kredit.
 */
Artisan::command('news:fetch', function (NewsIngestService $berita) {
    $hasil = $berita->run();

    $this->info("Tersimpan: {$hasil['stored']} · dibuang: {$hasil['excluded']} · dipangkas: {$hasil['pruned']}");

    foreach ($hasil['failed'] as $kategori => $alasan) {
        $this->error("Gagal ({$kategori}): {$alasan}");
    }

    return $hasil['failed'] === [] ? 0 : 1;
})->purpose('Ambil berita terbaru dari NewsData.io ke cache');
