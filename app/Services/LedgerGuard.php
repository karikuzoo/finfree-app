<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Validation\ValidationException;

/**
 * Menegakkan invarian buku besar SETELAH perubahan ditulis, di dalam
 * transaksi database yang sama, lalu membatalkannya bila dilanggar.
 *
 * Kenapa sesudah dan bukan sebelum: kedua aturan di bawah bergantung pada
 * keadaan HASIL, bukan keadaan awal. Menyunting transaksi lama bisa membuat
 * saldo minus di tanggal mana pun; memindahkan transaksi ke rekening lain
 * memengaruhi dua rekening sekaligus; mengubah nominal pembayaran mengubah
 * sisa utang. Memeriksanya di depan berarti menghitung ulang seluruh
 * kemungkinan perubahan secara manual — dan setiap jenis suntingan baru
 * menambah satu cabang yang mudah terlupa. Menulis dulu lalu memeriksa
 * hasilnya menangkap semuanya dengan satu aturan.
 *
 * Pesannya dilemparkan sebagai ValidationException, bukan exception biasa,
 * supaya sampai ke pengguna sebagai galat formulir dalam bahasa Indonesia —
 * bukan halaman 500.
 */
class LedgerGuard
{
    public function __construct(private AccountBalanceService $saldo) {}

    /**
     * @param  string  $field  Kolom formulir yang galatnya ditempelkan.
     */
    public function assertConsistent(User $user, string $field = 'amount'): void
    {
        $this->assertAccountsNotOverdrawn($user, $field);
        $this->assertDebtsNotOverpaid($user, $field);
    }

    /**
     * Saldo rekening tidak boleh minus. Aplikasi ini mencatat uang yang
     * benar-benar ada; saldo negatif selalu berarti pencatatannya keliru,
     * bukan keadaan yang perlu didukung.
     */
    private function assertAccountsNotOverdrawn(User $user, string $field): void
    {
        $saldo = $this->saldo->forUser($user);
        $minus = array_filter($saldo, fn (float $n) => $n < 0);

        if ($minus === []) {
            return;
        }

        $nama = $user->accounts()
            ->whereIn('id', array_keys($minus))
            ->pluck('name')
            ->implode(', ');

        throw ValidationException::withMessages([
            $field => "Saldo {$nama} tidak mencukupi untuk perubahan ini.",
        ]);
    }

    /**
     * Pembayaran tidak boleh melebihi sisa pokok. Utang yang sisanya minus
     * akan tampil sebagai "lunas berlebih" — angka yang tidak punya arti.
     */
    private function assertDebtsNotOverpaid(User $user, string $field): void
    {
        $sisa = $this->saldo->debtRemaining($user);
        $lebih = array_filter($sisa, fn (float $n) => $n < 0);

        if ($lebih === []) {
            return;
        }

        $nama = $user->debts()
            ->whereIn('id', array_keys($lebih))
            ->pluck('name')
            ->implode(', ');

        throw ValidationException::withMessages([
            $field => "Pembayaran melebihi sisa pokok {$nama}.",
        ]);
    }
}
