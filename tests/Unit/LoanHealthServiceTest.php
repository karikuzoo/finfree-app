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
            'worstInstallment' => null,
            'worstLabel' => null,
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
        $hasil = $this->nilai(['worstInstallment' => 8_500_000, 'worstLabel' => 'setelah 3 tahun bunga tetap']);

        $this->assertSame(25.0, $hasil['now']['dsr']);
        $this->assertSame(42.5, $hasil['worst']['dsr']);
        $this->assertSame('risky', $hasil['status']);
        $this->assertSame('Setelah 3 tahun bunga tetap, rasionya menjadi 42,5% — di atas batas 40%.', $hasil['reasons'][1]);
    }

    public function test_keadaan_terberat_yang_lebih_ringan_diabaikan(): void
    {
        $hasil = $this->nilai(['worstInstallment' => 4_000_000, 'worstLabel' => 'bila bunga turun']);

        $this->assertNull($hasil['worst']);
        $this->assertSame('healthy', $hasil['status']);
    }

    public function test_batas_dibaca_dari_config(): void
    {
        config(['loan_health.dsr_healthy_max' => 20]);

        $this->assertSame('caution', $this->nilai()['status']);
    }
}
