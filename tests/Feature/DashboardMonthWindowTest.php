<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Menjaga perhitungan rentang bulan pada grafik pertumbuhan aset dari luberan
 * tanggal.
 *
 * Operasi bulan pada tanggal 29–31 mudah meleset ke bulan berikutnya: "31
 * September" tidak ada, jadi menjadi 1 Oktober. Bug seperti itu tidak
 * memunculkan error — deretnya hanya melewatkan satu bulan, dan hanya bila
 * diakses di akhir bulan. Seluruh test di sini MEMBEKUKAN waktu di tanggal 31;
 * tanpa itu semuanya lolos pada 28 dari 31 hari dalam sebulan.
 *
 * Deretnya kini dihitung PER TUJUAN, membentang dari bulan tujuan dibuat sampai
 * bulan berjalan — bukan lagi jendela tetap 12 bulan untuk seluruh akun. Versi
 * sebelumnya memakai `subMonths(11)->startOfMonth()` yang bisa meleset; versi
 * sekarang memotong ke awal bulan lebih dulu, sehingga kursornya selalu
 * bertanggal 1 dan tidak pernah meluber. Test ini mengunci sifat itu.
 */
class DashboardMonthWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTujuan(User $user, string $dibuatPada): \App\Models\FinancialGoal
    {
        $sebelumnya = Carbon::getTestNow();
        Carbon::setTestNow($dibuatPada);

        $goal = $user->goals()->create([
            'type' => GoalType::Emergency->value,
            'name' => 'Dana Darurat',
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 4,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);

        Carbon::setTestNow($sebelumnya);

        return $goal;
    }

    /** @return array<int, array{month: string, cumulative_amount: float}> */
    private function deret(User $user): array
    {
        return app(DashboardSummaryService::class)->forUser($user)['goals'][0]['asset_growth_series'];
    }

    public function test_deret_membentang_dari_bulan_tujuan_dibuat_sampai_bulan_ini(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        $user = User::factory()->create();
        $this->buatTujuan($user, '2026-05-14 09:00:00');

        $bulan = array_column($this->deret($user), 'month');

        $this->assertSame(['2026-05', '2026-06', '2026-07', '2026-08'], $bulan);
    }

    /**
     * Inti test ini: tidak ada bulan yang hilang di tengah deret, meski
     * diaksesnya pada tanggal 31 dan meski jalur bulannya melewati bulan-bulan
     * berisi 30 hari — Juni, September, November — yang dulu jadi korban
     * luberan.
     */
    public function test_tidak_ada_bulan_yang_terlewat_saat_diakses_tanggal_31(): void
    {
        Carbon::setTestNow('2026-12-31 09:00:00');

        $user = User::factory()->create();
        $this->buatTujuan($user, '2026-01-31 09:00:00');

        $bulan = array_column($this->deret($user), 'month');

        $this->assertSame([
            '2026-01', '2026-02', '2026-03', '2026-04', '2026-05', '2026-06',
            '2026-07', '2026-08', '2026-09', '2026-10', '2026-11', '2026-12',
        ], $bulan);
    }

    /**
     * Bulan dengan 31 hari tidak pernah meluber, jadi tanggal 30 adalah
     * pembanding yang berguna: hasilnya harus sama persis dengan tanggal 31.
     */
    public function test_hasilnya_sama_baik_diakses_tanggal_30_maupun_31(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-08-30 09:00:00');
        $this->buatTujuan($user, '2026-02-15 09:00:00');
        $tanggal30 = array_column($this->deret($user), 'month');

        Carbon::setTestNow('2026-08-31 09:00:00');
        $tanggal31 = array_column($this->deret($user), 'month');

        $this->assertSame($tanggal30, $tanggal31);
    }

    /**
     * Setoran di bulan pertama harus ikut terhitung. Bila batas bawah rentang
     * meleset satu bulan, setoran itulah yang pertama hilang dari total
     * kumulatif — dan hilangnya tidak terlihat karena grafiknya tetap tergambar.
     */
    public function test_setoran_di_bulan_pertama_ikut_terhitung(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        $user = User::factory()->create();
        $goal = $this->buatTujuan($user, '2026-05-14 09:00:00');
        $goal->contributions()->create([
            'amount' => 2000000,
            'contributed_on' => '2026-05-20',
        ]);

        $deret = collect($this->deret($user));

        $this->assertSame(2000000.0, $deret->firstWhere('month', '2026-05')['cumulative_amount']);
        $this->assertSame(2000000.0, $deret->last()['cumulative_amount']);
    }

    /**
     * Tujuan yang baru dibuat hari ini tetap menghasilkan satu titik, bukan
     * deret kosong — grafik tanpa titik sama sekali terbaca sebagai kerusakan.
     */
    public function test_tujuan_yang_baru_dibuat_tetap_punya_satu_titik(): void
    {
        Carbon::setTestNow('2026-08-31 09:00:00');

        $user = User::factory()->create();
        $this->buatTujuan($user, '2026-08-31 08:00:00');

        $this->assertCount(1, $this->deret($user));
    }
}
