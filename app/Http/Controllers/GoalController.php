<?php

namespace App\Http\Controllers;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Http\Requests\StoreGoalRequest;
use App\Http\Requests\UpdateGoalRequest;
use App\Models\FinancialGoal;
use App\Services\DashboardSummaryService;
use App\Services\GoalCalculatorService;
use App\Services\GoalRecalculationService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Tujuan finansial: daftar, buat, ubah, dan hapus.
 *
 * Controller ini TIDAK memuat satu baris pun rumus — seluruh perhitungan
 * didelegasikan ke GoalCalculatorService, sama seperti kalkulator publik
 * (CLAUDE.md §6.6). Satu-satunya "hitungan" di sini adalah mengubah tanggal
 * target menjadi jumlah bulan, karena itu penerjemahan input, bukan rumus
 * finansial.
 */
class GoalController extends Controller
{
    /**
     * Daftar tujuan pengguna.
     *
     * Ringkasan progresnya diambil dari DashboardSummaryService, BUKAN dihitung
     * ulang di sini. Progres, status on-track, dan sisa hari punya definisi yang
     * halus (lihat docblock summarizeGoal); menghitungnya di dua tempat
     * dipastikan akan menghasilkan angka berbeda suatu hari, dan pengguna
     * melihat dua nilai berbeda untuk tujuan yang sama.
     *
     * Konsekuensi yang diterima: service itu juga menghitung grafik aset,
     * kalender, dan aktivitas terbaru yang tidak dipakai halaman ini. Volumenya
     * kecil — setoran satu pengguna dalam setahun — jadi konsistensi lebih
     * berharga daripada beberapa kueri yang terbuang.
     */
    public function index(Request $request, DashboardSummaryService $summary): Response
    {
        $ringkasan = $summary->forUser($request->user());

        return Inertia::render('Goal/Index', [
            // Hanya tujuan berstatus aktif. Belum ada antarmuka untuk menandai
            // tujuan tercapai/diarsipkan, jadi untuk sekarang seluruh tujuan
            // pengguna pasti aktif.
            'goals' => $ringkasan['goals'],
            'totalTarget' => $ringkasan['total_target'],
            'totalAssets' => $ringkasan['total_assets'],
            'overallProgress' => $ringkasan['overall_progress_percentage'],
            // Dikirim terpisah supaya kartu bisa menandai yang utama
            // berdasar ID, bukan menebak dari urutan. Urutan daftar
            // tetap menurut created_at, jadi tujuan utama tidak selalu
            // berada di posisi pertama.
            'primaryGoalId' => $ringkasan['primary_goal']['id'] ?? null,
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Goal/Create', [
            'isFirstGoal' => $request->user()->goals()->count() === 0,
            'prefill' => $this->prefillFromCalculator($request),
        ]);
    }

    /**
     * Isian awal dari tombol "Jadikan Tujuan" di kalkulator publik — dibawa
     * lewat query string, supaya tetap utuh melewati halaman login bagi tamu
     * (Laravel mengembalikan ke URL yang dituju, lengkap dengan query-nya).
     *
     * Hanya MENGISI form; tidak menyimpan apa pun. Batasnya sama dengan
     * StoreGoalRequest, dan query yang tidak lolos diabaikan diam-diam —
     * tautan yang disunting tangan cukup membuka form kosong, bukan halaman
     * galat. Yang disimpan tetap melewati validasi penuh saat dikirim.
     */
    private function prefillFromCalculator(Request $request): ?array
    {
        if (! $request->has('target_amount')) {
            return null;
        }

        // `months`, imbal hasil, dan `name` opsional: "DP rumah" dari
        // kalkulator KPR hanya membawa nominal dan nama — kapan DP-nya harus
        // terkumpul tidak diketahui kalkulator itu, jadi dipilih pengguna.
        $validator = validator($request->query(), [
            'name' => ['nullable', 'string', 'max:100'],
            'target_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],
            'initial_amount' => ['nullable', 'numeric', 'min:0', 'lt:target_amount'],
            'months' => ['nullable', 'integer', 'min:1', 'max:720'],
            'estimated_return_rate' => ['nullable', 'numeric', 'min:0', 'max:30'],
            'estimated_inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ]);

        if ($validator->fails()) {
            return null;
        }

        $data = $validator->validated();

        return [
            'name' => $data['name'] ?? null,
            'target_amount' => (float) $data['target_amount'],
            'initial_amount' => (float) ($data['initial_amount'] ?? 0),
            'months' => isset($data['months']) ? (int) $data['months'] : null,
            'estimated_return_rate' => (float) ($data['estimated_return_rate'] ?? 0),
            'estimated_inflation_rate' => (float) ($data['estimated_inflation_rate'] ?? 0),
        ];
    }

    public function store(StoreGoalRequest $request, GoalCalculatorService $calculator): RedirectResponse
    {
        $data = $request->validated();

        $targetDate = $data['target_date'] ?? null;
        $initialAmount = (float) ($data['initial_amount'] ?? 0);
        $inflationRate = (float) ($data['estimated_inflation_rate'] ?? 0);

        // Tujuan dan snapshot perhitungannya ditulis dalam satu transaksi.
        // Bila perhitungan gagal, tujuannya pun tidak jadi tersimpan — lebih
        // baik daripada meninggalkan tujuan tanpa angka yang menjelaskannya.
        $goal = DB::transaction(function () use ($request, $data, $targetDate, $initialAmount, $inflationRate, $calculator) {
            $goal = $request->user()->goals()->create([
                // Jenis tujuan tidak lagi dipilih pengguna: satu nama bebas
                // lebih fleksibel daripada enam kategori tetap, dan tidak ada
                // logika yang bergantung padanya — alokasi instrumen dihitung
                // dari profil risiko & jangka waktu (InvestmentAllocationService),
                // bukan dari jenis. Kolomnya tetap ada di database dan diisi
                // 'custom' supaya skema serta data lama tidak perlu diubah.
                'type' => GoalType::Custom,
                'name' => $data['name'],
                'target_amount' => $data['target_amount'],
                'initial_amount' => $initialAmount,
                'target_date' => $targetDate,
                'estimated_return_rate' => $data['estimated_return_rate'],
                'estimated_inflation_rate' => $inflationRate,
                'status' => GoalStatus::Active,
            ]);

            $months = $this->monthsUntil($targetDate);

            if ($months !== null) {
                $hasil = $calculator->calculateMonthlyContribution(
                    targetAmount: (float) $data['target_amount'],
                    currentAmount: $initialAmount,
                    months: $months,
                    annualReturnRate: (float) $data['estimated_return_rate'],
                    annualInflationRate: $inflationRate,
                );

                $goal->calculations()->create([
                    'monthly_contribution_required' => $hasil['monthly_contribution_required'],
                    'total_contribution_projection' => $hasil['total_contribution_projection'],
                    'total_investment_growth_projection' => $hasil['total_investment_growth_projection'],
                    'calculation_snapshot' => $hasil,
                    'formula_version' => $hasil['formula_version'],
                ]);
            }

            $request->user()->activities()->create([
                'financial_goal_id' => $goal->id,
                'type' => 'goal_created',
                'goal_name' => $goal->name,
                'amount' => $goal->target_amount,
            ]);

            return $goal;
        });

        // Diarahkan ke daftar tujuan, bukan Dashboard: di sanalah tujuan yang
        // baru dibuat langsung terlihat beserta progresnya. Dashboard hanya
        // menampilkan tujuan utama, sehingga tujuan kedua dan seterusnya akan
        // tampak "hilang" bila pengguna dikembalikan ke sana.
        return redirect()
            ->route('goals.index')
            ->with('status', "Tujuan \"{$goal->name}\" berhasil dibuat.");
    }

    /**
     * Jumlah bulan dari hari ini sampai tanggal target.
     *
     * NULL untuk tujuan tanpa tenggat — tanpa jangka waktu,
     * setoran bulanan tidak punya arti dan tidak ada yang bisa dihitung.
     *
     * Dibulatkan ke atas supaya tenggatnya tidak terlewat: sisa 30 hari lebih
     * sedikit tetap dihitung 2 bulan, bukan 1. Minimal 1, karena tanggal
     * target sudah dipastikan setelah hari ini oleh StoreGoalRequest.
     */
    private function monthsUntil(?string $targetDate): ?int
    {
        if ($targetDate === null) {
            return null;
        }

        $months = Carbon::now()->startOfDay()->diffInMonths(
            Carbon::parse($targetDate)->startOfDay(),
        );

        return max(1, (int) ceil($months));
    }

    public function edit(Request $request, FinancialGoal $financialGoal): Response
    {
        abort_unless($financialGoal->user_id === $request->user()->id, 403);

        return Inertia::render('Goal/Edit', [
            'goal' => [
                'id' => $financialGoal->id,
                'name' => $financialGoal->name,
                'target_amount' => (float) $financialGoal->target_amount,
                'initial_amount' => (float) $financialGoal->initial_amount,
                'target_date' => $financialGoal->target_date?->toDateString(),
                'estimated_return_rate' => (float) $financialGoal->estimated_return_rate,
                'estimated_inflation_rate' => (float) $financialGoal->estimated_inflation_rate,
            ],

            // Dana yang sudah DITANDAI membatasi apa yang masuk akal diubah:
            // menurunkan target di bawah nominal ini membuat tujuan langsung
            // tercapai. Dikirim supaya form bisa memperingatkan, bukan
            // melarang — menurunkan target memang wajar bila rencananya
            // berubah.
            'currentAmount' => round((float) $financialGoal->allocated_amount, 2),
        ]);
    }

    /**
     * Menyimpan perubahan, lalu MENGHITUNG ULANG snapshot perhitungannya.
     *
     * Snapshot lama tidak ditimpa melainkan ditambah baris baru: `formula_version`
     * dan `calculation_snapshot` ada justru supaya angka yang pernah dijanjikan
     * ke pengguna tetap bisa dijelaskan di kemudian hari (PRD D-6). Yang dibaca
     * kartu tujuan adalah yang terbaru (relasi `latestCalculation`).
     */
    public function update(
        UpdateGoalRequest $request,
        FinancialGoal $financialGoal,
        GoalCalculatorService $calculator,
    ): RedirectResponse {
        $data = $request->validated();

        $targetDate = $data['target_date'] ?? null;
        $initialAmount = (float) ($data['initial_amount'] ?? 0);
        $inflationRate = (float) ($data['estimated_inflation_rate'] ?? 0);

        DB::transaction(function () use (
            $request, $financialGoal, $data, $targetDate, $initialAmount, $inflationRate, $calculator
        ) {
            $financialGoal->update([
                'name' => $data['name'],
                'target_amount' => $data['target_amount'],
                'initial_amount' => $initialAmount,
                'target_date' => $targetDate,
                'estimated_return_rate' => $data['estimated_return_rate'],
                'estimated_inflation_rate' => $inflationRate,
            ]);

            $months = $this->monthsUntil($targetDate);

            if ($months !== null) {
                $hasil = $calculator->calculateMonthlyContribution(
                    targetAmount: (float) $data['target_amount'],
                    currentAmount: $initialAmount,
                    months: $months,
                    annualReturnRate: (float) $data['estimated_return_rate'],
                    annualInflationRate: $inflationRate,
                );

                $financialGoal->calculations()->create([
                    'monthly_contribution_required' => $hasil['monthly_contribution_required'],
                    'total_contribution_projection' => $hasil['total_contribution_projection'],
                    'total_investment_growth_projection' => $hasil['total_investment_growth_projection'],
                    'calculation_snapshot' => $hasil,
                    'formula_version' => $hasil['formula_version'],
                ]);
            }

            $request->user()->activities()->create([
                'financial_goal_id' => $financialGoal->id,
                'type' => 'goal_updated',
                'goal_name' => $financialGoal->name,
                'amount' => $financialGoal->target_amount,
            ]);
        });

        return redirect()
            ->route('goals.index')
            ->with('status', "Tujuan \"{$financialGoal->name}\" berhasil diperbarui.");
    }
    /**
     * FR-36: menerima salah satu tawaran rekalkulasi. Angkanya dihitung ulang
     * di GoalRecalculationService — yang dikirim browser hanya NAMA pilihannya.
     */
    public function recalculate(
        Request $request,
        FinancialGoal $financialGoal,
        GoalRecalculationService $recalculations,
    ): RedirectResponse {
        abort_unless($financialGoal->user_id === $request->user()->id, 403);

        $data = $request->validate([
            'option' => ['required', Rule::in(GoalRecalculationService::OPTIONS)],
        ]);

        $recalculations->apply($request->user(), $financialGoal, $data['option']);

        $pesan = match ($data['option']) {
            'contribution' => 'Setoran bulanan dinaikkan',
            'date' => 'Tanggal target dimundurkan',
            'target' => 'Nominal target diturunkan',
        };

        return back()->with('status', "{$pesan} untuk \"{$financialGoal->name}\".");
    }

    /**
     * Menjadikan sebuah tujuan sebagai tujuan utama di Dashboard.
     *
     * Sebelum ini tujuan utama selalu yang TERTUA, dan pengguna tidak punya
     * cara mengubahnya — tujuan yang paling ia pedulikan bisa tertimbun di
     * bawah hanya karena dibuat belakangan.
     *
     * Disimpan di users.primary_goal_id, bukan sebagai penanda di tiap tujuan:
     * satu kolom membuat "dua tujuan utama sekaligus" mustahil terjadi.
     */
    public function setPrimary(Request $request, FinancialGoal $financialGoal): RedirectResponse
    {
        abort_unless($financialGoal->user_id === $request->user()->id, 403);

        // Ditulis langsung, bukan lewat update(): primary_goal_id sengaja tidak
        // masuk $fillable supaya tidak bisa ikut terbawa payload lain.
        $user = $request->user();
        $user->primary_goal_id = $financialGoal->id;
        $user->save();

        return back()->with(
            'status',
            "\"{$financialGoal->name}\" kini menjadi tujuan utama.",
        );
    }
    /**
     * Hapus tujuan beserta seluruh data terkait (setoran & kalkulasi).
     *
     * Hard delete — model ini tidak memakai SoftDeletes (lihat catatan di
     * migrasi create_financial_goals_table dan docblock FinancialGoal).
     * Relasi contributions dan calculations di-cascade oleh foreign key
     * di migrasi, tapi dihapus eksplisit di sini juga sebagai jaring
     * pengaman — kalau constraint FK berubah, data yatim piatu tidak
     * akan tertinggal.
     */
    public function destroy(Request $request, FinancialGoal $financialGoal): RedirectResponse
    {
        // Pastikan tujuan milik pengguna yang sedang login.
        if ($financialGoal->user_id !== $request->user()->id) {
            abort(403);
        }

        $nama = $financialGoal->name;

        DB::transaction(function () use ($request, $financialGoal, $nama) {
            $financialGoal->calculations()->delete();
            $financialGoal->contributions()->delete();
            $financialGoal->delete();

            $request->user()->activities()->create([
                'type' => 'goal_deleted',
                'goal_name' => $nama,
            ]);
        });

        return redirect()
            ->route('goals.index')
            ->with('status', "Tujuan \"{$nama}\" berhasil dihapus.");
    }
}
