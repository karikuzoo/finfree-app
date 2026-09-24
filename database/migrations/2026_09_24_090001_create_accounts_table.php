<?php

use App\Enums\AccountKind;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Rekening dan aset pengguna (PRD FR-63).
 *
 * **Saldo TIDAK disimpan sebagai kolom.** Ia selalu dihitung sebagai
 * `opening_balance` ditambah seluruh transaksi yang menyentuh rekening ini
 * (lihat AccountBalanceService), mengikuti alasan yang sama seperti
 * `current_amount` pada goal_contributions: kolom cache berarti dua sumber
 * kebenaran yang langsung menyimpang begitu ada transaksi disunting atau
 * dihapus — dan pada saldo rekening, penyimpangan itu tidak akan pernah
 * disadari pengguna sampai angkanya sudah jauh salah.
 *
 * `opening_balance` adalah saldo pada saat pengguna MULAI mencatat, bukan
 * saldo saat ini. Tanpa kolom ini pengguna harus memasukkan seluruh riwayat
 * transaksinya sejak rekening dibuka hanya agar saldonya benar.
 *
 * Tanpa `deleted_at` — sama seperti tabel lain di aplikasi ini, penghapusan
 * selalu permanen (FR-37).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('accounts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');

            // Nilai enum berasal dari App\Enums\AccountKind, bukan daftar
            // string yang ditulis ulang di sini.
            $table->enum('kind', AccountKind::values());

            // Nama bank atau sekuritas. Boleh kosong: uang tunai di dompet
            // tidak punya lembaga, dan memaksa mengisinya hanya melahirkan
            // isian asal-asalan.
            $table->string('institution')->nullable();

            $table->decimal('opening_balance', 18, 2)->default(0);

            $table->timestamps();

            $table->index(['user_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('accounts');
    }
};
