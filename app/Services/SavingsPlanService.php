<?php

namespace App\Services;

use App\Enums\GoalStatus;
use App\Models\Budget;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Support\Carbon;

/**
 * Rencana menabung (PRD FR-74..FR-78).
 *
 * Menjawab satu pertanyaan: **sisa uang saya cukup untuk target yang mana?**
 *
 * Itu pertanyaan yang BERBEDA dari yang dijawab GoalCalculatorService
 * ("berapa per bulan supaya target ini tercapai?"), dan keduanya dipakai
 * bersama di sini — kebutuhan tiap target dihitung oleh kalkulator anuitas
 * yang sudah ada, lalu dibagi oleh service ini menurut kemampuan yang nyata.
 *
 * Prototipe Arus memakai pembagian biasa untuk kebutuhan bulanan
 * `(target − terkumpul) / sisa_bulan`, tanpa imbal hasil maupun inflasi.
 * Itu TIDAK dipakai di sini: rumus anuitas dengan inflasi adalah inti produk
 * ini, sudah diverifikasi terhadap `docs/fixtures/calculator-cases.json`, dan
 * diimplementasikan sama persis di PHP maupun JavaScript (CLAUDE.md §6.6).
 * Menggantinya dengan pembagian biasa akan membuat target jangka panjang
 * tampak jauh lebih mahal daripada seharusnya.
 *
 * **Kekurangan dana ditampilkan, bukan ditutupi.** Ketika kemampuan menabung
 * tidak cukup, service ini melaporkan selisihnya apa adanya dan tidak
 * mengarang asumsi imbal hasil yang lebih tinggi supaya angkanya pas.
 */
class SavingsPlanService
{
    public function __construct(
        private AccountBalanceService $saldo,
        private GoalCalculatorService $kalkulator,
    ) {}

    /**
     * @return array{
     *     budget: array{income: float, expenses: float, reserve: float, debt_principal: float, capacity: float},
     *     rows: array<int, array>,
     *     total_need: float,
     *     total_allocation: float,
     *     total_shortfall: float,
     *     unallocated: float
     * }
     */
    public function forUser(User $user): array
    {
        $anggaran = $this->budgetOf($user);
        $cicilan = $this->activeMonthlyPrincipal($user);

        $kemampuan = round(max(
            0,
            (float) $anggaran->planned_income
                - (float) $anggaran->planned_expenses
                - (float) $anggaran->monthly_reserve
                - $cicilan,
        ), 2);

        $baris = $this->mapping($user, $kemampuan);

        return [
            'budget' => [
                'income' => (float) $anggaran->planned_income,
                'expenses' => (float) $anggaran->planned_expenses,
                'reserve' => (float) $anggaran->monthly_reserve,
                'debt_principal' => $cicilan,
                'capacity' => $kemampuan,
            ],
            'rows' => $baris,
            'total_need' => round(array_sum(array_column($baris, 'need')), 2),
            'total_allocation' => round(array_sum(array_column($baris, 'allocation')), 2),
            'total_shortfall' => round(array_sum(array_column($baris, 'shortfall')), 2),
            'unallocated' => round(
                $kemampuan - array_sum(array_column($baris, 'allocation')),
                2,
            ),
        ];
    }

    /** Anggaran pengguna; baris kosong bila belum pernah diisi. */
    public function budgetOf(User $user): Budget
    {
        return $user->budget ?? new Budget([
            'planned_income' => 0,
            'planned_expenses' => 0,
            'monthly_reserve' => 0,
        ]);
    }

    /**
     * Cicilan pokok yang masih harus dibayar tiap bulan.
     *
     * Dibatasi sisa pokoknya (`min`): utang bersisa 300 ribu dengan rencana
     * cicilan 1 juta hanya menyerap 300 ribu bulan ini. Tanpa pembatasan itu,
     * kemampuan menabung tampak lebih kecil daripada yang sebenarnya, tepat
     * pada bulan terakhir ketika pengguna justru mulai punya kelonggaran.
     */
    private function activeMonthlyPrincipal(User $user): float
    {
        $sisa = $this->saldo->debtRemaining($user);

        return round(
            $user->debts()->get()->sum(
                fn ($utang) => max(0, min(
                    (float) $utang->monthly_principal,
                    $sisa[$utang->id] ?? 0,
                )),
            ),
            2,
        );
    }

    /**
     * Membagi kemampuan menabung ke target, prioritas tinggi lebih dulu, lalu
     * tenggat terdekat.
     *
     * Urutannya penting justru ketika dananya TIDAK cukup — dan itu keadaan
     * yang paling umum. Membagi rata membuat semua target meleset sedikit;
     * membagi berurutan membuat yang terpenting tetap tercapai tepat waktu.
     *
     * @return array<int, array>
     */
    private function mapping(User $user, float $kemampuan): array
    {
        $tersisa = $kemampuan;

        // Target mana yang sudah disisihkan BULAN INI, beserta totalnya.
        // Satu kueri untuk semuanya, bukan satu per baris rencana.
        $bulanIni = Carbon::now(config('app.timezone'))->startOfMonth();
        $sudahBulanIni = $user->activities()
            ->where('type', 'goal_set_aside')
            ->whereNotNull('financial_goal_id')
            ->where('created_at', '>=', $bulanIni)
            ->selectRaw('financial_goal_id, SUM(amount) AS total')
            ->groupBy('financial_goal_id')
            ->pluck('total', 'financial_goal_id');

        $tujuan = $user->goals()
            ->where('status', GoalStatus::Active->value)
            ->get()
            ->sortBy([
                fn (FinancialGoal $a, FinancialGoal $b) => $a->priority->rank() <=> $b->priority->rank(),
                // Tanpa tenggat diletakkan paling belakang di dalam kelompok
                // prioritas yang sama: target yang tidak dikejar tanggal tidak
                // boleh menggeser target yang dikejar tanggal.
                fn (FinancialGoal $a, FinancialGoal $b) => ($a->target_date?->toDateString() ?? '9999-12-31')
                    <=> ($b->target_date?->toDateString() ?? '9999-12-31'),
            ]);

        $baris = [];

        foreach ($tujuan as $goal) {
            $kebutuhan = $this->monthlyNeed($goal);
            $alokasi = round(min($kebutuhan, max(0, $tersisa)), 2);
            $tersisa = round($tersisa - $alokasi, 2);

            $baris[] = [
                'goal_id' => $goal->id,
                'name' => $goal->name,
                'priority' => $goal->priority->value,
                'priority_label' => $goal->priority->label(),
                'target_amount' => (float) $goal->target_amount,
                'allocated_amount' => (float) $goal->allocated_amount,
                'target_date' => $goal->target_date?->toDateString(),
                'months_left' => $this->monthsLeft($goal),
                'need' => $kebutuhan,
                'allocation' => $alokasi,
                'shortfall' => round($kebutuhan - $alokasi, 2),
                // Setara harian, supaya angkanya terasa sebagai kebiasaan
                // sehari-hari dan bukan tagihan bulanan yang menakutkan.
                'daily_equivalent' => round($alokasi / 30, 2),
                'achieved' => $kebutuhan <= 0,

                // Menutup lingkaran umpan balik: tanpa ini halaman rencana
                // mengulang perintah yang sama persis tiap bulan, tidak peduli
                // pengguna sudah mengikutinya atau belum.
                'set_aside_this_month' => round((float) ($sudahBulanIni[$goal->id] ?? 0), 2),

                // Target tanpa rekening tidak bisa memakai tombol "Sudah saya
                // sisihkan" — tidak ada saldo yang bisa ditandai. Dikirim
                // supaya tombolnya bisa menjelaskan alasannya, bukan sekadar
                // menolak saat ditekan.
                'can_set_aside' => $goal->account_id !== null && $kebutuhan > 0,
            ];
        }

        return $baris;
    }

    /**
     * Kebutuhan bulanan sebuah target — memakai rumus anuitas yang sudah ada,
     * lengkap dengan imbal hasil dan inflasi.
     *
     * Target TANPA tenggat tidak punya kebutuhan bulanan yang bisa dihitung:
     * tanpa tanggal, setoran berapa pun secara matematis "cukup". Ia
     * dikembalikan nol dan tidak menyerap kemampuan menabung, supaya tidak
     * menggeser target yang benar-benar dikejar tanggal.
     */
    private function monthlyNeed(FinancialGoal $goal): float
    {
        $bulan = $this->monthsLeft($goal);

        if ($bulan === null) {
            return 0.0;
        }

        $terkumpul = (float) $goal->allocated_amount;

        if ($terkumpul >= (float) $goal->target_amount) {
            return 0.0;
        }

        return (float) $this->kalkulator->calculateMonthlyContribution(
            targetAmount: (float) $goal->target_amount,
            currentAmount: $terkumpul,
            months: $bulan,
            annualReturnRate: (float) $goal->estimated_return_rate,
            annualInflationRate: (float) $goal->estimated_inflation_rate,
        )['monthly_contribution_required'];
    }

    /**
     * Sisa bulan sampai tenggat, minimal 1.
     *
     * Target yang tenggatnya SUDAH LEWAT dikembalikan 1, bukan 0 — seluruh
     * kekurangannya jatuh ke bulan ini. Itu memang menghasilkan angka besar,
     * dan memang begitu keadaannya; menyebarnya ke bulan-bulan berikutnya
     * berarti diam-diam memundurkan tenggat yang pengguna tetapkan sendiri.
     */
    private function monthsLeft(FinancialGoal $goal): ?int
    {
        if ($goal->target_date === null) {
            return null;
        }

        $hariIni = Carbon::now(config('app.timezone'))->startOfDay();
        $tenggat = $goal->target_date->copy()->startOfDay();

        if ($tenggat <= $hariIni) {
            return 1;
        }

        $bulan = ($tenggat->year - $hariIni->year) * 12
            + ($tenggat->month - $hariIni->month)
            + ($tenggat->day >= $hariIni->day ? 0 : -1);

        return max(1, $bulan);
    }
}
