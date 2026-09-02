<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\GoalContribution;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mencatat setoran lewat kalender (PRD FR-32).
 *
 * Form ringkas di Dashboard hanya bisa mencatat untuk hari berjalan. Kalender
 * mengembalikan kemampuan memilih tanggal — tanpa mengetik tanggal sama sekali,
 * karena tanggalnya ditentukan oleh sel yang diklik.
 *
 * Yang dijaga di sini adalah kontrak servernya. Tanggal masa depan ditolak, dan
 * setoran bertanggal mundur muncul di tanggal yang benar pada kalender — bukan
 * tersedot ke hari ini.
 */
class CalendarContributionTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTujuan(User $user, string $nama = 'Dana Darurat')
    {
        return $user->goals()->create([
            'type' => GoalType::Emergency->value,
            'name' => $nama,
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 4,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);
    }

    public function test_setoran_bertanggal_mundur_jatuh_di_tanggal_yang_dipilih(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->post(route('goals.contributions.store', $goal), [
                'amount' => 750000,
                'contributed_on' => '2026-09-14',
                'note' => 'Sisa gajian',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame('2026-09-14', GoalContribution::sole()->contributed_on->toDateString());

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-09-01'));

        $hari = collect($kalender['contributions'])->firstWhere('date', '2026-09-14');

        $this->assertNotNull($hari, 'Setoran tidak muncul di tanggal yang dipilih.');
        $this->assertSame(750000.0, $hari['amount']);
        $this->assertSame('Sisa gajian', $hari['entries'][0]['note']);
    }

    /**
     * Uang yang belum disetor bukan setoran. Form di kalender menyembunyikan
     * dirinya pada tanggal masa depan, tetapi penjaga sesungguhnya ada di sini.
     */
    public function test_menolak_setoran_bertanggal_masa_depan(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->post(route('goals.contributions.store', $goal), [
                'amount' => 500000,
                'contributed_on' => '2026-09-21',
            ])
            ->assertSessionHasErrors('contributed_on');

        $this->assertSame(0, GoalContribution::count());
    }

    public function test_setoran_hari_ini_tetap_diterima(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->post(route('goals.contributions.store', $goal), [
                'amount' => 500000,
                'contributed_on' => '2026-09-20',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1, GoalContribution::count());
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tidak_bisa_mencatat_setoran_ke_tujuan_orang_lain(): void
    {
        $goalOrangLain = $this->buatTujuan(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('goals.contributions.store', $goalOrangLain), [
                'amount' => 500000,
                'contributed_on' => Carbon::today()->toDateString(),
            ])
            ->assertForbidden();

        $this->assertSame(0, GoalContribution::count());
    }

    /**
     * Grafik pertumbuhan aset membentang dari bulan tujuan dibuat. Setoran
     * bertanggal mundur lewat kalender tetap harus ikut terhitung — kalau
     * tidak, titik akhir grafik bertentangan dengan kartu progres di layar
     * yang sama.
     */
    public function test_setoran_lewat_kalender_ikut_masuk_grafik(): void
    {
        Carbon::setTestNow('2026-09-20 10:00:00');

        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)->post(route('goals.contributions.store', $goal), [
            'amount' => 1500000,
            'contributed_on' => '2026-09-03',
        ]);

        $ringkasan = app(DashboardSummaryService::class)->forUser($user)['goals'][0];
        $titikAkhir = end($ringkasan['asset_growth_series'])['cumulative_amount'];

        $this->assertSame(
            $ringkasan['current_amount'],
            $titikAkhir,
            'Titik akhir grafik harus sama dengan nominal di kartu progres.',
        );
    }
}
