<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Menjaga perhitungan rentang bulan di Dashboard dari luberan tanggal.
 *
 * PHP mengisi bagian tanggal yang tidak disebutkan dari hari ini, dan
 * operasi bulan pada tanggal 29–31 bisa meleset ke bulan berikutnya:
 * "31 September" tidak ada, jadi menjadi 1 Oktober. Seluruh test di sini
 * MEMBEKUKAN waktu di tanggal 31 — tanpa itu semuanya lolos pada 28 dari
 * 31 hari dalam sebulan, dan bug jenis ini kembali lolos ke pengguna.
 */
class DashboardMonthWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_grafik_pertumbuhan_aset_berakhir_di_bulan_berjalan(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        $series = $this->series();

        $this->assertCount(12, $series);
        $this->assertSame('2026-08', end($series)['month'], 'Bulan terakhir harus bulan berjalan, bukan bulan depan.');
        $this->assertSame('2025-09', $series[0]['month'], 'Jendela 12 bulan harus mulai September 2025.');
    }

    /**
     * Inti bug-nya: bulan pertama jendela dulu meleset, sehingga setoran di
     * bulan itu tidak ikut terhitung dan total kumulatifnya kurang.
     */
    public function test_setoran_di_bulan_pertama_jendela_ikut_terhitung(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        $user = User::factory()->create();
        $goal = $user->goals()->create([
            'type' => 'emergency',
            'name' => 'Dana Darurat',
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'estimated_return_rate' => 4,
            'status' => 'active',
        ]);

        $goal->contributions()->create([
            'amount' => 2000000,
            'contributed_on' => '2025-09-10',
        ]);

        $series = collect($this->series($user));

        $this->assertSame(2000000.0, $series->firstWhere('month', '2025-09')['cumulative_amount']);
        $this->assertSame(2000000.0, $series->last()['cumulative_amount']);
    }

    /**
     * Bulan dengan 31 hari tidak pernah meluber, jadi tanggal 30 adalah
     * pembanding yang berguna: hasilnya harus sama persis dengan tanggal 31.
     */
    public function test_hasilnya_sama_baik_diakses_tanggal_30_maupun_31(): void
    {
        Carbon::setTestNow('2026-08-30 09:00:00');
        $tanggal30 = array_column($this->series(), 'month');

        Carbon::setTestNow('2026-08-31 09:00:00');
        $tanggal31 = array_column($this->series(), 'month');

        $this->assertSame($tanggal30, $tanggal31);
    }

    private function series(?User $user = null): array
    {
        return app(DashboardSummaryService::class)
            ->forUser($user ?? User::factory()->create())['asset_growth_series'];
    }
}
