<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tautan foto artikel dari penerbitnya (keputusan pengguna 27 Sep 2026,
 * dicatat di PRD D-16).
 *
 * Migrasi pembuatan tabel ini sengaja tidak memuat gambar. Keputusannya
 * berubah: kartu berita yang hanya berisi tulisan terasa gersang, dan
 * pengguna memilih menampilkan foto artikel.
 *
 * Yang disimpan hanya TAUTAN-nya. Fotonya tidak pernah diunduh atau disalin
 * ke server Arus — browser memuatnya langsung dari server penerbit, dan
 * kartunya selalu menyebut penerbit serta menautkan ke artikel aslinya.
 * Fotonya tetap hak cipta penerbit.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('news_article_cache', function (Blueprint $table) {
            $table->string('image_url', 2048)->nullable()->after('summary');
        });
    }

    public function down(): void
    {
        Schema::table('news_article_cache', function (Blueprint $table) {
            $table->dropColumn('image_url');
        });
    }
};
