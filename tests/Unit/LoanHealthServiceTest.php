<?php

namespace Tests\Unit;

use App\Services\LoanHealthService;
use Tests\TestCase;

/**
 * Cek kesehatan cicilan KPR. Meng-extend Tests\TestCase (bukan PHPUnit murni)
 * karena batasnya dibaca dari config/loan_health.php.
 *
 * Skenario dasar: pendapatan 20 jt, angsuran KPR 5 jt (DSR 25%), pengeluaran
 * 8 jt, tanpa cicilan lain & pajak → sisa 7 jt (35% pendapatan).
 */
class LoanHealthServiceTest extends TestCase
{
    private function nilai(array $ubah = []): array
    {
        $a = array_merge([
            'monthlyIncome' => 20_000_000,
            'otherInstallments' => 0,
            'monthlyExpenses' => 8_000_000,
            'annualTaxes' => 0,
            'installment' => 5_000_000,
            'stages' => [],
            'incomeGrowth' => 0.0,
        ], $ubah);

        return (new LoanHealthService)->evaluate(...$a);
    }

    public function test_rasio_rendah_dan_sisa_cukup_sehat(): void
    {
        $hasil = $this->nilai();

        $this->assertSame('healthy', $hasil['status']);
        $this->assertSame(25.0, $hasil['now']['dsr']);
        $this->assertSame(7_000_000, $hasil['now']['residual']);
        $this->assertNull($hasil['worst']);
    }

    /** Batas config: sampai 30% sehat, sampai 40% waspada, lebih dari itu berisiko. */
    public function test_batas_rasio_cicilan(): void
    {
        $this->assertSame('healthy', $this->nilai(['installment' => 6_000_000, 'monthlyExpenses' => 0])['status']);   // 30%
        $this->assertSame('caution', $this->nilai(['installment' => 6_200_000, 'monthlyExpenses' => 0])['status']);   // 31%
        $this->assertSame('caution', $this->nilai(['installment' => 8_000_000, 'monthlyExpenses' => 0])['status']);   // 40%
        $this->assertSame('risky', $this->nilai(['installment' => 8_200_000, 'monthlyExpenses' => 0])['status']);     // 41%
    }

    /** Cicilan lain ikut rasio — itu yang dihitung bank, bukan KPR saja. */
    public function test_cicilan_lain_ikut_rasio(): void
    {
        $hasil = $this->nilai(['otherInstallments' => 2_000_000]);

        $this->assertSame(35.0, $hasil['now']['dsr']);
        $this->assertSame('caution', $hasil['status']);
        $this->assertStringContainsString('KPR ditambah cicilan lain', $hasil['reasons'][0]);
    }

    /**
     * Rasio aman tidak cukup: pengeluaran besar bisa menghabiskan sisanya.
     * Pajak tahunan tidak masuk rasio, tetapi mengurangi sisa uang.
     */
    public function test_rasio_aman_tapi_sisa_uang_minus_berisiko(): void
    {
        $hasil = $this->nilai(['monthlyExpenses' => 14_000_000, 'annualTaxes' => 24_000_000]);

        $this->assertSame(25.0, $hasil['now']['dsr']);
        $this->assertSame(2_000_000, $hasil['monthly_taxes']);
        $this->assertSame(-1_000_000, $hasil['now']['residual']);
        $this->assertSame('risky', $hasil['status']);
        $this->assertStringContainsString('kurang Rp 1.000.000', $hasil['reasons'][1]);
    }

    public function test_sisa_tipis_waspada(): void
    {
        // Sisa 1,5 jt = 7,5% pendapatan, di bawah batas 10%.
        $hasil = $this->nilai(['monthlyExpenses' => 13_500_000]);

        $this->assertSame('caution', $hasil['status']);
        $this->assertStringContainsString('nyaris tanpa ruang', end($hasil['reasons']));
    }

    /**
     * Inti fitur ini: KPR yang sehat selama bunga promo tetapi berat setelah
     * bunga mengambang dinilai dari keadaan TERBERATNYA.
     */
    public function test_dinilai_dari_angsuran_terberat(): void
    {
        $hasil = $this->nilai(['stages' => [['label' => 'mulai tahun ke-4', 'installment' => 8_500_000]]]);

        $this->assertSame(25.0, $hasil['now']['dsr']);
        $this->assertSame(42.5, $hasil['worst']['dsr']);
        $this->assertSame('risky', $hasil['status']);
        $this->assertSame('Mulai tahun ke-4, rasionya menjadi 42,5% — di atas batas 40%.', $hasil['reasons'][1]);
    }

    public function test_keadaan_terberat_yang_lebih_ringan_diabaikan(): void
    {
        $hasil = $this->nilai(['stages' => [['label' => 'mulai tahun ke-2', 'installment' => 4_000_000]]]);

        $this->assertNull($hasil['worst']);
        $this->assertSame('healthy', $hasil['status']);
    }

    /**
     * Bunga berjenjang: yang berguna bukan hanya puncaknya, tetapi KAPAN
     * cicilan mulai tidak sehat — itu tahun yang perlu disiapkan pengguna.
     */
    public function test_menyebut_jenjang_pertama_yang_melewati_batas_sehat(): void
    {
        $hasil = $this->nilai(['stages' => [
            ['label' => 'mulai tahun ke-2', 'installment' => 5_800_000],   // 29% — masih sehat
            ['label' => 'mulai tahun ke-5', 'installment' => 6_800_000],   // 34% — mulai melewati
            ['label' => 'mulai tahun ke-11', 'installment' => 7_400_000],  // 37% — terberat
        ]]);

        $this->assertSame('mulai tahun ke-11', $hasil['worst']['label']);
        $this->assertSame('caution', $hasil['status']);
        $this->assertSame('Mulai tahun ke-5, rasionya sudah 34% — melewati batas sehat 30%.', $hasil['reasons'][1]);
        $this->assertSame('Mulai tahun ke-11, rasionya menjadi 37%.', $hasil['reasons'][2]);
    }

    public function test_jenjang_terberat_yang_pertama_melewati_tidak_disebut_dua_kali(): void
    {
        $hasil = $this->nilai(['stages' => [['label' => 'mulai tahun ke-4', 'installment' => 7_000_000]]]);

        // Kalimat sisa uang boleh menyebut tahunnya; RASIO-nya cukup sekali.
        $this->assertCount(1, array_filter($hasil['reasons'], fn ($r) => str_contains($r, 'tahun ke-4, rasionya')));
    }

    // ── Kenaikan gaji per tahun (30 Sep 2026) ───────────────────────────

    /** Jenjang contoh pengguna: Rp 500 jt, 20 th, 3,75 / 6,75 / 9,75 / 10,75%. */
    private function jenjangContoh(): array
    {
        return [
            ['label' => 'mulai tahun ke-2', 'installment' => 3_763_866, 'year' => 2],
            ['label' => 'mulai tahun ke-5', 'installment' => 4_546_179, 'year' => 5],
            ['label' => 'mulai tahun ke-11', 'installment' => 4_739_763, 'year' => 11],
        ];
    }

    public function test_tanpa_kenaikan_gaji_terberat_adalah_angsuran_terbesar(): void
    {
        $hasil = $this->nilai(['monthlyIncome' => 15_000_000, 'monthlyExpenses' => 6_000_000, 'installment' => 2_964_442, 'stages' => $this->jenjangContoh()]);

        $this->assertSame('mulai tahun ke-11', $hasil['worst']['label']);
        $this->assertSame(31.6, $hasil['worst']['dsr']);
        $this->assertSame(15_000_000, $hasil['worst']['income']);
    }

    /**
     * Dengan gaji naik 5% per tahun, pendapatan tahun ke-11 menjadi
     * 15 jt × 1,05¹⁰ ≈ 24,4 jt — angsuran terbesar justru paling ringan.
     * Yang terberat kini jenjang tahun ke-5: 4.546.179 ÷ (15 jt × 1,05⁴ =
     * 18.232.594) = 24,9%. Itulah alasan terberat dipilih dari RASIO, bukan
     * dari angsuran.
     */
    public function test_kenaikan_gaji_menggeser_jenjang_terberat(): void
    {
        $hasil = $this->nilai([
            'monthlyIncome' => 15_000_000, 'monthlyExpenses' => 6_000_000,
            'installment' => 2_964_442, 'stages' => $this->jenjangContoh(), 'incomeGrowth' => 5,
        ]);

        $this->assertSame('mulai tahun ke-5', $hasil['worst']['label']);
        $this->assertSame(18_232_594, $hasil['worst']['income']);
        $this->assertSame(24.9, $hasil['worst']['dsr']);
        $this->assertSame('healthy', $hasil['status']);
        $this->assertSame(5.0, $hasil['income_growth']);
        $this->assertContains(
            'Angka itu sudah memperhitungkan kenaikan gaji 5% per tahun: pendapatan mulai tahun ke-5 diperkirakan Rp 18.232.594.',
            $hasil['reasons'],
        );
    }

    /** Sisa uang juga dihitung dari pendapatan yang sudah naik, dan pengeluaran TETAP. */
    public function test_sisa_uang_memakai_pendapatan_jenjang_itu(): void
    {
        $hasil = $this->nilai([
            'monthlyIncome' => 15_000_000, 'monthlyExpenses' => 6_000_000,
            'installment' => 2_964_442, 'stages' => $this->jenjangContoh(), 'incomeGrowth' => 5,
        ]);

        $this->assertSame(18_232_594 - 4_546_179 - 6_000_000, $hasil['worst']['residual']);
    }

    /**
     * Gaji yang naik lebih cepat dari angsuran: tidak ada jenjang yang lebih
     * berat dari tahun pertama. Labelnya jadi bergantung pada asumsi itu —
     * dan itu disebut terang-terangan.
     */
    public function test_kenaikan_gaji_yang_menutup_kenaikan_angsuran_disebut(): void
    {
        $hasil = $this->nilai([
            'monthlyIncome' => 15_000_000, 'monthlyExpenses' => 6_000_000, 'installment' => 2_964_442,
            'stages' => [['label' => 'mulai tahun ke-2', 'installment' => 3_000_000, 'year' => 2]],
            'incomeGrowth' => 10,
        ]);

        $this->assertNull($hasil['worst']);
        $this->assertStringContainsString('Penilaian ini bergantung pada kenaikan gaji itu benar-benar terjadi.', implode(' ', $hasil['reasons']));
    }

    /** "Bila bunga naik" tidak punya tahun — dinilai dengan pendapatan sekarang. */
    public function test_skenario_bunga_naik_tidak_ikut_dinaikkan_gajinya(): void
    {
        $hasil = $this->nilai([
            'stages' => [['label' => 'bila bunga naik ke 12%', 'installment' => 7_000_000]],
            'incomeGrowth' => 10,
        ]);

        $this->assertSame(20_000_000, $hasil['worst']['income']);
        $this->assertSame(35.0, $hasil['worst']['dsr']);
    }

    /**
     * Kalimat pertama menyatakan posisi terhadap batas. Dulu selalu
     * "39% pendapatan (patokan sehat: sampai 30%)" — pembaca harus
     * membandingkan sendiri dan mudah mengira masih aman.
     */
    public function test_kalimat_pertama_menyatakan_posisi_terhadap_batas(): void
    {
        $kalimat = fn (int $angsuran) => $this->nilai(['installment' => $angsuran, 'monthlyExpenses' => 0])['reasons'][0];

        $this->assertSame('Angsuran KPR memakan 25% pendapatan — masih dalam batas sehat 30%.', $kalimat(5_000_000));
        $this->assertSame('Angsuran KPR memakan 39% pendapatan — sudah di atas batas sehat 30% sejak tahun pertama.', $kalimat(7_800_000));
        $this->assertSame('Angsuran KPR memakan 45% pendapatan — di atas batas 40% sejak tahun pertama.', $kalimat(9_000_000));
    }

    public function test_batas_dibaca_dari_config(): void
    {
        config(['loan_health.dsr_healthy_max' => 20]);

        $this->assertSame('caution', $this->nilai()['status']);
    }
}
