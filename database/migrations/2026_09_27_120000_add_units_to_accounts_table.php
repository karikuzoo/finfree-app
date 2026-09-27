<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Jumlah satuan aset — gram emas, lot saham, unit reksa dana (PRD FR-51).
 *
 * KETERANGAN, bukan dasar perhitungan: saldo tetap `opening_balance` +
 * riwayat transaksi (D-12), dan tidak ada angka rupiah yang diturunkan dari
 * kolom ini. Ia disimpan apa adanya — jumlah yang pengguna pegang SEKARANG —
 * dan diperbarui bersama "Perbarui nilai".
 *
 * Nullable: satuan itu opsional, dan bank/tunai memang tidak punya satuan.
 * Empat desimal: unit penyertaan reksa dana lazim ditulis sampai empat angka
 * di belakang koma; gram emas cukup dua, tapi satu kolom untuk semuanya.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->decimal('units', 20, 4)->nullable()->after('opening_balance');
        });
    }

    public function down(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('units');
        });
    }
};
