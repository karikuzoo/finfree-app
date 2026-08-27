<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Pencatatan setoran ke sebuah tujuan (PRD FR-32..FR-35).
 *
 * `current_amount` sebuah goal TIDAK disimpan sebagai kolom — selalu
 * dihitung sebagai `initial_amount + SUM(amount)` (lihat
 * DashboardSummaryService). Menyimpannya sebagai kolom cache berarti dua
 * sumber kebenaran yang bisa saling menyimpang begitu ada setoran diedit
 * atau dihapus.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('goal_contributions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('financial_goal_id')->constrained()->cascadeOnDelete();

            $table->decimal('amount', 18, 2);
            $table->date('contributed_on');
            $table->text('note')->nullable();

            $table->timestamps();

            $table->index(['financial_goal_id', 'contributed_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('goal_contributions');
    }
};
