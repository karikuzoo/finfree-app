<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreDebtRequest;
use App\Models\Debt;
use App\Services\AccountBalanceService;
use App\Services\LedgerGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Utang & cicilan (PRD FR-70).
 *
 * Pembayaran pokok TIDAK dicatat di sini — ia transaksi berjenis `payment`
 * yang dikirim ke TransactionController. Tombol "Catat pembayaran" di kartu
 * utang hanyalah pintasan ke endpoint yang sama dengan bidang utangnya sudah
 * terisi. Alasannya: pembayaran mengurangi saldo rekening sekaligus sisa
 * utang, dan seluruh invarian untuk itu — saldo tidak boleh minus,
 * pembayaran tidak boleh melebihi sisa pokok — sudah dijaga di sana.
 * Menyalinnya ke sini berarti dua tempat yang bisa berbeda aturan.
 */
class DebtController extends Controller
{
    public function __construct(
        private AccountBalanceService $saldo,
        private LedgerGuard $penjaga,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $sisa = $this->saldo->debtRemaining($user);

        $utang = $user->debts()
            ->orderBy('due_on')
            ->orderBy('created_at')
            ->get()
            ->map(function (Debt $d) use ($sisa) {
                $pokok = (float) $d->principal;
                $tersisa = $sisa[$d->id] ?? $pokok;
                $terbayar = round($pokok - $tersisa, 2);

                return [
                    'id' => $d->id,
                    'name' => $d->name,
                    'principal' => $pokok,
                    'remaining' => $tersisa,
                    'paid' => $terbayar,
                    'progress' => $pokok > 0 ? round($terbayar / $pokok * 100, 1) : 0.0,
                    'monthly_principal' => (float) $d->monthly_principal,
                    'due_on' => $d->due_on?->toDateString(),
                    // Lunas ditentukan dari RIWAYAT, bukan dari tombol yang
                    // ditekan pengguna — satu sumber kebenaran, dan otomatis
                    // kembali aktif bila pembayarannya dihapus.
                    'settled' => $tersisa <= 0,
                ];
            });

        return Inertia::render('Debt/Index', [
            'debts' => $utang,
            'totalRemaining' => round($utang->sum('remaining'), 2),
            'monthlyPrincipal' => round(
                $utang->where('settled', false)->sum('monthly_principal'),
                2,
            ),
            'accounts' => $user->accounts()->orderBy('name')->get(['id', 'name']),
        ]);
    }

    public function store(StoreDebtRequest $request): RedirectResponse
    {
        $request->user()->debts()->create($request->validated());

        return back();
    }

    /**
     * Menurunkan sisa pokok di bawah jumlah yang sudah terbayar akan membuat
     * sisa utang negatif — angka yang tidak punya arti. Ditangkap LedgerGuard
     * setelah penulisan, lalu dibatalkan.
     */
    public function update(StoreDebtRequest $request, Debt $debt): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $debt) {
            $debt->update($request->validated());
            $this->penjaga->assertConsistent($user, 'principal');
        });

        return back();
    }

    /**
     * Utang yang punya riwayat pembayaran tidak boleh dihapus.
     *
     * Ditolak dengan pesan yang bisa dibaca, bukan dibiarkan jatuh ke
     * pelanggaran foreign key. Menghapusnya secara berantai juga bukan
     * pilihan: pembayarannya adalah uang yang benar-benar keluar dari
     * rekening, dan melenyapkannya akan membuat saldo melonjak naik
     * seolah uang itu tidak pernah dibayarkan.
     */
    public function destroy(Request $request, Debt $debt): RedirectResponse
    {
        abort_unless($debt->user_id === $request->user()->id, 403);

        if ($debt->payments()->exists()) {
            throw ValidationException::withMessages([
                'debt' => "Utang {$debt->name} sudah punya riwayat pembayaran. "
                    .'Hapus pembayarannya lebih dulu bila catatan ini memang keliru.',
            ]);
        }

        $debt->delete();

        return back();
    }
}
