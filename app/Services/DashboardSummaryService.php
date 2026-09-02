<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Enums\RiskProfile;
use App\Models\CalendarNote;
use App\Models\FinancialGoal;
use App\Models\GoalCalculation;
use App\Models\GoalContribution;
use App\Models\Reminder;
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

        // startOfMonth() DULU, baru subMonths() — urutannya menentukan.
        //
        // Dibalik, hasilnya salah di akhir bulan: dari 31 Agustus,
        // subMonths(11) menghasilkan "31 September" yang tidak ada, lalu
        // meluber ke 1 Oktober. Jendela 12 bulannya jadi mulai sebulan
        // terlambat dan setoran bulan pertama hilang dari total kumulatif.
        // Tanggal 1 tidak pernah bisa meluber, jadi urutan ini aman.
        $since = Carbon::now()->startOfMonth()->subMonths($months - 1);

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
    /**
     * Data kalender untuk SATU bulan tertentu — setoran beserta catatan
     * pengguna di tanggal-tanggal bulan itu.
     *
     * Terpisah dari `forUser()` dan dikirim sebagai prop Inertia tersendiri
     * (`calendar`), bukan bagian dari `summary`. Alasannya: kalender bisa
     * digeser ke bulan lain, dan dengan prop terpisah pergeseran itu cukup
     * memuat ulang prop ini saja (`router.get(..., { only: ['calendar'] })`)
     * tanpa menghitung ulang seluruh agregasi dashboard — yang jauh lebih
     * mahal dan tidak berubah sama sekali saat pengguna sekadar melihat bulan
     * lalu.
     *
     * Tiap tanggal membawa `entries` — rincian setoran satu per satu beserta
     * catatan dan nama tujuannya. Tanpa itu, catatan yang ditulis pengguna di
     * form "Catat Setoran" tidak pernah terlihat lagi di mana pun: kalender
     * hanya menampilkan totalnya, dan halaman riwayat setoran belum ada.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     contributions: array<int, array{
     *         date: string,
     *         amount: float,
     *         entries: array<int, array{amount: float, note: string|null, goal: string}>
     *     }>,
     *     notes: array<int, array{id: int, date: string, body: string}>
     * }
     */
    public function calendarForMonth(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

        $contributions = GoalContribution::query()
            ->join('financial_goals', 'financial_goals.id', '=', 'goal_contributions.financial_goal_id')
            ->where('financial_goals.user_id', $user->id)
            ->whereBetween('goal_contributions.contributed_on', [$start->toDateString(), $end->toDateString()])
            ->orderBy('goal_contributions.contributed_on')
            ->orderBy('goal_contributions.id')
            ->get([
                'goal_contributions.contributed_on',
                'goal_contributions.amount',
                'goal_contributions.note',
                'financial_goals.name as goal_name',
            ])
            ->groupBy(fn ($row) => Carbon::parse($row->contributed_on)->toDateString())
            ->map(fn (Collection $rows, string $date) => [
                'date' => $date,
                'amount' => round((float) $rows->sum('amount'), 2),
                'entries' => $rows->map(fn ($row) => [
                    'amount' => round((float) $row->amount, 2),
                    'note' => $row->note,
                    'goal' => $row->goal_name,
                ])->values()->all(),
            ])
            ->values()
            ->all();

        $notes = CalendarNote::query()
            ->where('user_id', $user->id)
            ->whereBetween('note_date', [$start->toDateString(), $end->toDateString()])
            ->orderBy('note_date')
            ->get()
            ->map(fn (CalendarNote $note) => [
                'id' => $note->id,
                'date' => $note->note_date->toDateString(),
                'body' => $note->body,
            ])
            ->all();

        $reminders = Reminder::query()
            ->where('user_id', $user->id)
            ->inMonth($start)
            ->orderBy('remind_at')
            ->get()
            ->map(fn (Reminder $reminder) => [
                'id' => $reminder->id,
                'date' => $reminder->remind_at->toDateString(),
                'time' => $reminder->remind_at->format('H:i'),
                'title' => $reminder->title,
                'completed' => $reminder->isCompleted(),
            ])
            ->all();

        return [
            'month' => $start->format('Y-m'),
            'label' => $start->translatedFormat('F Y'),
            'contributions' => $contributions,
            'notes' => $notes,
            'reminders' => $reminders,
        ];
    }

    /**
     * Pengingat untuk HARI INI saja — dipakai panel ringkas di Dashboard.
     *
     * Yang sudah ditandai selesai tetap ikut, supaya pengguna melihat apa yang
     * sudah dikerjakan hari ini, bukan hanya sisa pekerjaannya. Frontend yang
     * menampilkannya berbeda.
     *
     * @return array<int, array{id:int, time:string, title:string, completed:bool, past:bool}>
     */
    public function remindersForToday(User $user): array
    {
        $sekarang = Carbon::now();

        return Reminder::query()
            ->where('user_id', $user->id)
            ->onDate($sekarang)
            ->orderBy('remind_at')
            ->get()
            ->map(fn (Reminder $reminder) => [
                'id' => $reminder->id,
                'time' => $reminder->remind_at->format('H:i'),
                'title' => $reminder->title,
                'completed' => $reminder->isCompleted(),
                // Jamnya sudah lewat tapi belum ditandai selesai — frontend
                // memakai ini untuk menonjolkannya, bukan menyembunyikannya.
                'past' => $reminder->remind_at->lessThan($sekarang),
            ])
            ->all();
    }

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
        return $user->activities()
            ->latest()
            ->take(self::RECENT_ACTIVITY_LIMIT)
            ->get()
            ->map(fn ($row) => [
                'type' => $row->type,
                'goal_name' => $row->goal_name,
                'amount' => (float) $row->amount,
                'occurred_at' => Carbon::parse($row->created_at)->toIso8601String(),
            ])
            ->all();
    }
}
