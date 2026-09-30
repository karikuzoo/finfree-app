<?php

namespace Tests\Unit;

use App\Services\GoalCalculatorService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mesin kalkulator adalah fungsi matematis murni tanpa efek samping —
 * cakupan pengujiannya harus paling tinggi di seluruh aplikasi.
 *
 * Sumber kebenaran kasus uji ada di docs/fixtures/calculator-cases.json,
 * berkas yang sama yang dipakai test JavaScript untuk preview real-time.
 * Bila kedua implementasi bisa berbeda diam-diam, cepat atau lambat mereka
 * akan berbeda.
 */
class GoalCalculatorServiceTest extends TestCase
{
    private GoalCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GoalCalculatorService();
    }

    public static function fixtureCases(): array
    {
        $path = dirname(__DIR__, 2).'/docs/fixtures/calculator-cases.json';

        if (! is_file($path)) {
            throw new \RuntimeException("Test vector tidak ditemukan di {$path}");
        }

        $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return array_reduce(
            $fixture['cases'],
            fn (array $carry, array $case) => $carry + [$case['name'] => [$case]],
            [],
        );
    }

    #[DataProvider('fixtureCases')]
    public function test_cocok_dengan_test_vector(array $case): void
    {
        $in = $case['input'];

        $result = $this->calculator->calculateMonthlyContribution(
            $in['target_amount'],
            $in['current_amount'],
            $in['months'],
            $in['annual_return_rate'],
            $in['annual_inflation_rate'],
        );

        foreach (['monthly_contribution_required', 'future_value_target', 'future_value_projection', 'total_contribution_projection', 'total_investment_growth_projection'] as $key) {
            $this->assertSame(
                $case['expected'][$key],
                $result[$key],
                "Kasus '{$case['name']}': {$key} tidak sesuai test vector."
            );
        }

        $this->assertEqualsWithDelta(
            $case['expected']['monthly_rate'],
            $result['monthly_rate'],
            1e-10,
            "Kasus '{$case['name']}': monthly_rate tidak sesuai."
        );
    }

    /**
     * Penjaga D-1. Bila suatu saat ada yang mengubah rumus menjadi real return
     * (memotong return dengan inflasi), target masa depan tidak akan naik dan
     * test ini gagal. Perbaiki rumusnya, bukan angka harapannya.
     */
    public function test_inflasi_menaikkan_target_bukan_memotong_return(): void
    {
        $tanpaInflasi = $this->calculator->calculateMonthlyContribution(500_000_000, 0, 120, 8, 0);
        $denganInflasi = $this->calculator->calculateMonthlyContribution(500_000_000, 0, 120, 8, 3.5);

        $this->assertSame(500_000_000, $tanpaInflasi['future_value_target']);
        $this->assertGreaterThan(
            $tanpaInflasi['future_value_target'],
            $denganInflasi['future_value_target'],
            'Inflasi harus menaikkan nominal target (D-1).'
        );

        // Rate bulanan tidak boleh ikut berubah karena inflasi — kalau berubah,
        // berarti return sedang dipotong inflasi juga: perhitungan ganda.
        $this->assertEqualsWithDelta(
            $tanpaInflasi['monthly_rate'],
            $denganInflasi['monthly_rate'],
            1e-12,
            'Return nominal tidak boleh dipotong inflasi — itu perhitungan ganda.'
        );
    }

    /**
     * Penjaga D-2. Pada ordinary annuity dengan tenor 1 bulan, setoran tunggal
     * terjadi di akhir periode sehingga tidak sempat berbunga sama sekali.
     * Kalau hasilnya lebih kecil dari target, rumusnya sudah berubah jadi
     * annuity due (setoran di awal bulan).
     */
    public function test_tenor_satu_bulan_setara_target_penuh(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(10_000_000, 0, 1, 12, 0);

        $this->assertSame(10_000_000, $result['monthly_contribution_required']);
    }

    public function test_return_nol_tidak_membagi_nol(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(60_000_000, 0, 12, 0, 0);

        $this->assertSame(5_000_000, $result['monthly_contribution_required']);
        $this->assertSame(0.0, $result['monthly_rate']);
    }

    public function test_dana_awal_melebihi_target_menghasilkan_nol_bukan_negatif(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(100_000_000, 150_000_000, 60, 6, 0);

        $this->assertSame(0, $result['monthly_contribution_required']);
        $this->assertTrue($result['already_achieved']);
    }

    public function test_dana_awal_yang_bertumbuh_melebihi_target_juga_nol(): void
    {
        // 90jt @6%/thn selama 5 tahun tumbuh melewati 100jt tanpa setoran apa pun.
        $result = $this->calculator->calculateMonthlyContribution(100_000_000, 90_000_000, 60, 6, 0);

        $this->assertSame(0, $result['monthly_contribution_required']);
    }

    public function test_setoran_dibulatkan_ke_atas_sehingga_target_tercapai(): void
    {
        foreach (self::fixtureCases() as [$case]) {
            $result = $this->calculator->calculateMonthlyContribution(
                $case['input']['target_amount'],
                $case['input']['current_amount'],
                $case['input']['months'],
                $case['input']['annual_return_rate'],
                $case['input']['annual_inflation_rate'],
            );

            $this->assertGreaterThanOrEqual(
                $result['future_value_target'],
                $result['future_value_projection'],
                "Kasus '{$case['name']}': proyeksi tidak boleh berakhir di bawah target."
            );
        }
    }

    public function test_konversi_rate_memakai_effective_annual_bukan_pembagian_dua_belas(): void
    {
        $i = $this->calculator->monthlyRate(12);

        // Effective: (1.12)^(1/12)-1 ≈ 0.009489, bukan 0.01.
        $this->assertEqualsWithDelta(0.0094887929, $i, 1e-9);
        $this->assertEqualsWithDelta(0.12, (1 + $i) ** 12 - 1, 1e-12);
    }

    public function test_menolak_jangka_waktu_nol_atau_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(100_000_000, 0, 0, 8, 0);
    }

    public function test_menolak_target_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(0, 0, 12, 8, 0);
    }

    public function test_menolak_dana_awal_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(100_000_000, -1, 12, 8, 0);
    }

    // ── FR-42: kalkulator Investasi ─────────────────────────────────────

    /**
     * Pemeriksaan silang dua arah: setoran yang dihitung kalkulator tujuan,
     * bila diproyeksikan maju, harus menghasilkan target masa depannya
     * kembali. Keduanya memakai konvensi rate yang sama, jadi tidak boleh ada
     * selisih selain pembulatan setoran ke atas.
     */
    public function test_investasi_adalah_kebalikan_kalkulator_tujuan(): void
    {
        $tujuan = $this->calculator->calculateMonthlyContribution(1_000_000_000, 50_000_000, 120, 8, 2);
        $maju = $this->calculator->projectInvestment(50_000_000, $tujuan['monthly_contribution_required'], 120, 8);

        $this->assertSame($tujuan['future_value_projection'], $maju['final_value']);
        $this->assertGreaterThanOrEqual($tujuan['future_value_target'], $maju['final_value']);
    }

    public function test_investasi_return_nol_tidak_membagi_nol(): void
    {
        $hasil = $this->calculator->projectInvestment(10_000_000, 1_000_000, 24, 0);

        $this->assertSame(34_000_000, $hasil['final_value']);
        $this->assertSame(0, $hasil['investment_growth']);
    }

    public function test_investasi_bagiannya_menjumlah_ke_nilai_akhir(): void
    {
        $hasil = $this->calculator->projectInvestment(25_000_000, 2_500_000, 180, 9);

        $this->assertSame(
            $hasil['final_value'],
            $hasil['initial_amount'] + $hasil['total_contribution'] + $hasil['investment_growth'],
        );
        $this->assertSame(450_000_000, $hasil['total_contribution']);
    }

    // ── FR-41: kalkulator Pinjaman / KPR ────────────────────────────────

    /**
     * Konvensi bank: i = r/12, BUKAN effective annual. Rp 500 jt, 10%, 20 th
     * → angsuran Rp 4.825.109 (ceil), yang dicocokkan dengan rumus anuitas
     * standar. Bila suatu saat ada yang "menyeragamkan" konversinya ke
     * monthlyRate(), angsurannya turun ke ±4,68 jt dan test ini gagal.
     */
    public function test_pinjaman_memakai_bunga_tahunan_dibagi_dua_belas(): void
    {
        $hasil = $this->calculator->calculateLoan(500_000_000, 10, 240);

        $i = 0.10 / 12;
        $harapan = (int) ceil(500_000_000 * $i / (1 - (1 + $i) ** -240));

        $this->assertSame($harapan, $hasil['monthly_installment']);
        $this->assertSame(4_825_109, $hasil['monthly_installment']);
        $this->assertEqualsWithDelta($i, $hasil['monthly_rate'], 1e-12);
    }

    /** CLAUDE.md §6.8: kasus uji wajib. */
    public function test_pinjaman_sisa_pokok_berakhir_tepat_nol(): void
    {
        foreach ([[500_000_000, 10, 240], [75_000_000, 7.25, 36], [1_234_567, 18, 7], [300_000_000, 11.5, 360]] as [$p, $r, $n]) {
            $hasil = $this->calculator->calculateLoan($p, $r, $n);

            $this->assertSame(0, end($hasil['yearly'])['balance'], "Sisa pokok tidak nol untuk {$p}/{$r}/{$n}");
            $this->assertSame($p, array_sum(array_column($hasil['yearly'], 'principal_paid')));
            $this->assertSame($hasil['total_payment'], $hasil['principal'] + $hasil['total_interest']);
            // Angsuran terakhir menyerap kelebihan pembulatan, jadi tidak
            // pernah lebih besar dari angsuran biasa.
            $this->assertLessThanOrEqual($hasil['monthly_installment'], $hasil['last_installment']);
            $this->assertSame((int) ceil($n / 12), count($hasil['yearly']));
        }
    }

    public function test_pinjaman_tanpa_bunga_dibagi_rata(): void
    {
        $hasil = $this->calculator->calculateLoan(100, 0, 3);

        $this->assertSame(34, $hasil['monthly_installment']);
        $this->assertSame(32, $hasil['last_installment']);
        $this->assertSame(0, $hasil['total_interest']);
        $this->assertSame(100, $hasil['total_payment']);
    }

    public function test_pinjaman_deret_grafik_dari_pokok_penuh_ke_nol(): void
    {
        $hasil = $this->calculator->calculateLoan(120_000_000, 9, 30);

        $this->assertSame(['month' => 0, 'balance' => 120_000_000, 'cumulative_interest' => 0], $hasil['series'][0]);
        $this->assertSame([0, 12, 24, 30], array_column($hasil['series'], 'month'));
        $this->assertSame(0, end($hasil['series'])['balance']);
        $this->assertSame($hasil['total_interest'], end($hasil['series'])['cumulative_interest']);
    }

    /**
     * Bunga berjenjang dari contoh pengguna (30 Sep 2026): Rp 500 jt, 20 th,
     * tahun 1 3,75%, tahun 2–4 6,75%, tahun 5–10 9,75%, tahun 11–20 10,75%.
     * Angkanya dihitung terpisah dengan skrip simulasi yang sama caranya
     * (anuitas sisa pokok × sisa tenor di awal tiap jenjang).
     */
    public function test_pinjaman_bunga_berjenjang_contoh_pengguna(): void
    {
        $hasil = $this->calculator->calculateLoan(500_000_000, 3.75, 240, [
            ['from_month' => 13, 'rate' => 6.75],
            ['from_month' => 49, 'rate' => 9.75],
            ['from_month' => 121, 'rate' => 10.75],
        ]);

        $this->assertSame([2_964_442, 3_763_866, 4_546_179, 4_739_763], array_column($hasil['tiers'], 'installment'));
        $this->assertSame([[1, 12], [13, 48], [49, 120], [121, 240]], array_map(fn ($t) => [$t['from_month'], $t['to_month']], $hasil['tiers']));
        $this->assertSame(567_168_740, $hasil['total_interest']);
        $this->assertSame(0, end($hasil['yearly'])['balance']);
        $this->assertSame(500_000_000, array_sum(array_column($hasil['yearly'], 'principal_paid')));
        $this->assertSame(2_964_442, $hasil['monthly_installment']);
    }

    /**
     * "Tetap lalu mengambang" hanyalah dua jenjang: tahun-tahun pertamanya
     * sama persis dengan bunga tetap, lalu angsurannya dihitung ulang dari
     * sisa pokok, sisa tenor, dan bunga baru.
     */
    public function test_pinjaman_dua_jenjang_sama_dengan_tetap_lalu_mengambang(): void
    {
        $tetap = $this->calculator->calculateLoan(500_000_000, 7, 240);
        $campur = $this->calculator->calculateLoan(500_000_000, 7, 240, [['from_month' => 37, 'rate' => 11]]);

        $this->assertSame($tetap['monthly_installment'], $campur['monthly_installment']);
        $this->assertSame($tetap['yearly'][2]['balance'], $campur['yearly'][2]['balance']);

        $sisa = $campur['yearly'][2]['balance'];
        $i = 0.11 / 12;
        $this->assertSame((int) ceil($sisa * $i / (1 - (1 + $i) ** -204)), $campur['tiers'][1]['installment']);
        $this->assertSame(0, end($campur['yearly'])['balance']);
    }

    public function test_pinjaman_bunga_tetap_punya_satu_jenjang(): void
    {
        $hasil = $this->calculator->calculateLoan(100_000_000, 8, 60);

        $this->assertCount(1, $hasil['tiers']);
        $this->assertSame(['from_month' => 1, 'to_month' => 60, 'rate' => 8.0, 'installment' => $hasil['monthly_installment']], $hasil['tiers'][0]);
    }

    public function test_pinjaman_menolak_jenjang_yang_tidak_urut(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateLoan(100_000_000, 5, 120, [
            ['from_month' => 49, 'rate' => 9],
            ['from_month' => 13, 'rate' => 7],
        ]);
    }

    public function test_pinjaman_menolak_pokok_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateLoan(0, 10, 12);
    }

    // ── FR-36: kebalikan rumus untuk tawaran rekalkulasi ────────────────

    /**
     * Return 0% supaya angkanya bisa dihitung tangan: sisa 110 juta dengan
     * setoran 3.333.334 butuh 33 bulan (110 jt / 33 = 3.333.333,33 → ke atas
     * 3.333.334), dan 32 bulan belum cukup.
     */
    public function test_bulan_tercepat_dengan_setoran_tetap(): void
    {
        $this->assertSame(33, $this->calculator->monthsToReach(120_000_000, 10_000_000, 3_333_334, 0, 0));
        $this->assertSame(33, $this->calculator->monthsToReach(120_000_000, 10_000_000, 3_333_334, 0, 0, fromMonths: 25));
    }

    /**
     * Sifat yang dijanjikan ke pengguna: setoran untuk bulan yang ditawarkan
     * tidak melebihi setorannya sekarang, dan sebulan lebih cepat pasti
     * melebihi — dengan imbal hasil dan inflasi sungguhan.
     */
    public function test_bulan_tercepat_adalah_batas_yang_tepat(): void
    {
        $bulan = $this->calculator->monthsToReach(300_000_000, 25_000_000, 3_000_000, 6, 3.5);

        $this->assertNotNull($bulan);
        $this->assertLessThanOrEqual(3_000_000, $this->calculator->calculateMonthlyContribution(300_000_000, 25_000_000, $bulan, 6, 3.5)['monthly_contribution_required']);
        $this->assertGreaterThan(3_000_000, $this->calculator->calculateMonthlyContribution(300_000_000, 25_000_000, $bulan - 1, 6, 3.5)['monthly_contribution_required']);
    }

    /**
     * Inflasi lebih tinggi dari imbal hasil dengan setoran kecil: target masa
     * depan lari lebih cepat daripada tabungannya. Tidak boleh berputar
     * selamanya dan tidak boleh mengarang tanggal.
     */
    public function test_bulan_tercepat_null_bila_tak_pernah_tercapai(): void
    {
        $this->assertNull($this->calculator->monthsToReach(1_000_000_000, 0, 100_000, 0, 10, maxMonths: 600));
    }

    public function test_target_terjangkau_dengan_setoran_tetap(): void
    {
        // 10 jt + 3.333.334 × 24 = 90.000.016 → dibulatkan ke bawah ke ribuan.
        $this->assertSame(90_000_000, $this->calculator->affordableTarget(10_000_000, 3_333_334, 24, 0, 0));
    }

    public function test_target_terjangkau_benar_benar_terjangkau(): void
    {
        $target = $this->calculator->affordableTarget(25_000_000, 3_000_000, 60, 6, 3.5);

        $this->assertSame(0, $target % 1000);
        $this->assertLessThanOrEqual(3_000_000, $this->calculator->calculateMonthlyContribution($target, 25_000_000, 60, 6, 3.5)['monthly_contribution_required']);
        // Seribu rupiah lebih besar sudah tidak terjangkau — jadi ini memang
        // yang terbesar, bukan sekadar "cukup kecil".
        $this->assertGreaterThan(3_000_000, $this->calculator->calculateMonthlyContribution($target + 1000,25_000_000, 60, 6, 3.5)['monthly_contribution_required']);
    }
}
