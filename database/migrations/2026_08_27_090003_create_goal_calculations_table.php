<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Arsip hasil GoalCalculatorService yang menempel pada sebuah tujuan.
 * Bukan tempat menghitung — service tetap satu-satunya sumber rumus
 * (CLAUDE.md §6.6); tabel ini murni menyimpan hasilnya.
 *
 * Cuma `created_at` (lihat GoalCalculation::UPDATED_AT), karena baris ini
 * snapshot historis yang tidak pernah diedit setelah dibuat.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_calculations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_goal_id')->constrained()->cascadeOnDelete();

            $table->decimal('monthly_contribution_required', 18, 2);
            $table->decimal('total_contribution_projection', 18, 2);
            $table->decimal('total_investment_growth_projection', 18, 2);

            // jsonb, bukan json biasa — test suite berjalan di PostgreSQL
            // sungguhan (lihat phpunit.xml) justru supaya perbedaan tipe
            // seperti ini tidak diam-diam lolos. Menyimpan seluruh hasil
            // GoalCalculatorService apa adanya (future_value_target,
            // monthly_rate, already_achieved, dst), bukan cuma tiga kolom
            // di atas.
            $table->jsonb('calculation_snapshot')->nullable();

            // Cocok dengan GoalCalculatorService::FORMULA_VERSION — dicatat
            // per baris supaya hasil lama tetap bisa dijelaskan meski
            // rumus berubah di kemudian hari.
            $table->unsignedSmallInteger('formula_version')->default(1);

            $table->timestamp('created_at')->useCurrent();

            $table->index(['financial_goal_id', 'created_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_calculations');
    }
};
