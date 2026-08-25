<?php

use App\Enums\RiskProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Kolom preferensi pengguna (PRD FR-3, FR-19, FR-27).
 *
 * Catatan: kolom `deleted_at` yang sempat tercantum di rancangan skema
 * CLAUDE.md §5 **sengaja tidak dibuat**. Soft delete berarti data pengguna
 * tetap tersimpan setelah akun "dihapus", dan itu bertentangan dengan FR-37
 * yang menuntut penghapusan permanen sebagai pemenuhan hak penghapusan
 * UU 27/2022 PDP. Penghapusan akun di aplikasi ini adalah hard delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Nilai enum berasal dari App\Enums\RiskProfile agar tidak ada
            // daftar string kedua yang bisa menyimpang diam-diam.
            $table->enum('risk_profile', RiskProfile::values())
                ->default(RiskProfile::Moderate->value)
                ->after('password');

            // Disiapkan meski MVP hanya mendukung IDR (PRD §11) — menambah
            // kolom belakangan pada tabel berisi data jauh lebih merepotkan
            // daripada menyediakannya sekarang.
            $table->char('currency_preference', 3)
                ->default('IDR')
                ->after('risk_profile');

            $table->boolean('prefers_syariah')
                ->default(false)
                ->after('currency_preference');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'risk_profile',
                'currency_preference',
                'prefers_syariah',
            ]);
        });
    }
};
