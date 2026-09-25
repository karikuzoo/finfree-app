<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Enums\RiskProfile;
use App\Enums\TransactionType;
use App\Models\CalendarNote;
use App\Models\FinancialGoal;
use App\Models\GoalCalculation;
use App\Models\Reminder;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;

/**
 * Agregasi Dashboard — satu-satunya tempat perhitungan ini dilakukan
 * (CLAUDE.md §6.9). Frontend (Dashboard.jsx) hanya menampilkan apa yang
 * dikembalikan di sini; jangan menjumlahkan ulang di React.
 *
 * "current_amount" tiap goal = financial_goals.allocated_amount — dana yang
 * DITANDAI untuk tujuan itu di sebuah rekening. Sebelumnya ia dijumlahkan dari
 * setoran harian; pencatatan setoran sudah dipensiunkan dan digantikan model
 * alokasi, supaya uang yang sama tidak bisa dihitung dua kali di tempat
 * berbeda (lihat migrasi add_allocation_to_financial_goals_table).
 */
class DashboardSummaryService
{
    private const ASSET_GROWTH_MONTHS = 12;

    private const ASSET_GROWTH_DAYS = 30;

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
     *     asset_growth_series: array{monthly: array<int, array>, daily: array<int, array>},
     *     recent_activity: array<int, array>,
     * }
     */
    public function forUser(User $user): array
    {
        $activeGoals = $user->goals()
            ->where('status', GoalStatus::Active->value)
            ->with('latestCalculation')
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
            // Tujuan yang DIPILIH pengguna (users.primary_goal_id); bila
            // belum memilih atau pilihannya sudah dihapus, jatuh ke tujuan
            // tertua. NULL kalau belum ada tujuan aktif sama sekali.
            'primary_goal' => $goalSummaries->firstWhere('id', $user->primary_goal_id)
                ?? $goalSummaries->first(),
            // Pertumbuhan kekayaan dari RIWAYAT TRANSAKSI, bukan dari
            // setoran per tujuan. Alokasi target hanya menandai saldo dan
            // tidak punya riwayat waktu; yang benar-benar bergerak dari
            // hari ke hari adalah saldo rekeningnya sendiri.
            'asset_growth_series' => [
                'monthly' => $this->assetGrowthMonthly($user),
                'daily' => $this->assetGrowthDaily($user),
            ],
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
        // Dana yang DITANDAI untuk tujuan ini di sebuah rekening. Dulu ini
        // dijumlahkan dari setoran (initial_amount + SUM(contributions));
        // sejak pencatatan setoran dipensiunkan, satu-satunya sumbernya
        // adalah alokasi — lihat migrasi add_allocation_to_financial_goals.
        $currentAmount = round((float) $goal->allocated_amount, 2);
        $targetAmount = round((float) $goal->target_amount, 2);
        $dailySavingsTarget = round((float) $goal->daily_savings_target, 2);
        $projectedAmount = round(min($targetAmount, $currentAmount + $dailySavingsTarget), 2);
        $suggested = $this->allocations->forGoal($goal, $accountRiskProfile);

        return [
            'id' => $goal->id,
            'name' => $goal->name,
            // Setoran bulanan SESUAI RENCANA — dari snapshot saat tujuan
            // dibuat, bukan dihitung ulang terhadap sisa waktu hari ini.
            // Menghitung ulang adalah FR-36 (rekalkulasi saat realisasi
            // meleset), fitur tersendiri dengan tawaran pilihan ke pengguna.
            // NULL untuk tujuan tanpa tenggat: tanpa jangka waktu, setoran
            // bulanan tidak punya arti dan snapshotnya memang tidak dibuat.
            'planned_monthly_contribution' => $goal->latestCalculation
                ? round((float) $goal->latestCalculation->monthly_contribution_required, 2)
                : null,
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
            'suggested_allocation' => $suggested,
            // Perbandingan saran vs alokasi NYATA yang dicatat pengguna di
            // halaman Dompet (financial_goals.asset_allocation). Dihitung di
            // sini, bukan di React: persentase, selisih, dan ambang batas
            // penyimpangan adalah aturan produk, dan menaruhnya di frontend
            // berarti ia harus ditulis ulang di setiap tempat yang
            // menampilkannya (CLAUDE.md §6.9).
            'allocation_comparison' => $this->allocationComparison($goal, $suggested),
            'asset_allocation' => $goal->asset_allocation ?? [],
        ];
    }

    /**
     * Menyandingkan alokasi yang disarankan dengan yang BENAR-BENAR dipegang
     * pengguna (FR-52..FR-56).
     *
     * Alokasi nyata dicatat pengguna di halaman Dompet sebagai NOMINAL RUPIAH
     * per instrumen, sementara saran berbentuk persentase — jadi keduanya
     * disamakan ke persentase lebih dulu supaya bisa dibandingkan.
     *
     * Penyebutnya adalah total yang dialokasikan, BUKAN current_amount. Dana
     * yang belum ditempatkan ke instrumen mana pun tidak boleh mengecilkan
     * seluruh persentase — pengguna yang baru mencatat separuh dananya akan
     * melihat semua angkanya timpang tanpa tahu sebabnya.
     *
     * @param  array<int, array{instrument: string, percentage: float}>  $suggested
     * @return array{has_actual: bool, total_actual: float, rows: array<int, array>}
     */
    private function allocationComparison(FinancialGoal $goal, array $suggested): array
    {
        $nyata = $this->actualAllocationAmounts($goal);
        $totalNyata = array_sum($nyata);

        $saranPersen = [];
        foreach ($suggested as $baris) {
            $saranPersen[$baris['instrument']] = (float) $baris['percentage'];
        }

        // Gabungan keduanya: instrumen yang disarankan, yang dipegang, atau
        // dua-duanya. Instrumen yang dipegang tetapi tidak disarankan — kas,
        // atau instrumen yang ditambahkan sendiri — justru yang paling perlu
        // terlihat, jadi tidak boleh terbuang hanya karena tak ada di saran.
        $semua = array_unique([...array_keys($saranPersen), ...array_keys($nyata)]);

        $rows = [];
        foreach ($semua as $instrumen) {
            $nominal = round((float) ($nyata[$instrumen] ?? 0), 2);
            $persenNyata = $totalNyata > 0 ? round($nominal / $totalNyata * 100, 1) : 0.0;
            $persenSaran = round($saranPersen[$instrumen] ?? 0, 1);

            $rows[] = [
                'instrument' => $instrumen,
                'suggested_percentage' => $persenSaran,
                'actual_percentage' => $persenNyata,
                'actual_amount' => $nominal,
                'delta' => round($persenNyata - $persenSaran, 1),
                // Ambang ±10 poin persen (FR-54). Selisih kecil wajar terjadi
                // dan menandainya hanya melatih pengguna mengabaikan peringatan.
                'off_track' => $totalNyata > 0 && abs($persenNyata - $persenSaran) > 10,
            ];
        }

        usort($rows, fn ($a, $b) => $b['actual_amount'] <=> $a['actual_amount']
            ?: $b['suggested_percentage'] <=> $a['suggested_percentage']);

        return [
            'has_actual' => $totalNyata > 0,
            'total_actual' => round($totalNyata, 2),
            'rows' => $rows,
        ];
    }

    /**
     * Nominal per instrumen dari kolom JSON asset_allocation.
     *
     * Kuncinya dinormalkan ke label yang sama dengan InvestmentAllocationService
     * — tanpa itu "obligasi" dan "Obligasi/SBN" terhitung dua instrumen berbeda
     * dan perbandingannya jadi tidak berarti.
     *
     * @return array<string, float>
     */
    private function actualAllocationAmounts(FinancialGoal $goal): array
    {
        $tersimpan = $goal->asset_allocation ?? [];

        $peta = [
            'tabungan' => 'Tabungan/Kas',
            'saham' => 'Saham',
            'obligasi' => 'Obligasi/SBN',
            'deposito' => 'Deposito',
            'emas' => 'Emas',
        ];

        $hasil = [];
        foreach ($peta as $kunci => $label) {
            $nominal = (float) ($tersimpan[$kunci] ?? 0);
            if ($nominal > 0) {
                $hasil[$label] = $nominal;
            }
        }

        // Instrumen yang ditambahkan pengguna sendiri. Nama yang sama
        // dijumlahkan, bukan saling menimpa.
        foreach ($tersimpan['custom'] ?? [] as $item) {
            $nama = trim((string) ($item['name'] ?? ''));
            $nominal = (float) ($item['amount'] ?? 0);

            if ($nama !== '' && $nominal > 0) {
                $hasil[$nama] = ($hasil[$nama] ?? 0) + $nominal;
            }
        }

        return $hasil;
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
     * TIDAK lagi memuat setoran. Pencatatan setoran harian dipensiunkan dan
     * digantikan alokasi dari rekening — kalender kini murni untuk catatan
     * dan pengingat, dan uang tidak lagi dicatat lewat mengklik tanggal.
     *
     * @return array{
     *     month: string,
     *     label: string,
     *     notes: array<int, array{id: int, date: string, body: string}>,
     *     reminders: array<int, array>
     * }
     */
    public function calendarForMonth(User $user, Carbon $month): array
    {
        $start = $month->copy()->startOfMonth();
        $end = $month->copy()->endOfMonth();

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

    /**
     * Pertumbuhan kekayaan 12 bulan terakhir, dari RIWAYAT TRANSAKSI.
     *
     * Dulu deret ini dibangun dari setoran per tujuan. Sejak pencatatan
     * setoran dipensiunkan, sumbernya berpindah ke transaksi — dan itu
     * sebenarnya yang lebih benar: alokasi target hanya menandai saldo dan
     * tidak punya riwayat waktu, sedangkan saldo rekeningnya sendiri memang
     * bergerak dari hari ke hari.
     *
     * Titik pertama memuat SELURUH saldo awal rekening ditambah transaksi
     * sebelum jendela 12 bulan. Tanpa itu, grafiknya seolah dimulai dari nol
     * dan memperlihatkan lonjakan yang tidak pernah terjadi.
     *
     * @return array<int, array{period: string, cumulative_amount: float}>
     */
    private function assetGrowthMonthly(User $user): array
    {
        $awal = Carbon::now(config('app.timezone'))->startOfMonth()->subMonths(self::ASSET_GROWTH_MONTHS - 1);

        $kumulatif = $this->openingPosition($user, $awal);
        $perBulan = $this->netChangeGroupedBy($user, $awal, 'Y-m');

        $deret = [];
        $bulan = $awal->copy();

        for ($i = 0; $i < self::ASSET_GROWTH_MONTHS; $i++) {
            $kunci = $bulan->format('Y-m');
            $kumulatif = round($kumulatif + ($perBulan[$kunci] ?? 0), 2);
            $deret[] = ['period' => $kunci, 'cumulative_amount' => $kumulatif];
            $bulan->addMonthNoOverflow();
        }

        return $deret;
    }

    /**
     * Sama seperti versi bulanan, tetapi 30 hari terakhir.
     *
     * @return array<int, array{period: string, cumulative_amount: float}>
     */
    private function assetGrowthDaily(User $user): array
    {
        $awal = Carbon::now(config('app.timezone'))->startOfDay()->subDays(self::ASSET_GROWTH_DAYS - 1);

        $kumulatif = $this->openingPosition($user, $awal);
        $perHari = $this->netChangeGroupedBy($user, $awal, 'Y-m-d');

        $deret = [];
        $hari = $awal->copy();

        for ($i = 0; $i < self::ASSET_GROWTH_DAYS; $i++) {
            $kunci = $hari->toDateString();
            $kumulatif = round($kumulatif + ($perHari[$kunci] ?? 0), 2);
            $deret[] = ['period' => $kunci, 'cumulative_amount' => $kumulatif];
            $hari->addDay();
        }

        return $deret;
    }

    /** Total kekayaan tepat sebelum `$sejak` — saldo awal + transaksi lampau. */
    private function openingPosition(User $user, Carbon $sejak): float
    {
        $saldoAwal = (float) $user->accounts()->sum('opening_balance');

        $lampau = $user->transactions()
            ->where('occurred_on', '<', $sejak->toDateString())
            ->get(['type', 'amount']);

        return round($saldoAwal + $this->netChange($lampau), 2);
    }

    /**
     * Perubahan bersih kekayaan per periode sejak `$sejak`.
     *
     * TRANSFER diabaikan sepenuhnya: ia memindahkan uang antar rekening
     * milik pengguna yang sama, jadi tidak mengubah total kekayaannya sama
     * sekali. Menghitungnya akan membuat setiap pemindahan dana tampak
     * sebagai lonjakan lalu penurunan pada grafik yang sama.
     *
     * @return array<string, float>
     */
    private function netChangeGroupedBy(User $user, Carbon $sejak, string $format): array
    {
        return $user->transactions()
            ->where('occurred_on', '>=', $sejak->toDateString())
            ->get(['type', 'amount', 'occurred_on'])
            ->groupBy(fn ($t) => $t->occurred_on->format($format))
            ->map(fn (Collection $baris) => $this->netChange($baris))
            ->all();
    }

    /** @param  Collection<int, \App\Models\Transaction>  $transaksi */
    private function netChange(Collection $transaksi): float
    {
        return round($transaksi->sum(function ($t) {
            if ($t->type === TransactionType::Transfer) {
                return 0;
            }

            return $t->type->menambahSaldo() ? (float) $t->amount : -(float) $t->amount;
        }), 2);
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
