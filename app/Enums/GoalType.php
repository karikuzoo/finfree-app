<?php

namespace App\Enums;

/**
 * Jenis tujuan finansial (PRD FR-4).
 *
 * Sama seperti RiskProfile: `values()` dipakai langsung sebagai daftar
 * native PostgreSQL enum() di migrasi `financial_goals`, bukan daftar
 * string yang ditulis ulang secara terpisah di sana.
 */
enum GoalType: string
{
    case Retirement = 'retirement';
    case House = 'house';
    case Vehicle = 'vehicle';
    case Emergency = 'emergency';
    case Education = 'education';
    case Custom = 'custom';

    /** Label yang ditampilkan ke pengguna. */
    public function label(): string
    {
        return match ($this) {
            self::Retirement => 'Dana Pensiun',
            self::House => 'Beli Rumah',
            self::Vehicle => 'Beli Kendaraan',
            self::Emergency => 'Dana Darurat',
            self::Education => 'Dana Pendidikan',
            self::Custom => 'Kustom',
        };
    }

    /** Nilai apa adanya — dipakai aturan validasi dan definisi kolom migrasi. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
