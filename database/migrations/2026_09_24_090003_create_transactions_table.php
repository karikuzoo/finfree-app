<?php

use App\Enums\TransactionType;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Catatan transaksi (PRD FR-64..FR-69).
 *
 * Satu baris untuk kelima jenis transaksi, bukan tabel terpisah per jenis.
 * Alasannya: pengguna melihatnya sebagai satu daftar berurutan waktu, dan
 * saldo rekening adalah penjumlahan atas seluruh jenis sekaligus. Memecahnya
 * menjadi beberapa tabel berarti setiap perhitungan saldo menjadi UNION.
 *
 * Konsekuensinya dua kolom di bawah hanya terisi untuk jenis tertentu, dan
 * ITU DIJAGA DI SISI APLIKASI (StoreTransactionRequest), bukan oleh basis
 * data. Postgres bisa menegakkannya lewat CHECK, tetapi pesan pelanggaran
 * constraint tidak bisa disampaikan kepada pengguna dalam bahasa yang
 * berarti — sedangkan aturan ini justru yang paling sering dilanggar saat
 * mengisi formulir:
 *
 * - `to_account_id` hanya untuk `transfer`, dan wajib berbeda dari `account_id`
 * - `debt_id` hanya untuk `payment`
 *
 * `occurred_on` memakai tanggal Asia/Jakarta dan tidak boleh melewati hari
 * ini — pencatatan keuangan mundur itu wajar, maju tidak.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transactions', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Rekening asal. Menghapus rekening yang masih punya transaksi
            // ditolak di sisi aplikasi lebih dulu dengan pesan yang jelas;
            // restrictOnDelete di sini adalah jaring pengaman terakhir
            // supaya riwayat tidak pernah lenyap diam-diam.
            $table->foreignId('account_id')->constrained()->restrictOnDelete();

            $table->enum('type', TransactionType::values());
            $table->string('name');

            // Boleh negatif HANYA untuk `adjustment` — penurunan nilai aset.
            $table->decimal('amount', 18, 2);

            $table->foreignId('to_account_id')->nullable()
                ->constrained('accounts')->restrictOnDelete();
            $table->foreignId('debt_id')->nullable()
                ->constrained()->restrictOnDelete();

            // Kolom teks, bukan tabel tersendiri. Kategori di sini hanyalah
            // label untuk mengelompokkan; belum ada anggaran per kategori,
            // hierarki, maupun warna yang menuntut baris sendiri. Dinaikkan
            // jadi tabel kalau kebutuhan itu benar-benar muncul.
            $table->string('category')->nullable();

            $table->date('occurred_on');

            $table->timestamps();

            // Daftar transaksi selalu dibaca per pengguna, diurutkan mundur
            // menurut tanggal, dan disaring per bulan.
            $table->index(['user_id', 'occurred_on']);
            $table->index(['account_id', 'occurred_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('transactions');
    }
};
