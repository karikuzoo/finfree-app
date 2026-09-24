<?php

namespace App\Http\Controllers;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Requests\UpdateGoalAllocationRequest;
use App\Models\FinancialGoal;
use App\Services\AccountBalanceService;
use App\Services\LedgerGuard;
use App\Services\SavingsPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rencana menabung (PRD FR-74..FR-78).
 *
 * Tiga aksi dalam satu controller karena ketiganya melayani satu halaman:
 * melihat rencananya, mengubah anggarannya, dan mengubah alokasi sebuah
 * target. Memecahnya jadi tiga controller hanya menyebar satu layar ke tiga
 * berkas tanpa ada yang bisa dipakai ulang.
 *
 * Seluruh angkanya datang jadi dari SavingsPlanService.
 */
class SavingsPlanController extends Controller
{
    public function __construct(
        private SavingsPlanService $rencana,
        private AccountBalanceService $saldo,
        private LedgerGuard $penjaga,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $saldo = $this->saldo->forUser($user);

        return Inertia::render('SavingsPlan/Index', [
            'plan' => $this->rencana->forUser($user),
            'goals' => $user->goals()
                ->with('account:id,name')
                ->orderBy('created_at')
                ->get()
                ->map(fn (FinancialGoal $g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'target_amount' => (float) $g->target_amount,
                    'allocated_amount' => (float) $g->allocated_amount,
                    'account_id' => $g->account_id,
                    'account' => $g->account?->name,
                    'priority' => $g->priority->value,
                ]),
            // Hanya rekening likuid: nilai saham dan emas bergerak sendiri,
            // sehingga target yang ditandai di sana bisa meleset diam-diam.
            'accounts' => $user->accounts()
                ->whereIn('kind', AccountKind::nilaiLikuid())
                ->orderBy('name')
                ->get(['id', 'name'])
                ->map(fn ($r) => [
                    'id' => $r->id,
                    'name' => $r->name,
                    'balance' => $saldo[$r->id] ?? 0.0,
                ]),
            'priorities' => collect(GoalPriority::cases())
                ->map(fn (GoalPriority $p) => ['value' => $p->value, 'label' => $p->label()]),
        ]);
    }

    /** Satu baris anggaran per pengguna — dibuat saat pertama kali disimpan. */
    public function updateBudget(UpdateBudgetRequest $request): RedirectResponse
    {
        $request->user()->budget()->updateOrCreate([], $request->validated());

        return back();
    }

    public function updateAllocation(
        UpdateGoalAllocationRequest $request,
        FinancialGoal $financialGoal,
    ): RedirectResponse {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $financialGoal) {
            $financialGoal->update($request->validated());
            $this->penjaga->assertConsistent($user, 'allocated_amount');
        });

        return back();
    }
}
