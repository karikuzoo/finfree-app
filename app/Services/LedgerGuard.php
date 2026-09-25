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
        $this->assertAllocationsWithinBalance($user, $field);
    }

    /**
     * Total dana yang ditandai untuk target pada sebuah rekening tidak boleh
     * melebihi saldonya.
     *
     * Alokasi hanya MENANDAI saldo, tidak memindahkan uang. Tanpa aturan ini,
     * uang yang sama bisa ditandai untuk DP rumah sekaligus dana darurat, dan
     * kedua target tampak berjalan sesuai rencana padahal hanya satu yang
     * benar-benar bisa dipenuhi.
     *
     * Diperiksa dari DUA arah: saat alokasi bertambah, dan saat saldo
     * rekeningnya berkurang karena pengeluaran. Keduanya lewat sini karena
     * keduanya memanggil assertConsistent().
     */
    private function assertAllocationsWithinBalance(User $user, string $field): void
    {
        $ditandai = $user->goals()
            ->whereNotNull('account_id')
            ->selectRaw('account_id, SUM(allocated_amount) AS total')
            ->groupBy('account_id')
            ->pluck('total', 'account_id');

        if ($ditandai->isEmpty()) {
            return;
        }

        $saldo = $this->saldo->forUser($user);

        foreach ($ditandai as $accountId => $total) {
            $tersedia = $saldo[$accountId] ?? 0;

            if ((float) $total <= $tersedia) {
                continue;
            }

            $nama = $user->accounts()->whereKey($accountId)->value('name');

            // Menyebut KEDUA angkanya, bukan menyuruh "kurangi alokasi target
            // lain". Saran itu benar hanya bila target lain memang memakan
            // saldonya; ketika yang diminta sekadar jauh melampaui isi
            // rekening, ia menyesatkan — pengguna akan sibuk mengurangi
            // alokasi yang bukan penyebabnya. Angka yang jujur membiarkan ia
            // menyimpulkan sendiri mana dari keduanya yang berlaku.
            throw ValidationException::withMessages([
                $field => sprintf(
                    'Total dana untuk target di %s jadi %s, melebihi saldonya yang %s.',
                    $nama,
                    $this->rupiah($total),
                    $this->rupiah($tersedia),
                ),
            ]);
        }
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

        // Menyebut KEKURANGANNYA, bukan sekadar "tidak mencukupi". Selisihnya
        // persis angka yang dibutuhkan pengguna untuk memutuskan apa yang
        // harus diubah — tanpa itu ia harus menghitung sendiri dari dua layar
        // yang berbeda.
        $nama = $user->accounts()
            ->whereIn('id', array_keys($minus))
            ->pluck('name', 'id')
            ->map(fn (string $n, int $id) => $n.' (kurang '.$this->rupiah(-$minus[$id]).')')
            ->implode(', ');

        throw ValidationException::withMessages([
            $field => "Saldo tidak mencukupi: {$nama}.",
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
            ->pluck('name', 'id')
            ->map(fn (string $n, int $id) => $n.' (lebih '.$this->rupiah(-$lebih[$id]).')')
            ->implode(', ');

        throw ValidationException::withMessages([
            $field => "Pembayaran melebihi sisa pokok: {$nama}.",
        ]);
    }

    private function rupiah(float|string $nominal): string
    {
        return 'Rp '.number_format((float) $nominal, 0, ',', '.');
    }
}
