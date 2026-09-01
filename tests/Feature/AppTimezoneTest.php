<?php

namespace Tests\Feature;

use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Menjaga zona waktu aplikasi tetap WIB.
 *
 * Bawaan Laravel adalah UTC, yang tertinggal 7 jam dari WIB. Selama aplikasi
 * berjalan di UTC, setiap hari antara pukul 00.00 dan 07.00 WIB ia masih
 * menganggap "hari ini" adalah kemarin — lingkaran hari ini di kalender
 * meleset, hitungan hari beruntun bisa putus padahal pengguna menyetor, dan
 * pengingat malam terbaca belum lewat padahal sudah.
 *
 * Kesalahannya tidak pernah memunculkan error; ia hanya salah, dan hanya pada
 * sebagian jam dalam sehari. Nilainya juga mudah kembali ke UTC diam-diam saat
 * `config/app.php` ditimpa mengikuti versi baru Laravel — karena itu dikunci
 * di sini.
 */
class AppTimezoneTest extends TestCase
{
    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_zona_waktu_aplikasi_adalah_wib(): void
    {
        $this->assertSame('Asia/Jakarta', config('app.timezone'));
        $this->assertSame('Asia/Jakarta', date_default_timezone_get());
    }

    /**
     * Inti persoalannya: pada dini hari WIB, tanggal menurut UTC masih hari
     * sebelumnya. Test ini gagal bila aplikasi kembali berjalan di UTC.
     */
    public function test_tengah_malam_wib_sudah_terhitung_hari_berikutnya(): void
    {
        // 2026-09-01 17:30 UTC = 2026-09-02 00:30 WIB.
        Carbon::setTestNow(Carbon::parse('2026-09-01 17:30:00', 'UTC'));

        $this->assertSame(
            '2026-09-02',
            Carbon::now()->setTimezone(config('app.timezone'))->toDateString(),
            'Pukul 00.30 WIB seharusnya sudah tanggal 2, bukan masih tanggal 1.',
        );
    }

    /**
     * Selisih WIB terhadap UTC selalu +7 jam sepanjang tahun — Indonesia tidak
     * menerapkan waktu musim panas. Bila selisihnya berubah, berarti zonanya
     * tergeser ke zona lain, bukan sekadar berganti nama.
     */
    public function test_selisihnya_tujuh_jam_dari_utc(): void
    {
        $selisih = Carbon::now(config('app.timezone'))->utcOffset();

        $this->assertSame(7 * 60, $selisih, 'WIB harus +07:00 terhadap UTC.');
    }

    /**
     * Berlaku sepanjang tahun, bukan hanya saat test dijalankan — sekaligus
     * memastikan tidak ada zona ber-DST yang menyelinap masuk.
     */
    public function test_tidak_ada_pergeseran_waktu_musim_panas(): void
    {
        foreach (['2026-01-15 12:00:00', '2026-07-15 12:00:00'] as $waktu) {
            $this->assertSame(
                7 * 60,
                Carbon::parse($waktu, config('app.timezone'))->utcOffset(),
                "Selisih pada {$waktu} seharusnya tetap +07:00.",
            );
        }
    }
}
