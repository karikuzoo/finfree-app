<?php

namespace App\Http\Controllers;

use App\Services\GoalCalculatorService;
use App\Services\LoanHealthService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
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
    /**
     * Jenis bunga (`rate_type`):
     *  - `fixed`      — satu bunga sepanjang tenor.
     *  - `fix_float`  — bunga tetap `fixed_years` tahun, lalu `floating_rate`;
     *                   angsuran dihitung ulang saat berganti (dua tahap).
     *  - `floating`   — mengambang sejak awal: angsuran dihitung dengan bunga
     *                   sekarang, lalu DIUJI dengan `floating_rate` sebagai
     *                   "bunga bila naik" (`stress`).
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

        $input = $request->validate([
            'principal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            // 30 tahun — tenor KPR terpanjang yang umum ditawarkan bank.
            'months' => ['required', 'integer', 'min:1', 'max:360'],
            'rate_type' => ['nullable', Rule::in(['fixed', 'fix_float', 'floating'])],
            'fixed_years' => ['exclude_unless:rate_type,fix_float', 'required', 'integer', 'min:1', 'max:30'],
            'floating_rate' => ['exclude_if:rate_type,fixed', 'exclude_without:rate_type', 'required', 'numeric', 'min:0', 'max:50'],
            'monthly_income' => ['nullable', 'numeric', 'min:1', 'max:999999999999'],
            'other_installments' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'monthly_expenses' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'annual_property_tax' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
        ], [
            'principal.required' => 'Pokok pinjaman wajib diisi.',
            'principal.min' => 'Pokok pinjaman harus lebih besar dari nol.',
            'annual_interest_rate.required' => 'Suku bunga wajib diisi. Isi 0 bila pinjamannya tanpa bunga.',
            'annual_interest_rate.max' => 'Suku bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'months.required' => 'Tenor wajib diisi.',
            'months.min' => 'Tenor minimal 1 bulan.',
            'months.max' => 'Tenor maksimal 360 bulan (30 tahun).',
            'fixed_years.required' => 'Isi berapa tahun bunganya tetap.',
            'fixed_years.min' => 'Masa bunga tetap minimal 1 tahun.',
            'floating_rate.required' => 'Isi perkiraan bunga mengambangnya. Bila belum tahu, pakai bunga tetap ditambah 2–3 poin.',
            'floating_rate.max' => 'Bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'monthly_income.min' => 'Pendapatan harus lebih besar dari nol, atau kosongkan bila tidak ingin cek kesehatan cicilan.',
        ]);

        $jenis = $input['rate_type'] ?? 'fixed';

        if ($jenis === 'fix_float' && $input['fixed_years'] * 12 >= $input['months']) {
            throw ValidationException::withMessages([
                'fixed_years' => 'Masa bunga tetap harus lebih pendek dari tenor. Bila bunganya tetap sepanjang tenor, pilih "Tetap".',
            ]);
        }

        $pokok = (float) $input['principal'];
        $bunga = (float) $input['annual_interest_rate'];
        $bulan = (int) $input['months'];

        $result = $jenis === 'fix_float'
            ? $calculator->calculateLoan($pokok, $bunga, $bulan, (float) $input['floating_rate'], (int) $input['fixed_years'] * 12)
            : $calculator->calculateLoan($pokok, $bunga, $bulan);

        // Mengambang sejak awal: tabel utama memakai bunga sekarang, dan
        // skenario bunga naik dihitung terpisah dengan tenor penuh.
        $stress = $jenis === 'floating'
            ? $calculator->calculateLoan($pokok, (float) $input['floating_rate'], $bulan)
            : null;

        [$terberat, $labelTerberat] = match ($jenis) {
            'fix_float' => [$result['installment_after_float'], "setelah {$input['fixed_years']} tahun bunga tetap"],
            'floating' => [$stress['monthly_installment'], "bila bunga naik ke {$input['floating_rate']}%"],
            default => [null, null],
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
                    annualPropertyTax: (float) ($input['annual_property_tax'] ?? 0),
                    installment: $result['monthly_installment'],
                    worstInstallment: $terberat,
                    worstLabel: $labelTerberat,
                )
                : null,
        ]);
    }

    public function investment(Request $request, GoalCalculatorService $calculator): Response
    {
        if (! $request->has('monthly_contribution')) {
            return Inertia::render('Calculator/Investment', ['input' => null, 'result' => null]);
        }

        $input = $request->validate([
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
            throw ValidationException::withMessages([
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
