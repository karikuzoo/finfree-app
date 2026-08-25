<?php

namespace App\Http\Controllers;

use App\Services\GoalCalculatorService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Kalkulator tujuan versi publik — bisa dipakai tanpa login (PRD FR-44).
 *
 * Sengaja memakai GET dengan query string, bukan POST. Alasannya: hasil
 * perhitungan jadi bisa di-bookmark dan dibagikan lewat tautan, tombol
 * "kembali" di browser bekerja wajar, dan tidak perlu menyimpan apa pun di
 * sesi. Untuk alat hitung yang tidak mengubah data apa pun, GET memang
 * bentuk yang tepat.
 *
 * Perhitungannya sendiri didelegasikan seluruhnya ke GoalCalculatorService —
 * controller ini tidak boleh memuat satu baris pun rumus (CLAUDE.md §6.6).
 */
class GoalCalculatorController extends Controller
{
    public function show(Request $request, GoalCalculatorService $calculator): Response
    {
        // Halaman dibuka pertama kali, belum ada yang dihitung.
        if (! $request->has('target_amount')) {
            return Inertia::render('Calculator/Goal', [
                'input' => null,
                'result' => null,
            ]);
        }

        $input = $request->validate([
            'target_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'current_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999'],
            'months' => ['required', 'integer', 'min:1', 'max:720'],
            'annual_return_rate' => ['required', 'numeric', 'min:0', 'max:30'],
            'annual_inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ], [
            'target_amount.required' => 'Nominal target wajib diisi.',
            'target_amount.min' => 'Nominal target harus lebih besar dari nol.',
            'months.required' => 'Jangka waktu wajib diisi.',
            'months.min' => 'Jangka waktu minimal 1 bulan.',
            'months.max' => 'Jangka waktu maksimal 720 bulan (60 tahun).',
            'annual_return_rate.required' => 'Estimasi imbal hasil wajib diisi.',
            'annual_return_rate.max' => 'Estimasi imbal hasil di atas 30% per tahun tidak realistis untuk perencanaan jangka panjang.',
            'annual_inflation_rate.max' => 'Estimasi inflasi di atas 20% per tahun tidak realistis.',
        ]);

        $result = $calculator->calculateMonthlyContribution(
            targetAmount: (float) $input['target_amount'],
            currentAmount: (float) ($input['current_amount'] ?? 0),
            months: (int) $input['months'],
            annualReturnRate: (float) $input['annual_return_rate'],
            annualInflationRate: (float) ($input['annual_inflation_rate'] ?? 0),
        );

        return Inertia::render('Calculator/Goal', [
            'input' => $input,
            'result' => $result,
        ]);
    }
}
