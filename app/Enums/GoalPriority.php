<?php

namespace App\Enums;

/**
 * Prioritas target tabungan (PRD FR-73).
 *
 * Menentukan urutan pembagian kemampuan menabung saat dananya tidak cukup
 * untuk semua target sekaligus — yang justru keadaan paling umum. Tanpa
 * prioritas, kekurangan dana tersebar merata ke semua target, sehingga tidak
 * ada satu pun yang tercapai tepat waktu.
 */
enum GoalPriority: string
{
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return match ($this) {
            self::High => 'Prioritas tinggi',
            self::Medium => 'Prioritas sedang',
            self::Low => 'Prioritas rendah',
        };
    }

    /** Makin kecil, makin dulu dilayani. Dipakai pengurutan di SavingsPlanService. */
    public function rank(): int
    {
        return match ($this) {
            self::High => 0,
            self::Medium => 1,
            self::Low => 2,
        };
    }

    /** @return array<int, string> */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
