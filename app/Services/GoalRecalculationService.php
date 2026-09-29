<?php

namespace App\Services;

use App\Models\FinancialGoal;
use App\Models\GoalCalculation;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * FR-36: tawaran rekalkulasi saat realisasi meleset dari rencana.
 *
 * Tiga jalan keluar, masing-masing menahan dua hal dan mengubah satu:
 *
 * - `contribution` — tanggal dan nominal tetap, setoran bulanan dinaikkan.
 * - `date`         — setoran rencana dan nominal tetap, tanggal dimundurkan.
 * - `target`       — setoran rencana dan tanggal tetap, nominal diturunkan.
 *
 * Semuanya dihitung dari dana yang SUDAH ditandai (`allocated_amount`), bukan
 * dari `initial_amount` saat tujuan dibuat — justru selisih keduanya yang
 * membuat rencana lama meleset. Matematikanya tetap di GoalCalculatorService;
 * di sini hanya pemilihan input dan penulisan hasilnya.
 *
 * Menerapkan satu pilihan menulis snapshot baru ke `goal_calculations` dengan
 * kunci `recalculation` di dalam `calculation_snapshot`. Kunci itu menjadi
 * garis awal baru bagi status on-track (DashboardSummaryService): tanpanya,
 * status "tertinggal" tetap diukur dari hari tujuan dibuat, sehingga
 * tawarannya tidak pernah hilang walaupun pengguna sudah menerimanya.
 */
class GoalRecalculationService
{
    public const OPTIONS = ['contribution', 'date', 'target'];

    /** Batas pencarian tanggal baru: 50 tahun. Lebih dari itu bukan rencana. */
    private const MAX_MONTHS = 600;

    public function __construct(
        private readonly GoalCalculatorService $calculator,
    ) {
    }

    /**
     * Tawaran untuk satu tujuan, atau NULL bila tidak ada yang perlu
     * ditawarkan.
     *
     * Dipanggil hanya untuk tujuan yang statusnya "tertinggal". Syarat
     * tambahannya: setoran yang dibutuhkan SEKARANG memang lebih besar dari
     * rencana. Status tertinggal diukur linear, sedangkan rencana memakai
     * imbal hasil majemuk yang menumpuk di akhir — tujuan bisa tampak
     * tertinggal padahal setoran rencananya masih cukup. Menawarkan
     * "naikkan setoran" dengan angka yang sama atau lebih kecil hanya
     * membingungkan.
     *
     * @return array{
     *     required_monthly_contribution: int,
     *     planned_monthly_contribution: float,
     *     options: array{
     *         contribution: array{monthly_contribution: int},
     *         date: array{target_date: string, months_added: int}|null,
     *         target: array{target_amount: int}|null,
     *     },
     * }|null
     */
    public function optionsFor(FinancialGoal $goal): ?array
    {
        $rencana = $goal->latestCalculation
            ? (float) $goal->latestCalculation->monthly_contribution_required
            : 0.0;

        if (! $goal->target_date || $rencana <= 0) {
            return null;
        }

        $terkumpul = (float) $goal->allocated_amount;
        $target = (float) $goal->target_amount;

        if ($terkumpul >= $target) {
            return null;
        }

        $bulan = $this->monthsUntil($goal->target_date);
        $return = (float) $goal->estimated_return_rate;
        $inflasi = (float) $goal->estimated_inflation_rate;

        $perlu = $this->calculator->calculateMonthlyContribution(
            $target, $terkumpul, $bulan, $return, $inflasi,
        )['monthly_contribution_required'];

        if ($perlu <= $rencana) {
            return null;
        }

        $bulanBaru = $this->calculator->monthsToReach(
            $target, $terkumpul, $rencana, $return, $inflasi,
            fromMonths: $bulan + 1,
            maxMonths: self::MAX_MONTHS,
        );

        $targetBaru = $this->calculator->affordableTarget(
            $terkumpul, $rencana, $bulan, $return, $inflasi,
        );

        return [
            'required_monthly_contribution' => $perlu,
            'planned_monthly_contribution' => round($rencana, 2),
            'options' => [
                'contribution' => ['monthly_contribution' => $perlu],
                'date' => $bulanBaru === null ? null : [
                    'target_date' => $this->today()->addMonthsNoOverflow($bulanBaru)->toDateString(),
                    'months_added' => $bulanBaru - $bulan,
                ],
                // Target yang tidak lebih besar dari dana terkumpul bukan
                // lagi "diturunkan" melainkan dianggap selesai — itu
                // keputusan pengguna lewat form ubah, bukan tawaran.
                'target' => $targetBaru > $terkumpul && $targetBaru < $target
                    ? ['target_amount' => $targetBaru]
                    : null,
            ],
        ];
    }

    /**
     * Menerapkan satu pilihan. Angkanya dihitung ULANG di sini, tidak
     * diterima dari browser: tawaran di halaman bisa sudah basi (dana
     * bertambah di tab lain), dan nominal target yang dikirim klien tidak
     * boleh dipercaya begitu saja.
     *
     * @throws ValidationException bila pilihannya tidak (lagi) tersedia
     */
    public function apply(User $user, FinancialGoal $goal, string $option): void
    {
        $tawaran = $this->optionsFor($goal);
        $pilihan = $tawaran['options'][$option] ?? null;

        if ($pilihan === null) {
            throw ValidationException::withMessages([
                'option' => 'Pilihan ini tidak lagi tersedia. Muat ulang halaman untuk melihat tawaran terbaru.',
            ]);
        }

        DB::transaction(function () use ($user, $goal, $option, $pilihan) {
            match ($option) {
                'date' => $goal->update(['target_date' => $pilihan['target_date']]),
                'target' => $goal->update(['target_amount' => $pilihan['target_amount']]),
                'contribution' => null,
            };

            $terkumpul = round((float) $goal->allocated_amount, 2);

            $hasil = $this->calculator->calculateMonthlyContribution(
                (float) $goal->target_amount,
                $terkumpul,
                $this->monthsUntil($goal->target_date),
                (float) $goal->estimated_return_rate,
                (float) $goal->estimated_inflation_rate,
            );

            $hasil['recalculation'] = [
                'option' => $option,
                'baseline_amount' => $terkumpul,
            ];

            $goal->calculations()->create(GoalCalculation::fromCalculatorResult($hasil));

            $user->activities()->create([
                'financial_goal_id' => $goal->id,
                'type' => 'goal_recalculated',
                'goal_name' => $goal->name,
                'amount' => $goal->target_amount,
            ]);
        });
    }

    /**
     * Sama dengan GoalController::monthsUntil(): dibulatkan ke atas, minimal
     * 1. Tenggat yang sudah lewat menjadi 1 bulan — seluruh kekurangan jatuh
     * ke bulan ini, seperti di SavingsPlanService::monthsLeft().
     */
    private function monthsUntil(Carbon $targetDate): int
    {
        $bulan = $this->today()->diffInMonths($targetDate->copy()->startOfDay(), false);

        return max(1, (int) ceil($bulan));
    }

    private function today(): Carbon
    {
        return Carbon::now()->startOfDay();
    }
}
