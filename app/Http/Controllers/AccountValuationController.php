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

        // FR-51: jumlah satuan terbaru, hanya untuk jenis bersatuan. Kosong
        // berarti "tidak diubah", jadi nilai lamanya dipertahankan.
        $satuanBaru = $account->kind->satuan() !== null && ($data['units'] ?? null) !== null
            ? round((float) $data['units'], 4)
            : null;
        $satuanBerubah = $satuanBaru !== null && $satuanBaru !== round((float) $account->units, 4);

        // Tidak ada perubahan berarti tidak ada yang perlu dicatat. Menyimpan
        // penyesuaian bernilai nol hanya mengotori riwayat dengan baris yang
        // tidak mengubah apa pun — dan kolom `amount` memang menolak nol.
        // Pengecualiannya: nilainya tetap tapi jumlah satuannya berubah
        // (mis. membetulkan berat gram yang salah ketik) — satuannya saja
        // yang disimpan, tanpa transaksi.
        if ($selisih === 0.0 && ! $satuanBerubah) {
            throw ValidationException::withMessages([
                'value' => 'Nilainya sama dengan yang tercatat sekarang.',
            ]);
        }

        DB::transaction(function () use ($user, $account, $data, $selisih, $satuanBerubah, $satuanBaru) {
            if ($selisih !== 0.0) {
                $user->transactions()->create([
                    'account_id' => $account->id,
                    'type' => TransactionType::Adjustment->value,
                    'name' => 'Penilaian ulang '.$account->name,
                    'amount' => $selisih,
                    'occurred_on' => $data['occurred_on'],
                ]);
            }

            if ($satuanBerubah) {
                $account->update(['units' => $satuanBaru]);
            }

            $this->penjaga->assertConsistent($user, 'value');
        });

        return back();
    }
}
