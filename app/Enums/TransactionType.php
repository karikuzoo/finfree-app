<?php

namespace App\Enums;

/**
 * Jenis transaksi (PRD FR-64).
 *
 * Lima jenis ini sengaja dibedakan karena masing-masing memperlakukan saldo
 * dan arus kas secara berbeda — dan menyatukannya adalah sumber kekeliruan
 * paling umum pada pencatat keuangan:
 *
 * - `income`/`expense` menambah dan mengurangi saldo, DAN masuk arus kas.
 * - `transfer` memindahkan uang antar rekening. Saldo dua rekening berubah,
 *   tetapi kekayaan tidak, dan ia TIDAK masuk arus kas — membeli reksa dana
 *   bukan pengeluaran, hanya berpindah tempat.
 * - `adjustment` mengoreksi nilai aset yang bergerak sendiri (harga saham,
 *   emas). Mengubah kekayaan tanpa ada uang yang benar-benar mengalir, jadi
 *   juga di luar arus kas. Satu-satunya jenis yang boleh bernilai negatif.
 * - `payment` membayar pokok utang. Mengurangi saldo DAN sisa utang
 *   sekaligus, sehingga kekayaan bersih tidak berubah — tetapi uangnya nyata
 *   keluar, jadi ia tetap masuk arus kas. Bunga dicatat terpisah sebagai
 *   `expense`; mencampurnya membuat pokok utang terlihat lunas lebih cepat
 *   daripada kenyataannya.
 */
enum TransactionType: string
{
    case Income = 'income';
    case Expense = 'expense';
    case Transfer = 'transfer';
    case Adjustment = 'adjustment';
    case Payment = 'payment';

    /** Label yang ditampilkan ke pengguna. */
    public function label(): string
    {
        return match ($this) {
            self::Income => 'Pemasukan',
            self::Expense => 'Pengeluaran',
            self::Transfer => 'Transfer',
            self::Adjustment => 'Penyesuaian nilai',
            self::Payment => 'Pembayaran pokok utang',
        };
    }

    /** Menambah saldo rekening asal, bukan menguranginya. */
    public function menambahSaldo(): bool
    {
        return in_array($this, [self::Income, self::Adjustment], true);
    }

    /**
     * Ikut dihitung sebagai arus kas periode berjalan. Transfer dan
     * penyesuaian tidak: tidak ada uang yang masuk atau keluar dari
     * keseluruhan kekayaan pengguna.
     */
    public function arusKas(): bool
    {
        return in_array($this, [self::Income, self::Expense, self::Payment], true);
    }

    /** Satu-satunya jenis yang boleh bernilai negatif. */
    public function bolehNegatif(): bool
    {
        return $this === self::Adjustment;
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
