<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Riwayat kalkulasi cepat (PRD FR-45) — dicatat otomatis setiap kali
 * pengguna yang login menghitung di kalkulator publik.
 *
 * Yang disimpan hanya ISIAN kalkulator (`input`, persis yang sudah lolos
 * validasi) plus beberapa angka utama untuk daftar (`summary`). Hasil
 * lengkapnya tidak disimpan: membuka kembali berarti menghitung ulang dari
 * isiannya, sehingga riwayat tidak pernah menampilkan angka dari rumus versi
 * lama. Data cek kesehatan KPR (pendapatan dan seterusnya) TIDAK PERNAH masuk
 * ke sini — lihat CalculationHistoryService.
 *
 * `input_hash` + indeks unik: isian yang sama persis tidak dicatat dua kali,
 * cukup naik ke urutan teratas. Dijamin di basis data, bukan hanya di
 * aplikasi, supaya dua permintaan bersamaan tidak menyisipkan baris ganda.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('calculation_histories', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();
            $table->string('calculator', 20);
            $table->jsonb('input');
            $table->char('input_hash', 64);
            $table->jsonb('summary');
            $table->timestamps();

            $table->unique(['user_id', 'calculator', 'input_hash']);
            // Daftar riwayat selalu diurutkan dari yang terakhir dipakai.
            $table->index(['user_id', 'updated_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('calculation_histories');
    }
};
