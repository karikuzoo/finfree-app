<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Concerns\ValidatesCalculatorQuery;
use App\Services\GoalCalculatorService;
use App\Services\LoanHealthService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kalkulator utilitas publik: Pinjaman/KPR (FR-41) dan Investasi (FR-42).
 * Bisa dipakai tanpa login (FR-44).
 *
 * Pola yang sama dengan GoalCalculatorController: GET dengan query string,
 * dihitung di server, hasilnya dikirim sebagai props. Query string membuat
 * hasilnya bisa dibagikan dan tetap terisi setelah dimuat ulang. Tidak ada
 * rumus di sini — semuanya di GoalCalculatorService (CLAUDE.md §6.8: jangan
 * tulis mesin hitung kedua).
 */
class UtilityCalculatorController extends Controller
{
    use ValidatesCalculatorQuery;

    /**
     * Jenis bunga (`rate_type`):
     *  - `fixed`    — satu bunga sepanjang tenor (`annual_interest_rate`).
     *  - `tiered`   — bunga berjenjang (`tiers`): tiap baris `rate` dan
     *                 `until_year`; baris terakhir berlaku sampai tenor habis.
     *                 `floating` menandai bunga yang masih perkiraan. "Tetap
     *                 lalu mengambang" adalah dua jenjang.
     *  - `floating` — mengambang sejak awal: angsuran dihitung dengan bunga
     *                 sekarang, lalu DIUJI dengan `floating_rate` sebagai
     *                 "bunga bila naik" (`stress`).
     *
     * Cek kesehatan (`health`) hanya dihitung bila `monthly_income` diisi —
     * bagian itu opsional, kalkulatornya tetap berguna tanpanya.
     */
    public function loan(
        Request $request,
        GoalCalculatorService $calculator,
        LoanHealthService $health,
    ): Response {
        if (! $request->has('principal')) {
            return Inertia::render('Calculator/Loan', ['input' => null, 'result' => null, 'stress' => null, 'health' => null]);
        }

        $input = $this->validateCalculatorQuery($request, 'calculator.loan', [
            'principal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'rate_type' => ['nullable', Rule::in(['fixed', 'tiered', 'floating'])],
            // Bunga berjenjang membawa bunganya sendiri per baris.
            'annual_interest_rate' => ['exclude_if:rate_type,tiered', 'required', 'numeric', 'min:0', 'max:50'],
            // 30 tahun — tenor KPR terpanjang yang umum ditawarkan bank.
            'months' => ['required', 'integer', 'min:1', 'max:360'],
            'tiers' => ['exclude_unless:rate_type,tiered', 'required', 'array', 'min:2', 'max:10'],
            'tiers.*.rate' => ['required', 'numeric', 'min:0', 'max:50'],
            'tiers.*.until_year' => ['nullable', 'integer', 'min:1', 'max:30'],
            'tiers.*.floating' => ['nullable', 'boolean'],
            'floating_rate' => ['exclude_unless:rate_type,floating', 'required', 'numeric', 'min:0', 'max:50'],
            'monthly_income' => ['nullable', 'numeric', 'min:1', 'max:999999999999'],
            'other_installments' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'monthly_expenses' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'annual_taxes' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            // Persen per tahun. Batas 30: kenaikan di atas itu bertahun-tahun
            // bukan asumsi perencanaan, dan justru membuat KPR tampak sehat.
            'income_growth' => ['nullable', 'numeric', 'min:0', 'max:30'],
        ], [
            'principal.required' => 'Pokok pinjaman wajib diisi.',
            'principal.min' => 'Pokok pinjaman harus lebih besar dari nol.',
            'annual_interest_rate.required' => 'Suku bunga wajib diisi. Isi 0 bila pinjamannya tanpa bunga.',
            'annual_interest_rate.max' => 'Suku bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'months.required' => 'Tenor wajib diisi.',
            'months.min' => 'Tenor minimal 1 bulan.',
            'months.max' => 'Tenor maksimal 360 bulan (30 tahun).',
            'rate_type.in' => 'Jenis bunga di tautan ini tidak dikenal — mungkin tautan dari versi lama. Pilih jenis bunganya lagi.',
            'tiers.min' =>'Bunga berjenjang butuh minimal dua jenjang. Bila bunganya sama sepanjang tenor, pilih "Tetap".',
            'tiers.max' => 'Paling banyak 10 jenjang.',
            'tiers.*.rate.required' => 'Isi bunga untuk setiap jenjang.',
            'tiers.*.rate.max' => 'Bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'floating_rate.required' => 'Isi perkiraan bunga bila naik. Bila belum tahu, pakai bunga sekarang ditambah 2–3 poin.',
            'floating_rate.max' => 'Bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'income_growth.max' => 'Kenaikan gaji di atas 30% per tahun terlalu optimistis untuk perencanaan cicilan 10–30 tahun.',
            'monthly_income.min' => 'Pendapatan harus lebih besar dari nol, atau kosongkan bila tidak ingin cek kesehatan cicilan.',
        ]);

        $jenis = $input['rate_type'] ?? 'fixed';
        $pokok = (float) $input['principal'];
        $bulan = (int) $input['months'];

        if ($jenis === 'tiered') {
            $jenjang = $this->tiersToMonths(array_values($input['tiers']), $bulan);
            $result = $calculator->calculateLoan($pokok, (float) $input['tiers'][0]['rate'], $bulan, array_slice($jenjang, 1));
        } else {
            $result = $calculator->calculateLoan($pokok, (float) $input['annual_interest_rate'], $bulan);
        }

        // Mengambang sejak awal: tabel utama memakai bunga sekarang, dan
        // skenario bunga naik dihitung terpisah dengan tenor penuh.
        $stress = $jenis === 'floating'
            ? $calculator->calculateLoan($pokok, (float) $input['floating_rate'], $bulan)
            : null;

        // Jenjang yang dinilai cek kesehatan: tiap jenjang berjenjang, atau
        // satu keadaan "bila bunga naik" untuk bunga mengambang.
        $tahap = match ($jenis) {
            'tiered' => array_map(fn ($t) => [
                'label' => $t['from_month'] === 1 ? 'tahun pertama' : 'mulai tahun ke-'.intdiv($t['from_month'] - 1, 12) + 1,
                'installment' => $t['installment'],
                // Tahun mulai jenjang — untuk menaikkan pendapatan sesuai
                // perkiraan kenaikan gaji (LoanHealthService).
                'year' => intdiv($t['from_month'] - 1, 12) + 1,
            ], array_slice($result['tiers'], 1)),
            'floating' => [['label' => "bila bunga naik ke {$input['floating_rate']}%", 'installment' => $stress['monthly_installment']]],
            default => [],
        };

        return Inertia::render('Calculator/Loan', [
            'input' => $input,
            'result' => $result,
            'stress' => $stress ? ['monthly_installment' => $stress['monthly_installment'], 'total_interest' => $stress['total_interest']] : null,
            'health' => isset($input['monthly_income'])
                ? $health->evaluate(
                    monthlyIncome: (float) $input['monthly_income'],
                    otherInstallments: (float) ($input['other_installments'] ?? 0),
                    monthlyExpenses: (float) ($input['monthly_expenses'] ?? 0),
                    annualTaxes: (float) ($input['annual_taxes'] ?? 0),
                    installment: $result['monthly_installment'],
                    stages: $tahap,
                    incomeGrowth: (float) ($input['income_growth'] ?? 0),
                )
                : null,
        ]);
    }

    /**
     * Baris jenjang dari form ("sampai tahun ke-N") → bulan mulai tiap
     * jenjang untuk calculateLoan(). Baris terakhir berlaku sampai tenor
     * habis, jadi `until_year`-nya diabaikan.
     *
     * Diperiksa di sini, bukan dengan aturan validasi biasa: sah-tidaknya satu
     * baris bergantung pada baris sebelumnya dan pada tenor.
     *
     * @return array<int, array{from_month: int, rate: float}>
     */
    private function tiersToMonths(array $baris, int $tenor): array
    {
        $hasil = [];
        $mulai = 1;
        $terakhir = count($baris) - 1;

        foreach ($baris as $k => $b) {
            $hasil[] = ['from_month' => $mulai, 'rate' => (float) $b['rate']];

            if ($k === $terakhir) {
                break;
            }

            $sampai = $b['until_year'] ?? null;
            $dariTahun = intdiv($mulai - 1, 12) + 1;

            if ($sampai === null) {
                $this->failCalculatorQuery('calculator.loan', [
                    "tiers.{$k}.until_year" => 'Isi sampai tahun ke berapa bunga ini berlaku.',
                ]);
            }

            if ((int) $sampai < $dariTahun) {
                $this->failCalculatorQuery('calculator.loan', [
                    "tiers.{$k}.until_year" => "Jenjang ini mulai tahun ke-{$dariTahun}, jadi batasnya paling cepat tahun ke-{$dariTahun}.",
                ]);
            }

            if ((int) $sampai * 12 >= $tenor) {
                $this->failCalculatorQuery('calculator.loan', [
                    "tiers.{$k}.until_year" => 'Jenjang ini sudah mencapai akhir tenor — jadikan jenjang terakhir, atau perpanjang tenornya.',
                ]);
            }

            $mulai = (int) $sampai * 12 + 1;
        }

        return $hasil;
    }


    public function investment(Request $request, GoalCalculatorService $calculator): Response
    {
        if (! $request->has('monthly_contribution')) {
            return Inertia::render('Calculator/Investment', ['input' => null, 'result' => null]);
        }

        $input = $this->validateCalculatorQuery($request, 'calculator.investment', [
            'initial_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'monthly_contribution' => ['required', 'numeric', 'min:0', 'max:999999999999'],
            'months' => ['required', 'integer', 'min:1', 'max:720'],
            'annual_return_rate' => ['required', 'numeric', 'min:0', 'max:30'],
        ], [
            'monthly_contribution.required' => 'Setoran bulanan wajib diisi. Isi 0 bila hanya ingin melihat pertumbuhan dana awal.',
            'months.required' => 'Jangka waktu wajib diisi.',
            'months.min' => 'Jangka waktu minimal 1 bulan.',
            'months.max' => 'Jangka waktu maksimal 720 bulan (60 tahun).',
            'annual_return_rate.required' => 'Estimasi imbal hasil wajib diisi.',
            'annual_return_rate.max' => 'Estimasi imbal hasil di atas 30% per tahun tidak realistis untuk perencanaan jangka panjang.',
        ]);

        // Dana awal dan setoran sama-sama nol tidak menghasilkan apa pun untuk
        // ditampilkan; dikatakan terus terang daripada menampilkan Rp 0.
        if ((float) ($input['initial_amount'] ?? 0) <= 0 && (float) $input['monthly_contribution'] <= 0) {
            $this->failCalculatorQuery('calculator.investment', [
                'monthly_contribution' => 'Isi dana awal, setoran bulanan, atau keduanya.',
            ]);
        }

        return Inertia::render('Calculator/Investment', [
            'input' => $input,
            'result' => $calculator->projectInvestment(
                initialAmount: (float) ($input['initial_amount'] ?? 0),
                monthlyContribution: (float) $input['monthly_contribution'],
                months: (int) $input['months'],
                annualReturnRate: (float) $input['annual_return_rate'],
            ),
        ]);
    }
}
