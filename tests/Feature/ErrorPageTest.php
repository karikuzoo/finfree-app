<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\View;
use Tests\TestCase;

/**
 * Halaman error gampang rusak diam-diam: ia hampir tidak pernah dibuka saat
 * pengembangan, dan kegagalannya tidak memunculkan error apa pun — pengguna
 * cuma melihat tampilan bawaan Laravel yang polos, atau layar putih.
 *
 * Test ini menjaga tiga hal: berkasnya ada, isinya berbahasa Indonesia dan
 * memakai tema Malam, serta benar-benar dipakai Laravel saat error terjadi.
 */
class ErrorPageTest extends TestCase
{
    use RefreshDatabase;

    /** Setiap kode yang punya halaman khusus. */
    public static function kodeError(): array
    {
        return [
            '403' => ['403'],
            '404' => ['404'],
            '419' => ['419'],
            '429' => ['429'],
            '500' => ['500'],
            '503' => ['503'],
        ];
    }

    /**
     * @dataProvider kodeError
     */
    public function test_berkas_halaman_error_tersedia(string $kode): void
    {
        $this->assertTrue(
            View::exists("errors.{$kode}"),
            "Halaman error {$kode} tidak ada — Laravel akan memakai tampilan bawaannya.",
        );
    }

    /**
     * @dataProvider kodeError
     */
    public function test_halaman_error_memakai_tema_malam_dan_bahasa_indonesia(string $kode): void
    {
        $html = View::make("errors.{$kode}", ['exception' => null])->render();

        // Warna latar tema Malam — bukti layout-nya benar-benar terpakai.
        $this->assertStringContainsString('#0B0C0B', $html);

        // Bahasa halaman, bukan bawaan Laravel yang berbahasa Inggris.
        $this->assertStringContainsString('lang="id"', $html);

        // Selalu ada jalan keluar; halaman buntu memaksa pengguna menekan tombol back.
        $this->assertStringContainsString('btn--primary', $html);

        $this->assertStringContainsString($kode, $html);
    }

    public function test_alamat_yang_tidak_ada_menampilkan_halaman_404_kustom(): void
    {
        $response = $this->get('/alamat-yang-pasti-tidak-ada-'.__FUNCTION__);

        $response->assertNotFound();
        $response->assertSee('Halaman tidak ditemukan');
        $response->assertSee('FinGoal');
        $response->assertDontSee('Not Found', false);
    }

    /**
     * Tamu diantar ke beranda, pengguna yang sudah masuk diantar ke dashboard —
     * mengirim orang yang sudah login ke halaman depan pemasaran adalah jalan
     * memutar yang tidak perlu.
     */
    public function test_tautan_keluar_menyesuaikan_status_login(): void
    {
        $this->get('/alamat-yang-tidak-ada-tamu')
            ->assertSee('Kembali ke Beranda');

        $this->actingAs(User::factory()->create())
            ->get('/alamat-yang-tidak-ada-pengguna')
            ->assertSee('Kembali ke Dashboard');
    }

    /**
     * Halaman error TIDAK boleh bergantung pada hasil build Vite. Bila error-nya
     * justru berasal dari manifest atau chunk yang rusak, halaman error berbasis
     * React ikut gagal dan pengguna melihat layar putih — tepat ketika halaman
     * error paling dibutuhkan.
     */
    public function test_halaman_error_tidak_bergantung_pada_build_frontend(): void
    {
        $html = View::make('errors.500', ['exception' => null])->render();

        $this->assertStringNotContainsString('/build/assets/', $html);
        $this->assertStringNotContainsString('@vite', $html);
        $this->assertStringNotContainsString('data-page', $html);
    }

    public function test_halaman_error_tidak_diindeks_mesin_pencari(): void
    {
        $html = View::make('errors.404', ['exception' => null])->render();

        $this->assertStringContainsString('noindex', $html);
    }
}
