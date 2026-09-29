<?php

namespace App\Http\Controllers;

use App\Services\GoalCalculatorService;
use Illuminate\Http\Request;
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
    public function loan(Request $request, GoalCalculatorService $calculator): Response
    {
        if (! $request->has('principal')) {
            return Inertia::render('Calculator/Loan', ['input' => null, 'result' => null]);
        }

        $input = $request->validate([
            'principal' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'annual_interest_rate' => ['required', 'numeric', 'min:0', 'max:50'],
            // 30 tahun — tenor KPR terpanjang yang umum ditawarkan bank.
            'months' => ['required', 'integer', 'min:1', 'max:360'],
        ], [
            'principal.required' => 'Pokok pinjaman wajib diisi.',
            'principal.min' => 'Pokok pinjaman harus lebih besar dari nol.',
            'annual_interest_rate.required' => 'Suku bunga wajib diisi. Isi 0 bila pinjamannya tanpa bunga.',
            'annual_interest_rate.max' => 'Suku bunga di atas 50% per tahun tidak lazim untuk pinjaman resmi. Periksa kembali angkanya.',
            'months.required' => 'Tenor wajib diisi.',
            'months.min' => 'Tenor minimal 1 bulan.',
            'months.max' => 'Tenor maksimal 360 bulan (30 tahun).',
        ]);

        return Inertia::render('Calculator/Loan', [
            'input' => $input,
            'result' => $calculator->calculateLoan(
                principal: (float) $input['principal'],
                annualInterestRate: (float) $input['annual_interest_rate'],
                months: (int) $input['months'],
            ),
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
