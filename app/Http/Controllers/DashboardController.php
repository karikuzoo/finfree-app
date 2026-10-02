<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\DashboardSummaryService;
use App\Support\MonthQuery;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Dashboard menggabungkan DUA lapisan yang sengaja dipisah di backend:
 *
 * - `summary` dari DashboardSummaryService — soal TUJUAN: progres, alokasi,
 *   saran instrumen.
 * - `wealth` dari AccountBalanceService — soal UANG: kekayaan bersih, arus
 *   kas bulan ini, komposisi aset.
 *
 * Keduanya tidak dilebur jadi satu service. Halaman Rekening, Investasi, dan
 * Rencana menabung masing-masing hanya butuh salah satunya; menyatukannya
 * berarti tiap halaman itu ikut membayar perhitungan yang tidak dipakainya.
 */
class DashboardController extends Controller
{
    private const RECENT_TRANSACTIONS = 5;

    public function index(
        Request $request,
        DashboardSummaryService $summary,
        AccountBalanceService $saldo,
    ): Response {
        // Bulan yang sedang dilihat (`?bulan=YYYY-MM`). Nilai yang tidak sah
        // jatuh ke bulan berjalan, BUKAN ditolak: sampai 2 Okt 2026 di sini
        // ada `$request->validate()`, yang di halaman GET mengembalikan ke
        // "halaman sebelumnya" — bisa alamat yang sama — lalu berputar.
        // MonthQuery juga menangani jebakan tanggal 31 ("31 Juni" meluber
        // ke Juli); lihat docblock-nya.
        $bulan = MonthQuery::from($request);

        return Inertia::render('Dashboard', [
            'summary' => $summary->forUser($request->user()),

            // Nilai biasa, BUKAN closure. Di Inertia, prop berupa closure
            // punya aturan evaluasi tersendiri yang mudah keliru dipakai —
            // dan kalender ini murah dihitung, jadi tidak ada yang dihemat
            // dengan menundanya.
            'calendar' => $summary->calendarForMonth($request->user(), $bulan),

            // Pengingat hari ini ditampilkan terpisah dari kalender: kalender
            // mengikuti bulan yang sedang dilihat, sedangkan panel ini selalu
            // soal hari ini — meski pengguna sedang menengok bulan lalu.
            'todayReminders' => $summary->remindersForToday($request->user()),

            'wealth' => $this->wealth($request, $saldo, $bulan),
        ]);
    }

    /**
     * Angka kekayaan untuk kartu atas dashboard.
     *
     * Arus kasnya mengikuti BULAN YANG SEDANG DILIHAT, sama seperti kalender —
     * bukan selalu bulan berjalan. Pengguna yang menggeser kalender ke bulan
     * lalu mengharapkan angka di atasnya ikut bergeser; membiarkannya tetap di
     * bulan ini membuat dua bagian layar bicara tentang periode berbeda tanpa
     * ada yang menjelaskan.
     *
     * @return array<string, mixed>
     */
    private function wealth(Request $request, AccountBalanceService $saldo, Carbon $bulan): array
    {
        $user = $request->user();

        return [
            'net_worth' => $saldo->netWorth($user),
            'total_assets' => $saldo->totalAssets($user),
            'total_debt' => round(array_sum($saldo->debtRemaining($user)), 2),
            'cash_flow' => $saldo->monthlyCashFlow($user, $bulan->format('Y-m')),
            'composition' => $saldo->assetComposition($user),
            'has_accounts' => $user->accounts()->exists(),

            // Beberapa transaksi terakhir, bukan seluruh riwayat — daftar
            // penuhnya punya halamannya sendiri.
            'recent_transactions' => $user->transactions()
                ->with('account:id,name')
                ->orderByDesc('occurred_on')
                ->orderByDesc('id')
                ->limit(self::RECENT_TRANSACTIONS)
                ->get()
                ->map(fn (Transaction $t) => [
                    'id' => $t->id,
                    'name' => $t->name,
                    'type_label' => $t->type->label(),
                    'category' => $t->category,
                    'account' => $t->account?->name,
                    'amount' => (float) $t->amount,
                    'increases_balance' => $t->type->menambahSaldo(),
                    'occurred_on' => $t->occurred_on->toDateString(),
                ]),
        ];
    }

}
