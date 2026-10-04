<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Session\TokenMismatchException;
use Illuminate\Foundation\Http\Middleware\ValidateCsrfToken;
use Tests\TestCase;

/**
 * Token CSRF kedaluwarsa (419) pada kiriman dari halaman Inertia — lihat
 * withExceptions di bootstrap/app.php.
 *
 * Test Laravel mematikan pemeriksaan CSRF, jadi middleware CSRF diganti
 * tiruan yang menolak setiap kiriman — di POSISI yang sama dengan aslinya,
 * yaitu SEBELUM pemeriksaan login. Urutan itulah yang membuat tamu yang
 * sesinya habis mendapat 419, bukan langsung dialihkan ke halaman masuk.
 */
class ExpiredSessionTest extends TestCase
{
    use RefreshDatabase;

    private const HALAMAN = 'http://localhost/tujuan/7/ubah';

    protected function setUp(): void
    {
        parent::setUp();

        $this->app->instance(ValidateCsrfToken::class, new class
        {
            public function handle($request, $next)
            {
                if ($request->isMethodSafe()) {
                    return $next($request);
                }

                throw new TokenMismatchException('CSRF token mismatch.');
            }
        });
    }

    private function inertia(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Referer' => self::HALAMAN];
    }

    /** Kasus pengguna 4 Okt 2026: sesi habis karena halaman terlalu lama terbuka. */
    public function test_sesi_habis_diantar_masuk_lalu_kembali_ke_halaman_tadi(): void
    {
        $this->patch('/tujuan/7', [], $this->inertia())
            ->assertRedirect(route('login'))
            ->assertSessionHas('status', fn ($pesan) => str_contains($pesan, 'Sesi Anda berakhir'));

        $this->assertSame(self::HALAMAN, session('url.intended'));
    }

    public function test_masih_masuk_kembali_ke_halaman_tadi_dengan_pemberitahuan(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/tujuan/7', [], $this->inertia())
            // 303 supaya browser mengikuti dengan GET, bukan mengulang PATCH.
            ->assertStatus(303)
            ->assertRedirect(self::HALAMAN)
            ->assertSessionHas('notice');
    }

    /** Tamu di kalkulator publik tidak dilempar ke halaman masuk. */
    public function test_tamu_di_halaman_publik_kembali_ke_halamannya(): void
    {
        $this->post(route('calculator.loan.health'), [], [...$this->inertia(), 'Referer' => 'http://localhost/kalkulator/pinjaman?principal=1'])
            ->assertRedirect('http://localhost/kalkulator/pinjaman?principal=1')
            ->assertSessionHas('notice');
    }

    public function test_referer_situs_lain_tidak_diikuti(): void
    {
        $this->actingAs(User::factory()->create())
            ->patch('/tujuan/7', [], [...$this->inertia(), 'Referer' => 'https://situs-lain.test/jebakan'])
            ->assertRedirect(url('/'));
    }

    public function test_kiriman_bukan_inertia_tetap_mendapat_halaman_419(): void
    {
        $this->patch('/tujuan/7')
            ->assertStatus(419)
            ->assertSee('Sesi Anda sudah kedaluwarsa');
    }

    public function test_pemberitahuan_dibagikan_ke_halaman(): void
    {
        $this->actingAs(User::factory()->create())
            ->withSession(['notice' => 'Kirim ulang.'])
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('notice', 'Kirim ulang.'));
    }
}
