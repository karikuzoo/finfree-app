<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalContributionRequest;
use App\Models\FinancialGoal;
use Illuminate\Http\RedirectResponse;

/**
 * Mencatat setoran (PRD FR-32..FR-35) langsung dari kartu utama Dashboard
 * — tidak perlu halaman "Tujuan" terpisah yang belum dibangun. Setelah
 * disimpan, DashboardSummaryService otomatis menghitung ulang
 * current_amount, progres, dan kalender aktivitas di request berikutnya
 * (tidak ada nilai yang di-cache di sisi FinancialGoal).
 */
class GoalContributionController extends Controller
{
    public function store(StoreGoalContributionRequest $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $financialGoal->contributions()->create($request->validated());

        return back();
    }
}
