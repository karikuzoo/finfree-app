<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan pengguna pada tanggal tertentu di kalender aktivitas.
 *
 * Terpisah dari `goal_contributions.note`: catatan di sana menempel pada satu
 * setoran, sedangkan catatan di sini menempel pada tanggalnya — bisa ada di
 * tanggal yang tidak punya setoran sama sekali ("gajian", "bayar pajak
 * kendaraan", "jangan lupa naikkan setoran bulan depan").
 *
 * Satu catatan per tanggal per pengguna. Dijamin oleh indeks unik, bukan hanya
 * oleh logika aplikasi — tanpa itu, dua request yang datang bersamaan bisa
 * menyisipkan dua baris untuk tanggal yang sama dan kalender jadi menampilkan
 * catatan ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calendar_notes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->date('note_date');
            $table->string('body', 500);
            $table->timestamps();

            $table->unique(['user_id', 'note_date']);
            // Kalender selalu diambil per rentang bulan, jadi inilah pola
            // query yang sebenarnya dipakai.
            $table->index(['user_id', 'note_date']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calendar_notes');
    }
};
