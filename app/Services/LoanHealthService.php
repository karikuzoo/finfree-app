<?php

namespace App\Services;

/**
 * Cek kesehatan cicilan KPR — bagian tambahan di kalkulator Pinjaman.
 *
 * Tiga ukuran, dinilai pada DUA keadaan: angsuran sekarang, dan angsuran
 * terberat (sesudah bunga mengambang, atau bila bunga naik). Yang dipakai
 * untuk label adalah yang terberat: di situlah cicilan paling mungkin tidak
 * tertanggung, dan KPR yang hanya sehat selama masa bunga promo bukan KPR
 * yang sehat.
 *
 * 1. Rasio cicilan (DSR) = (angsuran KPR + cicilan lain) ÷ pendapatan.
 *    PBB tidak ikut di sini — DSR bank hanya menghitung cicilan.
 * 2. Sisa uang = pendapatan − semua cicilan − pengeluaran − PBB/12.
 * 3. Label: status terburuk dari DSR dan sisa uang (batas di
 *    config/loan_health.php).
 *
 * Tidak ada rumus pinjaman di sini — angsurannya datang dari
 * GoalCalculatorService::calculateLoan(). Ini simulasi edukatif, bukan
 * penilaian kredit: bank menilai juga riwayat kredit, jenis pekerjaan, dan
 * agunan, yang tidak diketahui Arus.
 */
class LoanHealthService
{
    public const HEALTHY = 'healthy';

    public const CAUTION = 'caution';

    public const RISKY = 'risky';

    private const URUTAN = [self::HEALTHY => 0, self::CAUTION => 1, self::RISKY => 2];

    /**
     * @param  int|null  $worstInstallment  angsuran terberat; NULL bila sama dengan sekarang (bunga tetap)
     * @param  string|null  $worstLabel  keterangan keadaan terberat, mis. "setelah bunga mengambang"
     * @return array{
     *     status: string,
     *     now: array{installments: int, dsr: float, residual: int},
     *     worst: array{installments: int, dsr: float, residual: int, label: string}|null,
     *     monthly_property_tax: int,
     *     reasons: array<int, string>,
     * }
     */
    public function evaluate(
        float $monthlyIncome,
        float $otherInstallments,
        float $monthlyExpenses,
        float $annualPropertyTax,
        int $installment,
        ?int $worstInstallment = null,
        ?string $worstLabel = null,
    ): array {
        $pbbBulanan = (int) round($annualPropertyTax / 12);

        $sekarang = $this->keadaan($monthlyIncome, $otherInstallments, $monthlyExpenses, $pbbBulanan, $installment);
        $terberat = ($worstInstallment !== null && $worstInstallment > $installment)
            ? $this->keadaan($monthlyIncome, $otherInstallments, $monthlyExpenses, $pbbBulanan, $worstInstallment) + ['label' => $worstLabel ?? 'bila bunga naik']
            : null;

        $dinilai = $terberat ?? $sekarang;
        $alasan = $this->alasan($monthlyIncome, $sekarang, $terberat, $otherInstallments > 0);

        return [
            'status' => $this->terburuk($this->statusDsr($dinilai['dsr']), $this->statusSisa($dinilai['residual'], $monthlyIncome)),
            'now' => $sekarang,
            'worst' => $terberat,
            'monthly_property_tax' => $pbbBulanan,
            'reasons' => $alasan,
            // Dikirim supaya teks patokan di halaman selalu sama dengan yang
            // benar-benar dipakai menilai, meski config-nya disetel ulang.
            'thresholds' => [
                'dsr_healthy_max' => config('loan_health.dsr_healthy_max'),
                'dsr_caution_max' => config('loan_health.dsr_caution_max'),
                'residual_min_percentage' => config('loan_health.residual_min_percentage'),
            ],
        ];
    }

    /** @return array{installments: int, dsr: float, residual: int} */
    private function keadaan(float $pendapatan, float $cicilanLain, float $pengeluaran, int $pbbBulanan, int $angsuran): array
    {
        $semuaCicilan = $angsuran + (int) round($cicilanLain);

        return [
            'installments' => $semuaCicilan,
            'dsr' => $pendapatan > 0 ? round($semuaCicilan / $pendapatan * 100, 1) : 0.0,
            'residual' => (int) round($pendapatan - $semuaCicilan - $pengeluaran - $pbbBulanan),
        ];
    }

    private function statusDsr(float $dsr): string
    {
        return match (true) {
            $dsr <= config('loan_health.dsr_healthy_max') => self::HEALTHY,
            $dsr <= config('loan_health.dsr_caution_max') => self::CAUTION,
            default => self::RISKY,
        };
    }

    private function statusSisa(int $sisa, float $pendapatan): string
    {
        return match (true) {
            $sisa < 0 => self::RISKY,
            $sisa < $pendapatan * config('loan_health.residual_min_percentage') / 100 => self::CAUTION,
            default => self::HEALTHY,
        };
    }

    private function terburuk(string ...$status): string
    {
        usort($status, fn ($a, $b) => self::URUTAN[$b] <=> self::URUTAN[$a]);

        return $status[0];
    }

    /**
     * Kalimat yang menjelaskan labelnya — label tanpa alasan hanya bisa
     * dipercaya atau diabaikan, tidak bisa ditindaklanjuti.
     *
     * @return array<int, string>
     */
    private function alasan(float $pendapatan, array $sekarang, ?array $terberat, bool $adaCicilanLain): array
    {
        $sehat = config('loan_health.dsr_healthy_max');
        $waspada = config('loan_health.dsr_caution_max');
        $minSisa = config('loan_health.residual_min_percentage');
        $rupiah = fn (int $n) => 'Rp '.number_format(abs($n), 0, ',', '.');
        $persen = fn (float $n) => str_replace('.', ',', (string) $n).'%';

        $cicilan = $adaCicilanLain ? 'KPR ditambah cicilan lain' : 'Angsuran KPR';
        $kalimat = ["{$cicilan} memakan {$persen($sekarang['dsr'])} pendapatan (patokan sehat: sampai {$sehat}%)."];

        if ($terberat) {
            $kalimat[] = ucfirst($terberat['label']).", rasionya menjadi {$persen($terberat['dsr'])}"
                .($terberat['dsr'] > $waspada ? " — di atas batas {$waspada}%." : '.');
        }

        $dinilai = $terberat ?? $sekarang;
        $kapan = $terberat ? " {$terberat['label']}" : '';

        if ($dinilai['residual'] < 0) {
            $kalimat[] = "Pengeluaran dan cicilan melebihi pendapatan{$kapan}: kurang {$rupiah($dinilai['residual'])} tiap bulan.";
        } elseif ($pendapatan > 0 && $dinilai['residual'] < $pendapatan * $minSisa / 100) {
            $kalimat[] = "Sisa uang{$kapan} hanya {$rupiah($dinilai['residual'])} per bulan — di bawah {$minSisa}% pendapatan, nyaris tanpa ruang untuk dana darurat.";
        } else {
            $kalimat[] = "Sisa uang{$kapan}: {$rupiah($dinilai['residual'])} per bulan, sesudah semua cicilan, pengeluaran, dan PBB.";
        }

        return $kalimat;
    }
}
