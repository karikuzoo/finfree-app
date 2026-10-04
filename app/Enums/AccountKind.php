<?php

namespace App\Enums;

/**
 * Jenis rekening atau aset (PRD FR-63).
 *
 * Pembedaan `bank`/`cash`/`valas` dari sisanya BUKAN sekadar label: hanya
 * ketiganya yang boleh menampung dana target. Menandai sebagian portofolio saham
 * sebagai "dana DP rumah" menjanjikan kepastian yang tidak dimiliki
 * instrumen bernilai fluktuatif — nilainya bisa turun setelah ditandai,
 * dan targetnya diam-diam meleset. Lihat `likuid()`.
 *
 * Sama seperti GoalType: `values()` dipakai langsung sebagai daftar enum
 * native PostgreSQL di migrasi, bukan ditulis ulang di sana.
 *
 * `valas` (tabungan mata uang asing, 4 Okt 2026) satu-satunya jenis yang
 * likuid SEKALIGUS perlu dinilai ulang: uangnya bisa dicairkan kapan saja dan
 * boleh menampung dana tujuan (keputusan pengguna), tetapi nilai rupiahnya
 * bergerak mengikuti kurs. Mata uangnya per rekening (`accounts.currency`),
 * dan jumlah valasnya disimpan sebagai `units` — keterangan, sama seperti
 * gram emas. Kurs tidak diambil dari mana pun: pengguna memperbarui nilai
 * rupiahnya sendiri (aturan D-7, tanpa angka tak bersumber).
 */
enum AccountKind: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Stock = 'stock';
    case Fund = 'fund';
    case Gold = 'gold';
    case ForeignCurrency = 'valas';

    /** Label yang ditampilkan ke pengguna. */
    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::Cash => 'Tunai',
            self::Stock => 'Saham',
            self::Fund => 'Reksa Dana',
            self::Gold => 'Emas',
            self::ForeignCurrency => 'Valas',
        };
    }

    /**
     * Nilainya berubah karena pasar, bukan karena pengguna memasukkan
     * transaksi — jadi butuh penyesuaian nilai manual secara berkala.
     */
    public function perluPenilaian(): bool
    {
        return ! $this->likuid() || $this === self::ForeignCurrency;
    }

    /**
     * Satuan jumlah aset (PRD FR-51): gram untuk emas, lot untuk saham, unit
     * penyertaan untuk reksa dana. NULL untuk bank dan tunai — uang tidak
     * punya satuan selain rupiahnya sendiri.
     *
     * Satuannya hanya KETERANGAN. Nilai rupiah tetap satu-satunya dasar
     * perhitungan: kekayaan bersih, komposisi, dan dana tujuan tidak pernah
     * menyentuh jumlah satuan.
     *
     * NULL juga untuk valas — satuannya kode mata uang PER REKENING, bukan
     * per jenis. Pakai Account::satuan(), bukan metode ini, di mana pun yang
     * menyangkut satu rekening; lihat juga punyaSatuan().
     */
    public function satuan(): ?string
    {
        return match ($this) {
            self::Gold => 'gram',
            self::Stock => 'lot',
            self::Fund => 'unit',
            default => null,
        };
    }

    /** Rekening jenis ini mencatat jumlah satuan (gram, lot, unit, atau valas). */
    public function punyaSatuan(): bool
    {
        return $this->satuan() !== null || $this === self::ForeignCurrency;
    }

    /** @return array<int, string> Jenis yang punya satuan. */
    public static function bersatuan(): array
    {
        return array_column(
            array_filter(self::cases(), fn (self $k) => $k->punyaSatuan()),
            'value',
        );
    }

    /** Boleh menampung dana target. Lihat alasannya di komentar kelas. */
    public function likuid(): bool
    {
        return in_array($this, [self::Bank, self::Cash, self::ForeignCurrency], true);
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }

    /** @return array<int, string> */
    public static function nilaiLikuid(): array
    {
        return array_column(
            array_filter(self::cases(), fn (self $k) => $k->likuid()),
            'value',
        );
    }
}
