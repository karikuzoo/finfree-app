<?php

namespace App\Http\Controllers;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Http\Requests\StoreGoalRequest;
use App\Models\FinancialGoal;
use App\Services\DashboardSummaryService;
use App\Services\GoalCalculatorService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Pembuatan tujuan finansial — alur "Buat Tujuan Pertama".
 *
 * Lingkupnya sengaja hanya create. Ubah, hapus, dan daftar tujuan adalah
 * pekerjaan terpisah; halaman Goal/Index milik rekan tim yang menyusunnya.
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
            'typeLabels' => $this->typeLabels(),
        ]);
    }

    public function create(Request $request): Response
    {
        return Inertia::render('Goal/Create', [
            // Daftar jenis tujuan berasal dari enum, bukan ditulis ulang di
            // frontend — supaya menambah jenis baru cukup di satu tempat.
            'goalTypes' => collect(GoalType::cases())
                ->map(fn (GoalType $type) => [
                    'value' => $type->value,
                    'label' => $type->label(),
                    // Dana darurat tidak bertenggat; frontend memakai penanda
                    // ini untuk menyembunyikan kolom tanggal, dan aturan yang
                    // sama ditegakkan lagi di StoreGoalRequest.
                    'requiresDate' => $type !== GoalType::Emergency,
                ])
                ->values(),

            'isFirstGoal' => $request->user()->goals()->count() === 0,
        ]);
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
                'type' => $data['type'],
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
     * Peta nilai enum → label bahasa Indonesia, mis. `house` → "Beli Rumah".
     *
     * Dikirim dari server supaya menambah jenis tujuan baru cukup dilakukan di
     * App\Enums\GoalType — tidak perlu menyalin daftarnya lagi ke frontend.
     *
     * @return array<string, string>
     */
    private function typeLabels(): array
    {
        return collect(GoalType::cases())
            ->mapWithKeys(fn (GoalType $type) => [$type->value => $type->label()])
            ->all();
    }

    /**
     * Jumlah bulan dari hari ini sampai tanggal target.
     *
     * NULL untuk tujuan tanpa tenggat (dana darurat) — tanpa jangka waktu,
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
}
