<?php

namespace App\Http\Controllers;

use App\Models\CalculationHistory;
use App\Services\CalculationHistoryService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman riwayat kalkulasi cepat (PRD FR-45). Pencatatannya sendiri ada di
 * controller kalkulator lewat CalculationHistoryService.
 *
 * Tidak ada "ubah": membuka kembali berarti membuka kalkulatornya dengan
 * isian tersimpan (`url`), dan hitungan baru dari sana tercatat sebagai
 * baris baru. Riwayat yang bisa disunting tidak lagi mencatat apa yang
 * pernah dihitung.
 */
class CalculationHistoryController extends Controller
{
    public function index(Request $request, CalculationHistoryService $history): Response
    {
        $baris = $request->user()->calculationHistories()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->get()
            ->map(fn (CalculationHistory $c) => [
                'id' => $c->id,
                'calculator' => $c->calculator,
                'input' => $c->input,
                'summary' => $c->summary,
                'url' => $history->url($c),
                'calculated_at' => $c->updated_at->toIso8601String(),
            ]);

        return Inertia::render('Calculator/History', [
            'histories' => $baris,
            'limit' => CalculationHistoryService::LIMIT,
        ]);
    }

    public function destroy(Request $request, CalculationHistory $calculationHistory): RedirectResponse
    {
        abort_unless($calculationHistory->user_id === $request->user()->id, 403);

        $calculationHistory->delete();

        return back();
    }

    public function clear(Request $request): RedirectResponse
    {
        $request->user()->calculationHistories()->delete();

        return back();
    }
}
