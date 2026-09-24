<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Anggaran bulanan untuk rencana menabung (PRD FR-74).
 *
 * SATU baris per pengguna — `user_id` unik. Ini rencana yang berlaku
 * seterusnya, bukan catatan per bulan: pengguna mengisi "penghasilan saya
 * sekitar sekian, pengeluaran sekitar sekian", lalu memperbaruinya saat
 * keadaannya berubah. Menyimpannya per bulan akan menuntut pengisian ulang
 * tiap awal bulan hanya agar halaman rencananya tidak kosong.
 *
 * Angka di sini adalah RENCANA, bukan kenyataan. Yang sudah benar-benar
 * terjadi ada di tabel `transactions`, dan keduanya sengaja dipisah:
 * kemampuan menabung dihitung dari niat yang bisa dipegang, bukan dari
 * satu bulan kebetulan yang boros atau kebetulan hemat.
 *
 * `monthly_reserve` adalah dana yang sengaja TIDAK dialokasikan ke target —
 * penyangga untuk hal tak terduga. Tanpa kolom ini, rencana selalu membagi
 * habis seluruh sisa uang, dan pengeluaran mendadak sekecil apa pun langsung
 * membuat seluruh rencananya meleset.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('budgets', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->unique()->constrained()->cascadeOnDelete();

            $table->decimal('planned_income', 18, 2)->default(0);
            $table->decimal('planned_expenses', 18, 2)->default(0);
            $table->decimal('monthly_reserve', 18, 2)->default(0);

            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('budgets');
    }
};
