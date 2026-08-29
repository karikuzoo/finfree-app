<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Nominal yang pengguna komit sisihkan tiap hari untuk sebuah goal —
 * diisi manual oleh pengguna (bukan hasil kalkulator), dipakai
 * DashboardSummaryService untuk memproyeksikan progres di kartu utama
 * Dashboard bersama `current_amount` (initial_amount + SUM(contributions)).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->decimal('daily_savings_target', 18, 2)
                ->default(0)
                ->after('initial_amount');
        });
    }

    public function down(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->dropColumn('daily_savings_target');
        });
    }
};
