<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Utang dan cicilan (PRD FR-70).
 *
 * Tabelnya dibuat sekarang meski halaman Utang baru menyusul di fase
 * berikutnya: `transactions.debt_id` menunjuk ke sini, dan menambahkan
 * foreign key setelah tabel transaksi terisi jauh lebih merepotkan
 * daripada membuat lima kolom ini lebih awal.
 *
 * `principal` adalah sisa pokok pada saat pengguna MULAI mencatat, bukan
 * nilai pinjaman aslinya — sejajar dengan `accounts.opening_balance`.
 * Sisa utang berjalan dihitung sebagai `principal` dikurangi seluruh
 * transaksi berjenis `payment` yang menunjuk ke baris ini; tidak disimpan
 * sebagai kolom, dengan alasan yang sama seperti saldo rekening.
 *
 * **Mencatat utang tidak menambah saldo rekening mana pun.** Pinjaman yang
 * benar-benar baru diterima dicatat terpisah sebagai penyesuaian saldo,
 * kalau tidak uangnya akan terhitung sebagai penghasilan.
 *
 * Bunga TIDAK dimodelkan di sini. Ia dicatat sebagai pengeluaran biasa,
 * supaya pokok utang yang tersisa selalu menunjukkan angka yang sebenarnya
 * — mencampur bunga ke dalam pembayaran pokok membuat utang tampak lunas
 * lebih cepat daripada kenyataannya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('debts', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('name');
            $table->decimal('principal', 18, 2);

            // Rencana pokok per bulan. Dipakai penghitung kemampuan
            // menabung untuk menyisihkan kewajiban lebih dulu sebelum
            // membagi sisanya ke target.
            $table->decimal('monthly_principal', 18, 2)->default(0);

            $table->date('due_on')->nullable();

            $table->timestamps();

            $table->index(['user_id', 'due_on']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('debts');
    }
};
