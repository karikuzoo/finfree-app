<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreTransactionRequest;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use App\Services\LedgerGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Catatan transaksi (PRD FR-64..FR-69).
 *
 * Setiap penulisan dibungkus DB::transaction lalu diperiksa LedgerGuard.
 * Bila hasilnya melanggar invarian — saldo minus atau pembayaran melebihi
 * sisa utang — exception yang dilempar membatalkan seluruh transaksi
 * database, sehingga perubahannya tidak pernah benar-benar tersimpan.
 * Alasan memeriksa sesudah dan bukan sebelum ada di komentar LedgerGuard.
 */
class TransactionController extends Controller
{
    public function __construct(
        private AccountBalanceService $saldo,
        private LedgerGuard $penjaga,
    ) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $bulan = $request->string('bulan')->toString()
            ?: now(config('app.timezone'))->format('Y-m');

        // Disaring di basis data, bukan di PHP: riwayat transaksi tumbuh
        // tanpa batas atas dan memuat seluruhnya demi menampilkan satu bulan
        // akan melambat diam-diam seiring pemakaian.
        $transaksi = $user->transactions()
            ->with(['account:id,name', 'toAccount:id,name', 'debt:id,name'])
            ->inMonth($bulan)
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString()
            ->through(fn (Transaction $t) => [
                'id' => $t->id,
                'name' => $t->name,
                'type' => $t->type->value,
                'type_label' => $t->type->label(),
                'amount' => (float) $t->amount,
                'increases_balance' => $t->type->menambahSaldo(),
                'account' => $t->account?->name,
                'account_id' => $t->account_id,
                'to_account' => $t->toAccount?->name,
                'to_account_id' => $t->to_account_id,
                'debt' => $t->debt?->name,
                'debt_id' => $t->debt_id,
                'category' => $t->category,
                'occurred_on' => $t->occurred_on->toDateString(),
            ]);

        return Inertia::render('Transaction/Index', [
            'transactions' => $transaksi,
            'bulan' => $bulan,
            'cashFlow' => $this->saldo->monthlyCashFlow($user, $bulan),
            'accounts' => $user->accounts()->orderBy('name')->get(['id', 'name', 'kind']),
            'debts' => $user->debts()->orderBy('name')->get(['id', 'name']),
            'types' => collect(TransactionType::cases())->map(fn (TransactionType $t) => [
                'value' => $t->value,
                'label' => $t->label(),
            ]),
        ]);
    }

    public function store(StoreTransactionRequest $request): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request) {
            $user->transactions()->create($request->validated());
            $this->penjaga->assertConsistent($user);
        });

        return back();
    }

    public function update(StoreTransactionRequest $request, Transaction $transaction): RedirectResponse
    {
        $user = $request->user();

        DB::transaction(function () use ($user, $request, $transaction) {
            $transaction->update($request->validated());
            $this->penjaga->assertConsistent($user);
        });

        return back();
    }

    /**
     * Menghapus transaksi membalik pengaruhnya sepenuhnya — termasuk
     * mengembalikan sisa utang bila yang dihapus adalah pembayaran pokok.
     *
     * Tetap diperiksa LedgerGuard: menghapus sebuah pemasukan bisa membuat
     * saldo rekening menjadi minus karena pengeluaran setelahnya tetap ada.
     */
    public function destroy(Request $request, Transaction $transaction): RedirectResponse
    {
        abort_unless($transaction->user_id === $request->user()->id, 403);

        $user = $request->user();

        DB::transaction(function () use ($user, $transaction) {
            $transaction->delete();
            $this->penjaga->assertConsistent($user, 'transaction');
        });

        return back();
    }
}
