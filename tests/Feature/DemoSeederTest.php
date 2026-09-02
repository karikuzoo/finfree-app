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

        // Grafik pertumbuhan aset kini dihitung PER TUJUAN, bukan sebagai satu
        // deret untuk seluruh akun. Seeder menyebar setoran ke belakang selama
        // berbulan-bulan justru supaya grafiknya punya kurva — satu titik saja
        // membuat grafiknya tidak berguna.
        $this->assertGreaterThan(1, count($ringkasan['goals'][0]['asset_growth_series']));
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
