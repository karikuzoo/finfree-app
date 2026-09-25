<?php

namespace App\Http\Controllers;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Http\Requests\UpdateBudgetRequest;
use App\Http\Requests\StoreGoalSetAsideRequest;
use App\Http\Requests\UpdateGoalAllocationRequest;
use App\Models\FinancialGoal;
use App\Services\AccountBalanceService;
use App\Services\LedgerGuard;
use App\Services\SavingsPlanService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
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

    /**
     * "Sudah saya sisihkan" — menaikkan dana yang ditandai sebesar nominal
     * yang dikirim (PRD FR-85).
     *
     * Menutup lingkaran umpan balik rencana. Sebelum ini halaman rencana
     * menyuruh menyisihkan sekian tiap bulan lalu tidak menyediakan cara
     * untuk mengatakan sudah — sehingga ia mengulang perintah yang sama
     * persis tiap bulan, tidak peduli diikuti atau tidak.
     *
     * LedgerGuard tetap berlaku, dan di sinilah ia paling berguna: menekan
     * tombol ini padahal saldo rekeningnya tidak cukup akan DITOLAK. Uang
     * yang belum ada tidak bisa ditandai, dan rencana yang mengaku berjalan
     * padahal dananya tidak pernah ada lebih buruk daripada rencana yang
     * jelas-jelas tertinggal.
     */
    public function setAside(
        StoreGoalSetAsideRequest $request,
        FinancialGoal $financialGoal,
    ): RedirectResponse {
        $user = $request->user();
        $nominal = (float) $request->validated()['amount'];

        // Tanpa rekening, tidak ada saldo yang bisa ditandai — dan tidak ada
        // pula yang membatasi angkanya. Diarahkan ke form alokasi, bukan
        // dibiarkan menandai uang yang tidak berasal dari mana pun.
        if ($financialGoal->account_id === null) {
            throw ValidationException::withMessages([
                'amount' => 'Tentukan dulu rekening tempat dana target ini berada.',
            ]);
        }

        $baru = round((float) $financialGoal->allocated_amount + $nominal, 2);
        $target = (float) $financialGoal->target_amount;

        if ($baru > $target) {
            $sisa = round($target - (float) $financialGoal->allocated_amount, 2);

            throw ValidationException::withMessages([
                'amount' => 'Melebihi nominal target. Sisa yang dibutuhkan tinggal '
                    .number_format($sisa, 0, ',', '.').'.',
            ]);
        }

        DB::transaction(function () use ($user, $financialGoal, $baru, $nominal) {
            $financialGoal->update(['allocated_amount' => $baru]);
            $this->penjaga->assertConsistent($user, 'amount');

            // Dicatat ke user_activities — dan ini BUKAN duplikasi seperti
            // yang sengaja dihindari untuk transaksi. Baris transaksi bisa
            // diturunkan kembali dari tabelnya sendiri; peristiwa ini tidak
            // terekam di mana pun. `allocated_amount` hanya angka berjalan:
            // kapan dan berapa ia naik tidak bisa dihitung dari apa pun.
            $user->activities()->create([
                'financial_goal_id' => $financialGoal->id,
                'type' => 'goal_set_aside',
                'goal_name' => $financialGoal->name,
                'amount' => $nominal,
            ]);
        });

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
