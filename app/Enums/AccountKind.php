<?php

namespace App\Enums;

/**
 * Jenis rekening atau aset (PRD FR-63).
 *
 * Pembedaan `bank`/`cash` dari sisanya BUKAN sekadar label: hanya keduanya
 * yang boleh menampung dana target. Menandai sebagian portofolio saham
 * sebagai "dana DP rumah" menjanjikan kepastian yang tidak dimiliki
 * instrumen bernilai fluktuatif — nilainya bisa turun setelah ditandai,
 * dan targetnya diam-diam meleset. Lihat `likuid()`.
 *
 * Sama seperti GoalType: `values()` dipakai langsung sebagai daftar enum
 * native PostgreSQL di migrasi, bukan ditulis ulang di sana.
 */
enum AccountKind: string
{
    case Bank = 'bank';
    case Cash = 'cash';
    case Stock = 'stock';
    case Fund = 'fund';
    case Gold = 'gold';

    /** Label yang ditampilkan ke pengguna. */
    public function label(): string
    {
        return match ($this) {
            self::Bank => 'Bank',
            self::Cash => 'Tunai',
            self::Stock => 'Saham',
            self::Fund => 'Reksa Dana',
            self::Gold => 'Emas',
        };
    }

    /**
     * Nilainya berubah karena pasar, bukan karena pengguna memasukkan
     * transaksi — jadi butuh penyesuaian nilai manual secara berkala.
     */
    public function perluPenilaian(): bool
    {
        return ! $this->likuid();
    }

    /** Boleh menampung dana target. Lihat alasannya di komentar kelas. */
    public function likuid(): bool
    {
        return in_array($this, [self::Bank, self::Cash], true);
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
