<?php

namespace App\Enums;

/**
 * Profil risiko investasi.
 *
 * **Sumber kebenaran nilai enum ini untuk seluruh aplikasi.** Kolom
 * `users.risk_profile` dan `financial_goals.risk_profile_override` (FR-24)
 * wajib memakai kelas ini, bukan menuliskan string-nya sendiri-sendiri.
 *
 * Alasannya praktis: kedua kolom itu dikerjakan orang yang berbeda. Kalau
 * masing-masing menulis nilainya sebagai string lepas, cukup satu orang
 * mengetik `konservatif` alih-alih `conservative` untuk membuat keduanya
 * tidak cocok — dan itu baru ketahuan saat kedua fitur disambungkan, ketika
 * sudah ada data telanjur tersimpan.
 */
enum RiskProfile: string
{
    case Conservative = 'conservative';
    case Moderate = 'moderate';
    case Aggressive = 'aggressive';

    /** Label yang ditampilkan ke pengguna. */
    public function label(): string
    {
        return match ($this) {
            self::Conservative => 'Konservatif',
            self::Moderate => 'Moderat',
            self::Aggressive => 'Agresif',
        };
    }

    /** Penjelasan singkat untuk membantu pengguna memilih. */
    public function description(): string
    {
        return match ($this) {
            self::Conservative => 'Mengutamakan keamanan pokok. Imbal hasil lebih rendah, tetapi nilainya jarang turun.',
            self::Moderate => 'Menyeimbangkan pertumbuhan dan keamanan. Nilainya bisa naik-turun dalam jangka pendek.',
            self::Aggressive => 'Mengejar pertumbuhan jangka panjang. Siap menerima penurunan nilai yang cukup dalam.',
        };
    }

    /** Untuk dikirim sebagai props ke halaman Inertia. */
    public static function options(): array
    {
        return array_map(
            fn (self $case) => [
                'value' => $case->value,
                'label' => $case->label(),
                'description' => $case->description(),
            ],
            self::cases(),
        );
    }

    /** Nilai apa adanya — dipakai aturan validasi dan definisi kolom migrasi. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
