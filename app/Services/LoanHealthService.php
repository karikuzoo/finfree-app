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
 *    Pajak tahunan tidak ikut di sini — DSR bank hanya menghitung cicilan.
 * 2. Sisa uang = pendapatan − semua cicilan − pengeluaran − pajak tahunan/12
 *    (PBB, pajak kendaraan, dan pajak tahunan lain, diisi sebagai satu total).
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
     * @param  array<int, array{label: string, installment: int}>  $stages
     *     keadaan SESUDAH angsuran sekarang, urut waktu — tiap jenjang bunga
     *     berjenjang ("mulai tahun ke-5"), atau "bila bunga naik ke 11%" untuk
     *     bunga mengambang. Kosong untuk bunga tetap.
     * @return array{
     *     status: string,
     *     now: array{installments: int, dsr: float, residual: int},
     *     worst: array{installments: int, dsr: float, residual: int, label: string}|null,
     *     monthly_taxes: int,
     *     reasons: array<int, string>,
     * }
     */
    public function evaluate(
        float $monthlyIncome,
        float $otherInstallments,
        float $monthlyExpenses,
        float $annualTaxes,
        int $installment,
        array $stages = [],
    ): array {
        $pajakBulanan = (int) round($annualTaxes / 12);
        $nilai = fn (int $angsuran) => $this->keadaan($monthlyIncome, $otherInstallments, $monthlyExpenses, $pajakBulanan, $angsuran);

        $sekarang = $nilai($installment);

        // Terberat = jenjang dengan angsuran terbesar, dan hanya bila memang
        // lebih berat dari sekarang (bunga yang TURUN tidak perlu dinilai).
        $tahapTerberat = null;
        foreach ($stages as $s) {
            if ($s['installment'] > ($tahapTerberat['installment'] ?? $installment)) {
                $tahapTerberat = $s;
            }
        }
        $terberat = $tahapTerberat ? $nilai($tahapTerberat['installment']) + ['label' => $tahapTerberat['label']] : null;

        // Jenjang PERTAMA yang melewati batas sehat — pada bunga berjenjang,
        // kapan masalahnya mulai sering lebih berguna daripada seberapa berat
        // puncaknya. Hanya disebut bila bukan jenjang terberat itu sendiri.
        $mulaiMelewati = null;
        if ($sekarang['dsr'] <= config('loan_health.dsr_healthy_max')) {
            foreach ($stages as $s) {
                $dsr = $nilai($s['installment'])['dsr'];
                if ($dsr > config('loan_health.dsr_healthy_max')) {
                    $mulaiMelewati = $s['label'] !== ($tahapTerberat['label'] ?? null) ? ['label' => $s['label'], 'dsr' => $dsr] : null;
                    break;
                }
            }
        }

        $dinilai = $terberat ?? $sekarang;
        $alasan = $this->alasan($monthlyIncome, $sekarang, $terberat, $otherInstallments > 0, $mulaiMelewati);

        return [
            'status' => $this->terburuk($this->statusDsr($dinilai['dsr']), $this->statusSisa($dinilai['residual'], $monthlyIncome)),
            'now' => $sekarang,
            'worst' => $terberat,
            'monthly_taxes' => $pajakBulanan,
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
    private function keadaan(float $pendapatan, float $cicilanLain, float $pengeluaran, int $pajakBulanan, int $angsuran): array
    {
        $semuaCicilan = $angsuran + (int) round($cicilanLain);

        return [
            'installments' => $semuaCicilan,
            'dsr' => $pendapatan > 0 ? round($semuaCicilan / $pendapatan * 100, 1) : 0.0,
            'residual' => (int) round($pendapatan - $semuaCicilan - $pengeluaran - $pajakBulanan),
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
    private function alasan(float $pendapatan, array $sekarang, ?array $terberat, bool $adaCicilanLain, ?array $mulaiMelewati = null): array
    {
        $sehat = config('loan_health.dsr_healthy_max');
        $waspada = config('loan_health.dsr_caution_max');
        $minSisa = config('loan_health.residual_min_percentage');
        $rupiah = fn (int $n) => 'Rp '.number_format(abs($n), 0, ',', '.');
        $persen = fn (float $n) => str_replace('.', ',', (string) $n).'%';

        $cicilan = $adaCicilanLain ? 'KPR ditambah cicilan lain' : 'Angsuran KPR';
        $kalimat = ["{$cicilan} memakan {$persen($sekarang['dsr'])} pendapatan (patokan sehat: sampai {$sehat}%)."];

        if ($mulaiMelewati) {
            $kalimat[] = ucfirst($mulaiMelewati['label']).", rasionya sudah {$persen($mulaiMelewati['dsr'])} — melewati batas sehat {$sehat}%.";
        }

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
            $kalimat[] = "Sisa uang{$kapan}: {$rupiah($dinilai['residual'])} per bulan, sesudah semua cicilan, pengeluaran, dan pajak.";
        }

        return $kalimat;
    }
}
