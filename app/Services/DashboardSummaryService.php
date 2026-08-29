<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Enums\RiskProfile;
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

    public function __construct(
        private readonly InvestmentAllocationService $allocations,
    ) {
    }

    /**
     * @return array{
     *     total_assets: float,
     *     total_target: float,
     *     overall_progress_percentage: float,
     *     active_goals_count: int,
     *     goals: array<int, array>,
     *     primary_goal: array|null,
     *     streak_days: int,
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

        $goalSummaries = $activeGoals->map(
            fn (FinancialGoal $goal) => $this->summarizeGoal($goal, $user->risk_profile),
        );

        $totalAssets = round((float) $goalSummaries->sum('current_amount'), 2);
        $totalTarget = round((float) $goalSummaries->sum('target_amount'), 2);

        return [
            'total_assets' => $totalAssets,
            'total_target' => $totalTarget,
            'overall_progress_percentage' => $this->percentage($totalAssets, $totalTarget),
            'active_goals_count' => $activeGoals->count(),
            // Dipakai selector goal di GoalHeroCard — tiap item punya bentuk
            // yang sama persis dengan primary_goal (lihat summarizeGoal),
            // jadi frontend cukup ganti goal mana yang ditonjolkan tanpa
            // request tambahan.
            'goals' => $goalSummaries->values()->all(),
            // Goal tertua (pertama dibuat) — dipilih GoalHeroCard secara
            // default. NULL kalau belum ada goal aktif sama sekali.
            'primary_goal' => $goalSummaries->first(),
            'streak_days' => $this->streakDays($user),
            'asset_growth_series' => $this->assetGrowthSeries($user),
            'contribution_calendar' => $this->contributionCalendar($user),
            'recent_activity' => $this->recentActivity($user),
        ];
    }

    /**
     * Menambahkan beberapa hal di atas ringkasan dasar (current_amount vs
     * target_amount):
     *
     * - `daily_savings_target` + `projected_amount`: proyeksi progres kalau
     *   janji harian (diisi manual, lihat migrasi
     *   `add_daily_savings_target_to_financial_goals_table`) ditambahkan
     *   ke current_amount — BUKAN nilai tersimpan baru.
     * - `days_remaining`: NULL untuk goal tanpa target_date (dana darurat).
     * - `on_track`: null | 'on_track' | 'behind'. Dihitung dari progres
     *   waktu linear antara created_at dan target_date dibanding progres
     *   dana aktual — pendekatan yang disengaja sederhana, BUKAN memakai
     *   `monthly_contribution_required` dari GoalCalculation (yang formula
     *   annuity-nya tidak linear), supaya penjelasannya mudah dipahami
     *   pengguna: "andai disisihkan rata rata, seharusnya sudah sejauh
     *   mana". NULL untuk goal tanpa target_date atau yang baru dibuat
     *   hari yang sama (pembagi durasi = 0).
     * - `suggested_allocation`: lihat InvestmentAllocationService — tabel
     *   ILUSTRATIF, bukan hasil kajian produk.
     */
    private function summarizeGoal(FinancialGoal $goal, RiskProfile $accountRiskProfile): array
    {
        $currentAmount = round((float) $goal->initial_amount + (float) ($goal->contributions_sum ?? 0), 2);
        $targetAmount = round((float) $goal->target_amount, 2);
        $dailySavingsTarget = round((float) $goal->daily_savings_target, 2);
        $projectedAmount = round(min($targetAmount, $currentAmount + $dailySavingsTarget), 2);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            'type' => $goal->type->value,
            'current_amount' => $currentAmount,
            'target_amount' => $targetAmount,
            'daily_savings_target' => $dailySavingsTarget,
            'projected_amount' => $projectedAmount,
            // NULL untuk dana darurat (tanpa tenggat) — dikirim apa
            // adanya, frontend yang memutuskan cara menampilkannya.
            'progress_percentage' => $this->percentage($currentAmount, $targetAmount),
            'projected_progress_percentage' => $this->percentage($projectedAmount, $targetAmount),
            'target_date' => $goal->target_date?->toDateString(),
            'days_remaining' => $goal->target_date
                ? max(0, (int) Carbon::now()->startOfDay()->diffInDays($goal->target_date, false))
                : null,
            'on_track' => $this->onTrackStatus($goal, $currentAmount, $targetAmount),
            'suggested_allocation' => $this->allocations->forGoal($goal, $accountRiskProfile),
        ];
    }

    /**
     * @return array{status: string, gap_amount: float}|null
     */
    private function onTrackStatus(FinancialGoal $goal, float $currentAmount, float $targetAmount): ?array
    {
        if (! $goal->target_date || $targetAmount <= 0) {
            return null;
        }

        $start = Carbon::parse($goal->created_at)->startOfDay();
        $end = Carbon::parse($goal->target_date)->startOfDay();
        $totalDays = $start->diffInDays($end);

        if ($totalDays <= 0) {
            return null;
        }

        $elapsedDays = min($totalDays, max(0, $start->diffInDays(Carbon::now()->startOfDay())));
        $expectedAmount = round(($elapsedDays / $totalDays) * $targetAmount, 2);

        if ($currentAmount >= $expectedAmount) {
            return ['status' => 'on_track', 'gap_amount' => 0.0];
        }

        return ['status' => 'behind', 'gap_amount' => round($expectedAmount - $currentAmount, 2)];
    }

    private function percentage(float $numerator, float $denominator): float
    {
        if ($denominator <= 0) {
            return 0.0;
        }

        return round(min(100, ($numerator / $denominator) * 100), 2);
    }

    /**
     * Jumlah hari berturut-turut (sampai hari ini atau kemarin) pengguna
     * mencatat setidaknya satu setoran, digabung dari seluruh goal aktif.
     * Dihitung dari tanggal setoran (`contributed_on`), bukan waktu
     * pencatatannya (`created_at`) — mencatat telat untuk kemarin tetap
     * dihitung untuk kemarin.
     *
     * Kalau setoran terakhir lebih dari 1 hari yang lalu, streak dianggap
     * putus (0) — bukan "0 hari lagi sampai putus", supaya sederhana bagi
     * pengguna: hari ini belum menabung TIDAK memutus streak-nya (dianggap
     * masih ada kesempatan sampai tengah malam), tapi absen 2 hari sudah
     * dianggap putus.
     */
    private function streakDays(User $user): int
    {
        $dates = GoalContribution::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_contributions.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->distinct()
            ->orderByDesc('goal_contributions.contributed_on')
            ->pluck('goal_contributions.contributed_on')
            ->map(fn ($date) => Carbon::parse($date)->toDateString())
            ->unique()
            ->values();

        if ($dates->isEmpty()) {
            return 0;
        }

        $mostRecent = Carbon::parse($dates->first());

        if ($mostRecent->diffInDays(Carbon::today()) > 1) {
            return 0;
        }

        $streak = 1;
        $cursor = $mostRecent;

        for ($i = 1; $i < $dates->count(); $i++) {
            $expectedPrevious = $cursor->copy()->subDay()->toDateString();

            if ($dates[$i] !== $expectedPrevious) {
                break;
            }

            $streak++;
            $cursor = Carbon::parse($dates[$i]);
        }

        return $streak;
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
