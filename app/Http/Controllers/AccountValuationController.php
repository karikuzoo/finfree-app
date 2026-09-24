<?php

namespace App\Http\Controllers;

use App\Enums\TransactionType;
use App\Http\Requests\StoreValuationRequest;
use App\Models\Account;
use App\Services\AccountBalanceService;
use App\Services\LedgerGuard;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Memperbarui nilai sebuah rekening (PRD FR-72).
 *
 * Satu aksi, satu route — mengikuti pola GoalDailySavingsTargetController.
 *
 * Yang disimpan BUKAN nilai barunya, melainkan SELISIHNYA, sebagai transaksi
 * berjenis `adjustment`. Menyimpan nilai baru langsung ke kolom saldo akan
 * menabrak aturan dasar aplikasi ini: saldo selalu turunan dari saldo awal
 * ditambah riwayat, tidak pernah disimpan sendiri. Lewat selisih, kenaikan
 * harga emas punya jejak tanggal, muncul di daftar transaksi, dan bisa
 * dihapus bila ternyata salah — sama seperti catatan lain.
 */
class AccountValuationController extends Controller
{
    public function __construct(
        private AccountBalanceService $saldo,
        private LedgerGuard $penjaga,
    ) {}

    public function store(StoreValuationRequest $request, Account $account): RedirectResponse
    {
        $user = $request->user();
        $data = $request->validated();

        $sekarang = $this->saldo->forUser($user)[$account->id] ?? 0.0;
        $selisih = round((float) $data['value'] - $sekarang, 2);

        // Tidak ada perubahan berarti tidak ada yang perlu dicatat. Menyimpan
        // penyesuaian bernilai nol hanya mengotori riwayat dengan baris yang
        // tidak mengubah apa pun — dan kolom `amount` memang menolak nol.
        if ($selisih === 0.0) {
            throw ValidationException::withMessages([
                'value' => 'Nilainya sama dengan yang tercatat sekarang.',
            ]);
        }

        DB::transaction(function () use ($user, $account, $data, $selisih) {
            $user->transactions()->create([
                'account_id' => $account->id,
                'type' => TransactionType::Adjustment->value,
                'name' => 'Penilaian ulang '.$account->name,
                'amount' => $selisih,
                'occurred_on' => $data['occurred_on'],
            ]);

            $this->penjaga->assertConsistent($user, 'value');
        });

        return back();
    }
}
