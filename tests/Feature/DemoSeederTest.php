<?php

namespace Tests\Feature;

use App\Services\DashboardSummaryService;
use Database\Seeders\DemoSeeder;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga akun demo tetap berguna.
 *
 * Seeder yang rusak tidak pernah ketahuan sampai ada orang baru yang gagal
 * mencoba aplikasi — dan saat itu ia sudah kehilangan kesan pertama.
 */
class DemoSeederTest extends TestCase
{
    use RefreshDatabase;

    public function test_seeder_menghasilkan_akun_yang_siap_dipakai(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::where('email', DemoSeeder::EMAIL)->first();

        $this->assertNotNull($user, 'Akun demo tidak terbentuk.');
        $this->assertNotNull($user->email_verified_at, 'Akun demo harus sudah terverifikasi agar bisa langsung masuk dashboard.');
        $this->assertTrue(
            \Illuminate\Support\Facades\Hash::check(DemoSeeder::PASSWORD, $user->password),
            'Kata sandi demo tidak cocok dengan yang diumumkan seeder.',
        );
    }

    public function test_akun_demo_bisa_login_dan_membuka_dashboard(): void
    {
        $this->seed(DemoSeeder::class);

        $this->post(route('login'), [
            'email' => DemoSeeder::EMAIL,
            'password' => DemoSeeder::PASSWORD,
        ])->assertRedirect(route('dashboard', absolute: false));

        $this->assertAuthenticated();
        $this->get(route('dashboard'))->assertOk();
    }

    public function test_dashboard_terisi_data_bukan_empty_state(): void
    {
        $this->seed(DemoSeeder::class);

        $user = User::where('email', DemoSeeder::EMAIL)->first();
        $ringkasan = app(DashboardSummaryService::class)->forUser($user);

        $this->assertSame(3, $ringkasan['active_goals_count']);
        $this->assertGreaterThan(0, $ringkasan['total_assets']);
        $this->assertCount(3, $ringkasan['goals']);

        // Grafik pertumbuhan kekayaan dihitung dari riwayat transaksi seluruh
        // akun. Seeder menyebar transaksi ke belakang selama berbulan-bulan
        // justru supaya grafiknya punya kurva — deret yang datar membuatnya
        // tidak berguna, dan itu tidak akan terlihat dari jumlah titiknya saja.
        $deret = array_column($ringkasan['asset_growth_series']['monthly'], 'cumulative_amount');

        $this->assertCount(12, $deret);
        $this->assertGreaterThan($deret[0], end($deret), 'Grafiknya datar, seeder tidak menghasilkan kurva.');

        // Dana tujuan ditandai dari saldo rekening, jadi seluruh alokasi harus
        // muat di dalamnya — kalau tidak, LedgerGuard akan menolaknya dan
        // seeder gagal separuh jalan tanpa terlihat di sini.
        $this->assertGreaterThanOrEqual(
            $ringkasan['total_assets'],
            app(\App\Services\AccountBalanceService::class)->totalAssets($user),
        );
    }

    public function test_dijalankan_dua_kali_tidak_menggandakan_data(): void
    {
        $this->seed(DemoSeeder::class);
        $this->seed(DemoSeeder::class);

        $this->assertSame(1, User::where('email', DemoSeeder::EMAIL)->count());
        $this->assertSame(
            3,
            User::where('email', DemoSeeder::EMAIL)->first()->goals()->count(),
        );
    }
}
