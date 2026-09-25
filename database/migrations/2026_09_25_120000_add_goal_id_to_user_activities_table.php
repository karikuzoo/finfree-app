<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan baris aktivitas dengan tujuannya (PRD FR-85).
 *
 * Sebelumnya `user_activities` hanya menyimpan `goal_name`. Itu cukup selama
 * isinya sekadar dibaca sebagai kalimat, tetapi tidak cukup begitu ada yang
 * perlu MENCARI berdasarkan tujuannya — misalnya "apakah target ini sudah
 * disisihkan bulan ini". Mencocokkan lewat nama akan putus begitu tujuannya
 * diganti nama, dan aplikasi ini memang mengizinkan itu.
 *
 * NULLABLE dan `nullOnDelete`, bukan cascade: aktivitas `goal_deleted` justru
 * menjadi tidak berguna kalau ikut terhapus bersama tujuannya — "menghapus
 * tujuan X" adalah satu-satunya jejak bahwa X pernah ada. `goal_name` tetap
 * disimpan apa adanya supaya kalimatnya tetap terbaca setelah tautannya
 * dilepas.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->foreignId('financial_goal_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            $table->index(['financial_goal_id', 'type']);
        });
    }

    public function down(): void
    {
        Schema::table('user_activities', function (Blueprint $table) {
            $table->dropIndex(['financial_goal_id', 'type']);
            $table->dropConstrainedForeignId('financial_goal_id');
        });
    }
};
