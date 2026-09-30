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
     * @param  array<int, array{label: string, installment: int, year?: int|null}>  $stages
     *     keadaan SESUDAH angsuran sekarang, urut waktu — tiap jenjang bunga
     *     berjenjang ("mulai tahun ke-5", `year` 5), atau "bila bunga naik ke
     *     11%" untuk bunga mengambang (tanpa `year`). Kosong untuk bunga tetap.
     * @param  float  $incomeGrowth  perkiraan kenaikan pendapatan per tahun, persen.
     *     Menaikkan pendapatan tiap jenjang ber-`year`; keadaan tanpa `year`
     *     tetap memakai pendapatan sekarang — asumsi paling hati-hati.
     *     Pengeluaran, cicilan lain, dan pajak TIDAK ikut dinaikkan.
     * @return array{
     *     status: string,
     *     now: array{income: int, installments: int, dsr: float, residual: int},
     *     worst: array{income: int, installments: int, dsr: float, residual: int, label: string}|null,
     *     monthly_taxes: int,
     *     income_growth: float,
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
        float $incomeGrowth = 0.0,
    ): array {
        $pajakBulanan = (int) round($annualTaxes / 12);
        $nilai = fn (int $angsuran, float $pendapatan) => $this->keadaan($pendapatan, $otherInstallments, $monthlyExpenses, $pajakBulanan, $angsuran);

        // Pendapatan di awal tahun ke-N: naik (N − 1) kali dari sekarang.
        $pendapatanTahun = fn (?int $tahun) => $tahun === null
            ? $monthlyIncome
            : $monthlyIncome * (1 + $incomeGrowth / 100) ** max(0, $tahun - 1);

        $sekarang = $nilai($installment, $monthlyIncome);
        $tahap = array_map(
            fn ($s) => $nilai($s['installment'], $pendapatanTahun($s['year'] ?? null)) + ['label' => $s['label']],
            $stages,
        );

        // Terberat = keadaan dengan RASIO cicilan tertinggi, bukan angsuran
        // terbesar: dengan gaji yang naik, jenjang berangsuran terbesar bisa
        // justru lebih ringan dari jenjang di tengah. Hanya bila memang lebih
        // berat dari sekarang — bunga yang turun, atau gaji yang naik lebih
        // cepat dari angsuran, tidak perlu dinilai terpisah.
        $terberat = null;
        foreach ($tahap as $t) {
            if ($t['dsr'] > ($terberat['dsr'] ?? $sekarang['dsr'])) {
                $terberat = $t;
            }
        }

        // Jenjang PERTAMA yang melewati batas sehat — pada bunga berjenjang,
        // kapan masalahnya mulai sering lebih berguna daripada seberapa berat
        // puncaknya. Hanya disebut bila bukan jenjang terberat itu sendiri.
        $mulaiMelewati = null;
        if ($sekarang['dsr'] <= config('loan_health.dsr_healthy_max')) {
            foreach ($tahap as $t) {
                if ($t['dsr'] > config('loan_health.dsr_healthy_max')) {
                    $mulaiMelewati = $t['label'] !== ($terberat['label'] ?? null) ? $t : null;
                    break;
                }
            }
        }

        // Kenaikan gaji yang menutup kenaikan angsuran: angsurannya naik,
        // tetapi rasionya tidak pernah melebihi tahun pertama. Disebut, supaya
        // pengguna tahu labelnya bergantung pada asumsi kenaikan gaji itu.
        $diselamatkanGaji = $incomeGrowth > 0 && $terberat === null
            && collect($stages)->contains(fn ($s) => $s['installment'] > $installment && ($s['year'] ?? null) !== null);

        $dinilai = $terberat ?? $sekarang;

        return [
            'status' => $this->terburuk($this->statusDsr($dinilai['dsr']), $this->statusSisa($dinilai['residual'], $dinilai['income'])),
            'now' => $sekarang,
            'worst' => $terberat,
            'monthly_taxes' => $pajakBulanan,
            'income_growth' => $incomeGrowth,
            'reasons' => $this->alasan($sekarang, $terberat, $otherInstallments > 0, $mulaiMelewati, $incomeGrowth, $diselamatkanGaji),
            // Dikirim supaya teks patokan di halaman selalu sama dengan yang
            // benar-benar dipakai menilai, meski config-nya disetel ulang.
            'thresholds' => [
                'dsr_healthy_max' => config('loan_health.dsr_healthy_max'),
                'dsr_caution_max' => config('loan_health.dsr_caution_max'),
                'residual_min_percentage' => config('loan_health.residual_min_percentage'),
            ],
        ];
    }

    /** @return array{income: int, installments: int, dsr: float, residual: int} */
    private function keadaan(float $pendapatan, float $cicilanLain, float $pengeluaran, int $pajakBulanan, int $angsuran): array
    {
        $semuaCicilan = $angsuran + (int) round($cicilanLain);

        return [
            'income' => (int) round($pendapatan),
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
    private function alasan(array $sekarang, ?array $terberat, bool $adaCicilanLain, ?array $mulaiMelewati, float $kenaikanGaji, bool $diselamatkanGaji): array
    {
        $sehat = config('loan_health.dsr_healthy_max');
        $waspada = config('loan_health.dsr_caution_max');
        $minSisa = config('loan_health.residual_min_percentage');
        $rupiah = fn (int $n) => 'Rp '.number_format(abs($n), 0, ',', '.');
        $persen = fn (float $n) => str_replace('.', ',', (string) $n).'%';

        // Kalimat pertama MENYATAKAN posisinya terhadap batas, bukan sekadar
        // menyebut batasnya — "39% (patokan sehat: sampai 30%)" memaksa pembaca
        // membandingkan sendiri, dan mudah terbaca seolah masih aman.
        $cicilan = $adaCicilanLain ? 'KPR ditambah cicilan lain' : 'Angsuran KPR';
        $awal = "{$cicilan} memakan {$persen($sekarang['dsr'])} pendapatan";
        $kalimat = [match (true) {
            $sekarang['dsr'] <= $sehat => "{$awal} — masih dalam batas sehat {$sehat}%.",
            $sekarang['dsr'] <= $waspada => "{$awal} — sudah di atas batas sehat {$sehat}% sejak tahun pertama.",
            default => "{$awal} — di atas batas {$waspada}% sejak tahun pertama.",
        }];

        if ($mulaiMelewati) {
            $kalimat[] = ucfirst($mulaiMelewati['label']).", rasionya sudah {$persen($mulaiMelewati['dsr'])} — melewati batas sehat {$sehat}%.";
        }

        if ($terberat) {
            $kalimat[] = ucfirst($terberat['label']).", rasionya menjadi {$persen($terberat['dsr'])}"
                .($terberat['dsr'] > $waspada ? " — di atas batas {$waspada}%." : '.');

            if ($kenaikanGaji > 0 && $terberat['income'] !== $sekarang['income']) {
                $kalimat[] = "Angka itu sudah memperhitungkan kenaikan gaji {$persen($kenaikanGaji)} per tahun: pendapatan {$terberat['label']} diperkirakan {$rupiah($terberat['income'])}.";
            }
        }

        if ($diselamatkanGaji) {
            $kalimat[] = "Angsuran naik di jenjang berikutnya, tetapi dengan kenaikan gaji {$persen($kenaikanGaji)} per tahun rasionya tidak pernah melebihi tahun pertama. Penilaian ini bergantung pada kenaikan gaji itu benar-benar terjadi.";
        }

        $dinilai = $terberat ?? $sekarang;
        $kapan = $terberat ? " {$terberat['label']}" : '';

        if ($dinilai['residual'] < 0) {
            $kalimat[] = "Pengeluaran dan cicilan melebihi pendapatan{$kapan}: kurang {$rupiah($dinilai['residual'])} tiap bulan.";
        } elseif ($dinilai['income'] > 0 && $dinilai['residual'] < $dinilai['income'] * $minSisa / 100) {
            $kalimat[] = "Sisa uang{$kapan} hanya {$rupiah($dinilai['residual'])} per bulan — di bawah {$minSisa}% pendapatan, nyaris tanpa ruang untuk dana darurat.";
        } else {
            $kalimat[] = "Sisa uang{$kapan}: {$rupiah($dinilai['residual'])} per bulan, sesudah semua cicilan, pengeluaran, dan pajak.";
        }

        return $kalimat;
    }
}
