<?php

namespace App\Http\Controllers;

use App\Enums\AccountKind;
use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekening & aset (PRD FR-63).
 *
 * Saldo tidak pernah dikirim per rekening lewat kueri terpisah —
 * AccountBalanceService menghitung seluruhnya sekali, lalu hasilnya
 * ditempelkan di sini. Halaman ini menampilkan semua rekening pengguna
 * sekaligus, jadi ia titik N+1 yang paling jelas kalau dikerjakan per baris.
 */
class AccountController extends Controller
{
    public function __construct(private AccountBalanceService $saldo) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $saldo = $this->saldo->forUser($user);

        $rekening = $user->accounts()
            ->orderBy('created_at')
            ->get()
            ->map(fn (Account $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'kind' => $r->kind->value,
                'kind_label' => $r->kind->label(),
                'institution' => $r->institution,
                'opening_balance' => (float) $r->opening_balance,
                'balance' => $saldo[$r->id] ?? 0.0,
                // Nilainya bergerak sendiri mengikuti pasar, jadi kartunya
                // menawarkan "Perbarui nilai" alih-alih "Pindahkan dana".
                'needs_valuation' => $r->kind->perluPenilaian(),
            ]);

        return Inertia::render('Account/Index', [
            'accounts' => $rekening,
            'totalAssets' => $this->saldo->totalAssets($user),
            'composition' => $this->saldo->assetComposition($user),
            'kinds' => collect(AccountKind::cases())->map(fn (AccountKind $k) => [
                'value' => $k->value,
                'label' => $k->label(),
                'liquid' => $k->likuid(),
            ]),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $request->user()->accounts()->create($request->validated());

        return back();
    }

    public function update(StoreAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->validated());

        return back();
    }

    /**
     * Rekening yang masih dipakai TIDAK boleh dihapus.
     *
     * Ditolak di sini dengan pesan yang bisa dibaca, bukan dibiarkan jatuh ke
     * pelanggaran foreign key yang muncul sebagai halaman 500. Menghapusnya
     * secara berantai juga bukan pilihan: riwayat transaksi adalah catatan
     * keuangan pengguna, dan melenyapkannya diam-diam karena ia menghapus
     * satu rekening adalah kehilangan data yang tidak bisa dibatalkan.
     */
    public function destroy(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $dipakai = $account->transactions()->exists()
            || $account->incomingTransfers()->exists();

        if ($dipakai) {
            throw ValidationException::withMessages([
                'account' => "Rekening {$account->name} masih punya riwayat transaksi. "
                    .'Hapus atau pindahkan transaksinya terlebih dahulu.',
            ]);
        }

        $account->delete();

        return back();
    }
}
