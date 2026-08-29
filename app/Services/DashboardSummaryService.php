<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Models\FinancialGoal;
use App\Models\GoalCalculation;
use App\Models\GoalContribution;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregasi Dashboard — satu-satunya tempat perhitungan ini dilakukan
 * (CLAUDE.md §6.9). Frontend (Dashboard.jsx) hanya menampilkan apa yang
 * dikembalikan di sini; jangan menjumlahkan ulang di React.
 *
 * "current_amount" tiap goal = initial_amount + SUM(goal_contributions.amount).
 * Nilai ini sengaja TIDAK disimpan sebagai kolom — dihitung tiap kali di
 * sini supaya tidak ada dua sumber kebenaran yang bisa saling menyimpang
 * begitu ada setoran diedit atau dihapus (lihat catatan di migrasi
 * goal_contributions).
 */
class DashboardSummaryService
{
    private const ASSET_GROWTH_MONTHS = 12;

    private const RECENT_ACTIVITY_LIMIT = 10;

    /**
     * @return array{
     *     total_assets: float,
     *     total_target: float,
     *     overall_progress_percentage: float,
     *     active_goals_count: int,
     *     goals: array<int, array>,
     *     primary_goal: array|null,
     *     asset_growth_series: array<int, array{month: string, cumulative_amount: float}>,
     *     contribution_calendar: array<int, array{date: string, amount: float}>,
     *     recent_activity: array<int, array>,
     * }
     */
    public function forUser(User $user): array
    {
        $activeGoals = $user->goals()
            ->where('status', GoalStatus::Active->value)
            ->withSum('contributions as contributions_sum', 'amount')
            ->orderBy('created_at')
            ->get();

        $goalSummaries = $activeGoals->map(fn (FinancialGoal $goal) => $this->summarizeGoal($goal));

        $totalAssets = round((float) $goalSummaries->sum('current_amount'), 2);
        $totalTarget = round((float) $goalSummaries->sum('target_amount'), 2);

        return [
            'total_assets' => $totalAssets,
            'total_target' => $totalTarget,
            'overall_progress_percentage' => $this->percentage($totalAssets, $totalTarget),
            'active_goals_count' => $activeGoals->count(),
            'goals' => $goalSummaries->values()->all(),
            // Goal tertua (pertama dibuat) — dipakai kartu utama Dashboard.
            // NULL kalau belum ada goal aktif sama sekali.
            'primary_goal' => $this->primaryGoal($activeGoals->first()),
            'asset_growth_series' => $this->assetGrowthSeries($user),
            'contribution_calendar' => $this->contributionCalendar($user),
            'recent_activity' => $this->recentActivity($user),
        ];
    }

    /**
     * Kartu utama Dashboard menambahkan `daily_savings_target` (nominal
     * yang pengguna janjikan disisihkan tiap hari, diisi manual — lihat
     * migrasi `add_daily_savings_target_to_financial_goals_table`) di atas
     * `current_amount` untuk memproyeksikan progres, TANPA mengubah
     * `current_amount` itu sendiri — itu tetap murni initial + setoran.
     */
    private function primaryGoal(?FinancialGoal $goal): ?array
    {
        if (! $goal) {
            return null;
        }

        $currentAmount = round((float) $goal->initial_amount + (float) ($goal->contributions_sum ?? 0), 2);
        $targetAmount = round((float) $goal->target_amount, 2);
        $dailySavingsTarget = round((float) $goal->daily_savings_target, 2);
        $projectedAmount = round(min($targetAmount, $currentAmount + $dailySavingsTarget), 2);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'type' => $goal->type->value,
            'target_amount' => $targetAmount,
            'current_amount' => $currentAmount,
            'daily_savings_target' => $dailySavingsTarget,
            'projected_amount' => $projectedAmount,
            'progress_percentage' => $this->percentage($currentAmount, $targetAmount),
            'projected_progress_percentage' => $this->percentage($projectedAmount, $targetAmount),
            'target_date' => $goal->target_date?->toDateString(),
        ];
    }

    private function summarizeGoal(FinancialGoal $goal): array
    {
        $currentAmount = round((float) $goal->initial_amount + (float) ($goal->contributions_sum ?? 0), 2);
        $targetAmount = round((float) $goal->target_amount, 2);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'type' => $goal->type->value,
            'current_amount' => $currentAmount,
            'target_amount' => $targetAmount,
            // NULL untuk dana darurat (tanpa tenggat) — dikirim apa
            // adanya, frontend yang memutuskan cara menampilkannya.
            'progress_percentage' => $this->percentage($currentAmount, $targetAmount),
            'target_date' => $goal->target_date?->toDateString(),
        ];
    }

    private function percentage(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(min(100, ($numerator / $denominator) * 100), 2);
    }

    /**
     * Akumulasi bulanan dari dana awal + setoran, dibatasi N bulan
     * terakhir — array ini tidak boleh tumbuh tanpa batas.
     *
     * Pengelompokan per bulan dilakukan di PHP, bukan lewat fungsi tanggal
     * SQL (strftime()/to_char() berbeda sintaks antar driver). Volume
     * datanya kecil — setoran satu user dalam setahun — jadi ini bukan
     * masalah performa.
     */
    private function assetGrowthSeries(User $user): array
    {
        $months = self::ASSET_GROWTH_MONTHS;
        $since = Carbon::now()->subMonths($months - 1)->startOfMonth();

        $baseline = (float) FinancialGoal::query()
            ->where('user_id', $user->id)
            ->where('status', GoalStatus::Active->value)
            ->sum('initial_amount');

        $contributionsByMonth = GoalContribution::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_contributions.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->where('goal_contributions.contributed_on', '>=', $since->toDateString())
            ->get(['goal_contributions.contributed_on', 'goal_contributions.amount'])
            ->groupBy(fn ($row) => Carbon::parse($row->contributed_on)->format('Y-m'))
            ->map(fn (Collection $rows) => (float) $rows->sum('amount'));

        $series = [];
        $cumulative = $baseline;
        $cursor = $since->copy();

        for ($i = 0; $i < $months; $i++) {
            $key = $cursor->format('Y-m');
            $cumulative += (float) ($contributionsByMonth[$key] ?? 0);

            $series[] = [
                'month' => $key,
                'cumulative_amount' => round($cumulative, 2),
            ];

            $cursor->addMonth();
        }

        return $series;
    }

    /**
     * Total setoran per tanggal untuk BULAN BERJALAN saja (bukan histori
     * penuh) — dipakai kalender "Aktivitas Bulan Ini" di Dashboard.
     * Digabung dari seluruh goal aktif milik user, bukan cuma primary
     * goal, supaya kalender tetap benar begitu goal kedua/ketiga ada.
     */
    private function contributionCalendar(User $user): array
    {
        $start = Carbon::now()->startOfMonth()->toDateString();
        $end = Carbon::now()->endOfMonth()->toDateString();

        return GoalContribution::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_contributions.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->whereBetween('goal_contributions.contributed_on', [$start, $end])
            ->get(['goal_contributions.contributed_on', 'goal_contributions.amount'])
            ->groupBy(fn ($row) => Carbon::parse($row->contributed_on)->toDateString())
            ->map(fn (Collection $rows) => round((float) $rows->sum('amount'), 2))
            ->map(fn (float $amount, string $date) => ['date' => $date, 'amount' => $amount])
            ->values()
            ->all();
    }

    /**
     * Gabungan setoran + kalkulasi terbaru, dibatasi N item (FR-15).
     */
    private function recentActivity(User $user): array
    {
        $contributions = GoalContribution::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_contributions.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->orderByDesc('goal_contributions.created_at')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get([
                'goal_contributions.amount',
                'goal_contributions.created_at',
                'financial_goals.name as goal_name',
            ])
            ->map(fn ($row) => [
                'type' => 'contribution_recorded',
                'goal_name' => $row->goal_name,
                'amount' => (float) $row->amount,
                'occurred_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ]);

        $calculations = GoalCalculation::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_calculations.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->orderByDesc('goal_calculations.created_at')
            ->limit(self::RECENT_ACTIVITY_LIMIT)
            ->get([
                'goal_calculations.created_at',
                'financial_goals.name as goal_name',
            ])
            ->map(fn ($row) => [
                'type' => 'goal_calculation_completed',
                'goal_name' => $row->goal_name,
                'occurred_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ]);

        return $contributions
            ->concat($calculations)
            ->sortByDesc('occurred_at')
            ->take(self::RECENT_ACTIVITY_LIMIT)
            ->values()
            ->all();
    }
}
