<?php

namespace App\Enums;

/** Status siklus hidup sebuah tujuan finansial. */
enum GoalStatus: string
{
    case Active = 'active';
    case Achieved = 'achieved';
    case Archived = 'archived';

    public function label(): string
    {
        return match ($this) {
            self::Active => 'Aktif',
            self::Achieved => 'Tercapai',
            self::Archived => 'Diarsipkan',
        };
    }

    /** Nilai apa adanya — dipakai aturan validasi dan definisi kolom migrasi. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
