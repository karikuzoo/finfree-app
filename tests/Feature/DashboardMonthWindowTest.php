<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Menjaga perhitungan rentang bulan pada grafik pertumbuhan kekayaan dari
 * luberan tanggal.
 *
 * Operasi bulan pada tanggal 29–31 mudah meleset ke bulan berikutnya: "31
 * September" tidak ada, jadi menjadi 1 Oktober. Bug seperti itu tidak
 * memunculkan error — deretnya hanya melewatkan satu bulan, dan hanya bila
 * diakses di akhir bulan. Seluruh test di sini MEMBEKUKAN waktu di tanggal 31;
 * tanpa itu semuanya lolos pada 28 dari 31 hari dalam sebulan.
 *
 * Sumber deretnya berubah: dulu dari setoran per tujuan, kini dari riwayat
 * transaksi seluruh akun (setoran harian sudah dipensiunkan). Jendelanya tetap
 * 12 bulan, dan cara menghitung kursornya — `startOfMonth()` lebih dulu, baru
 * `subMonths()` — tetap yang dijaga di sini.
 */
class DashboardMonthWindowTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function deret(User $user): array
    {
        return app(DashboardSummaryService::class)->forUser($user)['asset_growth_series']['monthly'];
    }

    private function rekening(User $user, float $awal = 0): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    public function test_deret_selalu_dua_belas_bulan_berurutan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $this->rekening($user, 1_000_000);

        $deret = $this->deret($user);

        $this->assertCount(12, $deret);
        $this->assertSame('2025-09', $deret[0]['period']);
        $this->assertSame('2026-08', $deret[11]['period']);
    }

    /**
     * Inti berkas ini. `subMonths(11)` pada 31 Agustus memberi 31 September
     * yang tidak ada, sehingga kursornya meluber dan satu bulan hilang dari
     * deret. Memotong ke awal bulan lebih dulu membuat kursornya selalu
     * bertanggal 1.
     */
    public function test_tidak_ada_bulan_yang_terlewat_saat_diakses_tanggal_31(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 23:59:00'));

        $user = User::factory()->create();
        $this->rekening($user, 1_000_000);

        $bulan = array_column($this->deret($user), 'period');

        $this->assertSame([
            '2025-09', '2025-10', '2025-11', '2025-12',
            '2026-01', '2026-02', '2026-03', '2026-04',
            '2026-05', '2026-06', '2026-07', '2026-08',
        ], $bulan);
    }

    public function test_hasilnya_sama_baik_diakses_tanggal_30_maupun_31(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 5_000_000);
        Transaction::factory()->for($user)->for($rekening)->pemasukan(1_000_000)
            ->pada('2026-03-10')->create();

        Carbon::setTestNow(Carbon::parse('2026-08-30 08:00:00'));
        $tanggal30 = $this->deret($user);

        Carbon::setTestNow(Carbon::parse('2026-08-31 08:00:00'));
        $tanggal31 = $this->deret($user);

        $this->assertSame($tanggal30, $tanggal31);
    }

    /**
     * Titik pertama memuat SELURUH kekayaan sebelum jendela dimulai — saldo
     * awal rekening ditambah transaksi yang lebih tua. Tanpa itu grafiknya
     * seolah berangkat dari nol dan memperlihatkan lonjakan yang tidak
     * pernah terjadi.
     */
    public function test_titik_pertama_memuat_kekayaan_sebelum_jendela(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);
        Transaction::factory()->for($user)->for($rekening)->pemasukan(2_000_000)
            ->pada('2024-01-15')->create();

        $deret = $this->deret($user);

        $this->assertSame(12_000_000.0, $deret[0]['cumulative_amount']);
    }

    public function test_deret_menumpuk_dan_bukan_nilai_per_bulan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 0);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(1_000_000)
            ->pada('2026-06-05')->create();
        Transaction::factory()->for($user)->for($rekening)->pemasukan(1_000_000)
            ->pada('2026-07-05')->create();

        $deret = collect($this->deret($user))->keyBy('period');

        $this->assertSame(0.0, $deret['2026-05']['cumulative_amount']);
        $this->assertSame(1_000_000.0, $deret['2026-06']['cumulative_amount']);
        $this->assertSame(2_000_000.0, $deret['2026-07']['cumulative_amount']);
        $this->assertSame(2_000_000.0, $deret['2026-08']['cumulative_amount']);
    }

    /**
     * Transfer memindahkan uang antar rekening milik pengguna yang sama, jadi
     * kekayaannya tidak berubah. Menghitungnya membuat tiap pemindahan dana
     * tampak sebagai lonjakan pada grafik.
     */
    public function test_transfer_tidak_menggerakkan_grafik(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $asal = $this->rekening($user, 5_000_000);
        $tujuan = $this->rekening($user, 0);

        Transaction::factory()->for($user)->for($asal)->transfer(2_000_000, $tujuan)
            ->pada('2026-07-05')->create();

        foreach ($this->deret($user) as $titik) {
            $this->assertSame(5_000_000.0, $titik['cumulative_amount']);
        }
    }

    public function test_pengguna_tanpa_rekening_mendapat_deret_nol(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $deret = $this->deret(User::factory()->create());

        $this->assertCount(12, $deret);
        $this->assertSame(0.0, $deret[11]['cumulative_amount']);
    }

    // ── Deret harian ────────────────────────────────────────────────────

    /**
     * Kedua deret WAJIB memakai kunci yang sama.
     *
     * Ketiadaan test ini sempat meloloskan bug yang tidak terlihat: deret
     * bulanan memakai `month`, harian memakai `date`, dan grafiknya membaca
     * `point.date` untuk mode harian. Begitu sumber datanya diganti, seluruh
     * label harian menjadi undefined dan grafiknya kosong melompong — tanpa
     * satu pun error muncul di layar maupun di test.
     */
    public function test_deret_harian_memakai_kunci_yang_sama_dengan_bulanan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $this->rekening($user, 1_000_000);

        $semua = app(DashboardSummaryService::class)->forUser($user)['asset_growth_series'];

        foreach (['monthly', 'daily'] as $rentang) {
            foreach ($semua[$rentang] as $titik) {
                $this->assertArrayHasKey('period', $titik, "Deret {$rentang} tidak memakai kunci 'period'.");
                $this->assertArrayHasKey('cumulative_amount', $titik);
            }
        }
    }

    public function test_deret_harian_tiga_puluh_hari_berakhir_hari_ini(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $this->rekening($user, 1_000_000);

        $harian = app(DashboardSummaryService::class)
            ->forUser($user)['asset_growth_series']['daily'];

        $this->assertCount(30, $harian);
        $this->assertSame('2026-08-02', $harian[0]['period']);
        $this->assertSame('2026-08-31', $harian[29]['period']);
    }

    public function test_deret_harian_menumpuk_dari_kekayaan_sebelumnya(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-08-31 10:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 5_000_000);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(1_000_000)
            ->pada('2026-08-20')->create();

        $harian = collect(
            app(DashboardSummaryService::class)->forUser($user)['asset_growth_series']['daily']
        )->keyBy('period');

        $this->assertSame(5_000_000.0, $harian['2026-08-19']['cumulative_amount']);
        $this->assertSame(6_000_000.0, $harian['2026-08-20']['cumulative_amount']);
        $this->assertSame(6_000_000.0, $harian['2026-08-31']['cumulative_amount']);
    }
}
