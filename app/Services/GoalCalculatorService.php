<?php

namespace App\Services;

use InvalidArgumentException;

/**
 * Mesin perhitungan tujuan finansial — sumber kebenaran seluruh aplikasi.
 *
 * Aturan yang dikunci di sini berasal dari keputusan produk, bukan preferensi
 * implementasi. Jangan ubah tanpa mengubah dokumen dan test vector-nya:
 *
 *  - D-1  Inflasi MENAIKKAN nominal target, dan return yang dipakai adalah
 *         return nominal. Dilarang memotong return dengan inflasi juga —
 *         itu perhitungan ganda dan membuat setoran tampak jauh lebih besar
 *         dari seharusnya. Ini kesalahan paling umum di kalkulator sejenis.
 *  - D-2  Ordinary annuity: setoran di akhir bulan.
 *
 * Konversi rate: `annual_return_rate` diperlakukan sebagai effective annual
 * rate, sehingga i = (1+r)^(1/12) - 1 — bukan r/12. Selisih keduanya membesar
 * untuk tenor panjang seperti dana pensiun.
 *
 * Uang: perhitungan internal memakai float, tetapi setiap nilai yang keluar
 * dari service ini sudah dibulatkan ke rupiah penuh. Setoran bulanan
 * dibulatkan KE ATAS — membulatkan ke bawah membuat target meleset tipis.
 *
 * @see docs/fixtures/calculator-cases.json  test vector bersama PHP & JS
 * @see claude/CLAUDE.md §6                  penjelasan lengkap
 */
class GoalCalculatorService
{
    /**
     * Versi rumus. Naikkan bila perilaku perhitungan berubah, dan simpan
     * nilainya di goal_calculations.formula_version agar hasil lama tetap
     * bisa dijelaskan.
     */
    public const FORMULA_VERSION = 1;

    /**
     * Hitung setoran bulanan yang dibutuhkan untuk mencapai sebuah tujuan.
     *
     * @param  float  $targetAmount          nominal target dalam nilai hari ini
     * @param  float  $currentAmount         dana awal yang sudah dimiliki
     * @param  int    $months                jumlah bulan sampai target
     * @param  float  $annualReturnRate      persen per tahun, mis. 7.5
     * @param  float  $annualInflationRate   persen per tahun, mis. 3.5
     *
     * @throws InvalidArgumentException bila input tidak masuk akal
     */
    public function calculateMonthlyContribution(
        float $targetAmount,
        float $currentAmount,
        int $months,
        float $annualReturnRate,
        float $annualInflationRate = 0.0,
    ): array {
        $this->guard($targetAmount, $currentAmount, $months, $annualReturnRate, $annualInflationRate);

        $monthlyRate = $this->monthlyRate($annualReturnRate);
        $futureValueTarget = $this->inflatedTarget($targetAmount, $months, $annualInflationRate);

        // Nilai dana awal setelah bertumbuh sampai tanggal target.
        $growthFactor = ($monthlyRate === 0.0)
            ? 1.0
            : (1 + $monthlyRate) ** $months;
        $grownCurrent = $currentAmount * $growthFactor;

        $monthlyContribution = $this->solveContribution(
            $futureValueTarget,
            $currentAmount,
            $grownCurrent,
            $months,
            $monthlyRate,
            $growthFactor,
        );

        // Proyeksi dihitung memakai setoran yang SUDAH dibulatkan, bukan nilai
        // desimalnya — supaya angka yang ditampilkan konsisten dengan angka
        // yang benar-benar akan disetor pengguna.
        $futureValueProjection = ($monthlyRate === 0.0)
            ? $currentAmount + ($monthlyContribution * $months)
            : $grownCurrent + ($monthlyContribution * ($growthFactor - 1) / $monthlyRate);

        $futureValueProjection = (int) round($futureValueProjection);
        $totalContribution = $monthlyContribution * $months;

        return [
            'monthly_contribution_required' => $monthlyContribution,
            'future_value_target' => $futureValueTarget,
            'future_value_projection' => $futureValueProjection,
            'total_contribution_projection' => $totalContribution,
            'total_investment_growth_projection' =>
                $futureValueProjection - (int) round($currentAmount) - $totalContribution,
            'monthly_rate' => $monthlyRate,
            'months' => $months,
            'formula_version' => self::FORMULA_VERSION,
            'already_achieved' => $monthlyContribution === 0,
        ];
    }

    /**
     * Konversi effective annual rate menjadi rate bulanan.
     *
     * Sengaja BUKAN r/12. Untuk 12% setahun, r/12 memberi 1% per bulan yang
     * bila dimajemukkan 12 kali menghasilkan 12,68% — bukan 12% yang diminta.
     */
    public function monthlyRate(float $annualRatePercent): float
    {
        if ($annualRatePercent === 0.0) {
            return 0.0;
        }

        return (1 + $annualRatePercent / 100) ** (1 / 12) - 1;
    }

    /**
     * D-1: target dinaikkan ke nilai masa depan menurut inflasi.
     */
    public function inflatedTarget(float $targetAmount, int $months, float $annualInflationRate): int
    {
        if ($annualInflationRate === 0.0) {
            return (int) round($targetAmount);
        }

        $years = $months / 12;

        return (int) round($targetAmount * (1 + $annualInflationRate / 100) ** $years);
    }

    /**
     * Inti rumus, beserta dua kasus batas yang wajib ditangani.
     */
    private function solveContribution(
        int $futureValueTarget,
        float $currentAmount,
        float $grownCurrent,
        int $months,
        float $monthlyRate,
        float $growthFactor,
    ): int {
        // Dana awal (setelah bertumbuh) sudah menutup target — tidak perlu
        // menyetor apa pun. Tanpa cabang ini rumus menghasilkan angka negatif.
        if ($grownCurrent >= $futureValueTarget) {
            return 0;
        }

        // Return 0%: rumus utama membagi nol.
        if ($monthlyRate === 0.0) {
            return (int) ceil(($futureValueTarget - $currentAmount) / $months);
        }

        return (int) ceil(
            ($futureValueTarget - $grownCurrent) * $monthlyRate / ($growthFactor - 1)
        );
    }

    private function guard(
        float $targetAmount,
        float $currentAmount,
        int $months,
        float $annualReturnRate,
        float $annualInflationRate,
    ): void {
        if ($months < 1) {
            throw new InvalidArgumentException(
                'Jangka waktu minimal 1 bulan; tanggal target di masa lalu ditolak di validasi, bukan di perhitungan.'
            );
        }

        if ($targetAmount <= 0) {
            throw new InvalidArgumentException('Nominal target harus lebih besar dari nol.');
        }

        if ($currentAmount < 0) {
            throw new InvalidArgumentException('Dana awal tidak boleh negatif.');
        }

        if ($annualReturnRate < 0) {
            throw new InvalidArgumentException('Estimasi return tidak boleh negatif.');
        }

        if ($annualInflationRate < 0) {
            throw new InvalidArgumentException('Estimasi inflasi tidak boleh negatif.');
        }
    }
}
