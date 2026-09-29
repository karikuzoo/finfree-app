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
     * FR-42: kalkulator Investasi — arah MAJU dari rumus tujuan. Setorannya
     * diketahui, nilai akhirnya dicari (CLAUDE.md §6.8):
     *
     *   FV = PV × (1+i)^n + PMT × ((1+i)^n − 1) / i
     *
     * Konvensi rate sama dengan kalkulator tujuan (effective annual, ordinary
     * annuity), supaya hasil keduanya bisa saling dicocokkan: setoran yang
     * dihitung kalkulator tujuan, bila dimasukkan ke sini, menghasilkan
     * targetnya kembali.
     *
     * @return array{final_value: int, total_contribution: int, initial_amount: int, investment_growth: int, monthly_rate: float, months: int}
     */
    public function projectInvestment(
        float $initialAmount,
        float $monthlyContribution,
        int $months,
        float $annualReturnRate,
    ): array {
        if ($months < 1) {
            throw new InvalidArgumentException('Jangka waktu minimal 1 bulan.');
        }

        if ($initialAmount < 0 || $monthlyContribution < 0 || $annualReturnRate < 0) {
            throw new InvalidArgumentException('Dana awal, setoran, dan imbal hasil tidak boleh negatif.');
        }

        $monthlyRate = $this->monthlyRate($annualReturnRate);
        $growthFactor = ($monthlyRate === 0.0) ? 1.0 : (1 + $monthlyRate) ** $months;

        $finalValue = ($monthlyRate === 0.0)
            ? $initialAmount + $monthlyContribution * $months
            : $initialAmount * $growthFactor + $monthlyContribution * ($growthFactor - 1) / $monthlyRate;

        $finalValue = (int) round($finalValue);
        $initial = (int) round($initialAmount);
        $totalContribution = (int) round($monthlyContribution * $months);

        return [
            'final_value' => $finalValue,
            'initial_amount' => $initial,
            'total_contribution' => $totalContribution,
            'investment_growth' => $finalValue - $initial - $totalContribution,
            'monthly_rate' => $monthlyRate,
            'months' => $months,
        ];
    }

    /**
     * FR-41: kalkulator Pinjaman / KPR — anuitas, dibayar di akhir bulan:
     *
     *   angsuran = P × i / (1 − (1+i)^−n)
     *
     * PENGECUALIAN KONVENSI RATE: di sini i = r / 12, BUKAN effective annual
     * seperti monthlyRate(). Suku bunga pinjaman adalah angka KONTRAK, dan
     * bank di Indonesia menghitung angsurannya dengan bunga tahunan dibagi dua
     * belas. Memakai konversi efektif membuat angsuran Arus lebih rendah dari
     * brosur bank untuk pinjaman yang sama (Rp 500 jt, 10%, 20 th: ±4,68 jt
     * vs 4,83 jt) — pengguna yang mencocokkannya akan mengira Arus salah.
     * Imbal hasil investasi adalah PERKIRAAN, jadi di sana konvensi efektif
     * tetap berlaku. Diputuskan pengguna 29 Sep 2026, lihat CLAUDE.md §6.8.
     *
     * Tabel amortisasi dihitung per bulan dalam rupiah penuh: bunga = sisa
     * pokok × i (dibulatkan), pokok = angsuran − bunga. Angsuran dibulatkan
     * KE ATAS, sehingga kelebihan pembulatannya menumpuk; angsuran TERAKHIR
     * menyesuaikan supaya sisa pokok berakhir tepat nol — kasus uji wajib
     * (CLAUDE.md §6.8).
     *
     * BUNGA TETAP LALU MENGAMBANG (fix-lalu-float, umum di KPR Indonesia):
     * bila `$floatingRate` diisi dan `$fixedMonths` berada di dalam tenor,
     * `$fixedMonths` angsuran pertama memakai `$annualInterestRate`, lalu
     * angsuran DIHITUNG ULANG dari sisa pokok, sisa tenor, dan bunga
     * mengambang — cara bank menyesuaikan angsuran saat masa bunga tetap
     * habis. `installment_after_float` berisi angsuran tahap kedua itu; NULL
     * bila tidak ada tahap kedua.
     *
     * @return array{
     *     monthly_installment: int, last_installment: int,
     *     installment_after_float: int|null, fixed_months: int|null,
     *     total_payment: int, total_interest: int, principal: int,
     *     monthly_rate: float, months: int,
     *     yearly: array<int, array{year: int, principal_paid: int, interest_paid: int, balance: int}>,
     *     series: array<int, array{month: int, balance: int, cumulative_interest: int}>,
     * }
     */
    public function calculateLoan(
        float $principal,
        float $annualInterestRate,
        int $months,
        ?float $floatingRate = null,
        int $fixedMonths = 0,
    ): array {
        if ($months < 1) {
            throw new InvalidArgumentException('Tenor minimal 1 bulan.');
        }

        if ($principal <= 0) {
            throw new InvalidArgumentException('Pokok pinjaman harus lebih besar dari nol.');
        }

        if ($annualInterestRate < 0 || ($floatingRate !== null && $floatingRate < 0)) {
            throw new InvalidArgumentException('Suku bunga tidak boleh negatif.');
        }

        $pokok = (int) round($principal);
        $i = $annualInterestRate / 100 / 12;

        $angsuran = $this->annuityInstallment($pokok, $i, $months);
        $angsuranAwal = $angsuran;

        // Tahap kedua hanya ada bila masa bunga tetap berakhir SEBELUM tenor
        // habis. Masa tetap sepanjang tenor sama saja dengan bunga tetap.
        $adaTahapKedua = $floatingRate !== null && $fixedMonths > 0 && $fixedMonths < $months;
        $angsuranMengambang = null;

        $sisa = $pokok;
        $totalBunga = 0;
        $totalBayar = 0;
        $angsuranTerakhir = $angsuran;
        $tahunan = [];
        $deret = [['month' => 0, 'balance' => $pokok, 'cumulative_interest' => 0]];

        for ($bulan = 1; $bulan <= $months && $sisa > 0; $bulan++) {
            if ($adaTahapKedua && $bulan === $fixedMonths + 1) {
                $i = $floatingRate / 100 / 12;
                $angsuran = $angsuranMengambang = $this->annuityInstallment($sisa, $i, $months - $fixedMonths);
            }

            $bunga = (int) round($sisa * $i);
            $pokokDibayar = ($bulan === $months) ? $sisa : min($sisa, $angsuran - $bunga);
            $bayar = $pokokDibayar + $bunga;

            $sisa -= $pokokDibayar;
            $totalBunga += $bunga;
            $totalBayar += $bayar;
            $angsuranTerakhir = $bayar;

            $tahun = (int) ceil($bulan / 12);
            $tahunan[$tahun] ??= ['year' => $tahun, 'principal_paid' => 0, 'interest_paid' => 0, 'balance' => 0];
            $tahunan[$tahun]['principal_paid'] += $pokokDibayar;
            $tahunan[$tahun]['interest_paid'] += $bunga;
            $tahunan[$tahun]['balance'] = $sisa;

            if ($bulan % 12 === 0 || $sisa === 0) {
                $deret[] = ['month' => $bulan, 'balance' => $sisa, 'cumulative_interest' => $totalBunga];
            }
        }

        return [
            'monthly_installment' => $angsuranAwal,
            'last_installment' => $angsuranTerakhir,
            'installment_after_float' => $angsuranMengambang,
            'fixed_months' => $adaTahapKedua ? $fixedMonths : null,
            'principal' => $pokok,
            'total_payment' => $totalBayar,
            'total_interest' => $totalBunga,
            'monthly_rate' => $annualInterestRate / 100 / 12,
            'months' => $months,
            'yearly' => array_values($tahunan),
            'series' => $deret,
        ];
    }

    /** Angsuran anuitas dalam rupiah penuh, dibulatkan ke atas. */
    private function annuityInstallment(int $pokok, float $i, int $bulan): int
    {
        return ($i === 0.0)
            ? (int) ceil($pokok / $bulan)
            : (int) ceil($pokok * $i / (1 - (1 + $i) ** -$bulan));
    }

    /**
     * FR-36: jumlah bulan PALING SEDIKIT sampai setoran `$monthlyContribution`
     * cukup untuk mencapai target — dasar tawaran "mundurkan tanggal target".
     *
     * Dicari satu per satu lewat calculateMonthlyContribution(), bukan dengan
     * rumus logaritma kebalikannya: inflasi ikut menaikkan target setiap bulan
     * tambahan, dan pembulatan ke atas setorannya membuat bentuk tertutup
     * meleset satu bulan di tepinya. Memakai fungsi yang sama menjamin angka
     * yang ditawarkan persis cocok dengan angka yang akan tersimpan.
     *
     * NULL bila tidak tercapai dalam `$maxMonths` — terjadi bila inflasi lebih
     * tinggi dari imbal hasil dan setorannya terlalu kecil untuk mengejarnya.
     */
    public function monthsToReach(
        float $targetAmount,
        float $currentAmount,
        float $monthlyContribution,
        float $annualReturnRate,
        float $annualInflationRate = 0.0,
        int $fromMonths = 1,
        int $maxMonths = 600,
    ): ?int {
        for ($bulan = max(1, $fromMonths); $bulan <= $maxMonths; $bulan++) {
            $perlu = $this->calculateMonthlyContribution(
                $targetAmount, $currentAmount, $bulan, $annualReturnRate, $annualInflationRate,
            )['monthly_contribution_required'];

            if ($perlu <= $monthlyContribution) {
                return $bulan;
            }
        }

        return null;
    }

    /**
     * FR-36: nominal target TERBESAR (nilai hari ini) yang masih tercapai
     * dengan setoran `$monthlyContribution` selama `$months` bulan — dasar
     * tawaran "turunkan nominal target".
     *
     * Kebalikan calculateMonthlyContribution(): nilai masa depan yang bisa
     * dikumpulkan, lalu dikempiskan kembali oleh inflasi (D-1). Dibulatkan
     * KE BAWAH ke ribuan rupiah — sama alasannya dengan setoran yang
     * dibulatkan ke atas: pembulatan tidak boleh membuat target meleset — lalu
     * diperiksa ulang dengan fungsi aslinya supaya galat float di tepi
     * pembulatan tidak lolos.
     */
    public function affordableTarget(
        float $currentAmount,
        float $monthlyContribution,
        int $months,
        float $annualReturnRate,
        float $annualInflationRate = 0.0,
    ): int {
        $this->guard(1.0, $currentAmount, $months, $annualReturnRate, $annualInflationRate);

        $monthlyRate = $this->monthlyRate($annualReturnRate);
        $growthFactor = ($monthlyRate === 0.0) ? 1.0 : (1 + $monthlyRate) ** $months;

        $terkumpul = ($monthlyRate === 0.0)
            ? $currentAmount + $monthlyContribution * $months
            : $currentAmount * $growthFactor + $monthlyContribution * ($growthFactor - 1) / $monthlyRate;

        $faktorInflasi = (1 + $annualInflationRate / 100) ** ($months / 12);
        $target = (int) (floor($terkumpul / $faktorInflasi / 1000) * 1000);

        while ($target > 0 && $this->calculateMonthlyContribution(
            $target, $currentAmount, $months, $annualReturnRate, $annualInflationRate,
        )['monthly_contribution_required'] > $monthlyContribution) {
            $target -= 1000;
        }

        return max(0, $target);
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
