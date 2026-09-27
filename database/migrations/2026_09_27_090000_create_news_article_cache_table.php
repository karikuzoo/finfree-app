<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Cache berita (PRD FR-16..FR-18, FR-28..FR-30, keputusan D-16).
 *
 * Halaman Berita membaca dari tabel ini, BUKAN langsung dari NewsData.io:
 * satu permintaan pengguna tidak boleh memakan kuota harian, dan halaman
 * harus tetap berisi saat sumbernya sedang tidak bisa dihubungi.
 *
 * Hanya METADATA yang disimpan — judul, ringkasan, sumber, tanggal, tautan.
 * Kolom `image_url` di rancangan awal sengaja tidak ada: paket gratis
 * NewsData.io mengizinkan metadata dipakai, tetapi tidak gambar dan isi
 * penuh artikel.
 *
 * Tidak menempel ke `users`: berita sama untuk semua orang, termasuk tamu.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('news_article_cache', function (Blueprint $table) {
            $table->id();

            // FR-29: job berjalan tiap jam dan akan menerima artikel yang
            // sama berulang kali. UNIQUE di basis data, bukan hanya
            // pemeriksaan di kode — dua job yang tumpang tindih tetap tidak
            // bisa menggandakannya.
            $table->string('url', 2048)->unique();

            $table->string('title', 500);
            $table->text('summary')->nullable();

            // `source_id` NewsData.io (mis. "kontan_co_id") dan nama tampilannya.
            $table->string('source', 100);
            $table->string('source_name', 150)->nullable();

            // Slug kategori dari config/news.php, bukan enum: kategorinya
            // diatur di konfigurasi (FR-28) dan boleh berubah tanpa migrasi.
            $table->string('category', 50);

            $table->timestamp('published_at');
            $table->timestamp('fetched_at');

            $table->index(['category', 'published_at']);
            $table->index('published_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('news_article_cache');
    }
};
