<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Tujuan utama yang DIPILIH pengguna. NULL berarti belum memilih,
            // dan sistem jatuh ke tujuan tertua — perilaku yang berlaku
            // sebelum kolom ini ada, jadi data lama tetap benar tanpa migrasi
            // data apa pun.
            //
            // Disimpan sebagai satu kolom di users, BUKAN penanda boolean di
            // tiap baris financial_goals. Boolean membolehkan dua tujuan
            // bertanda utama sekaligus bila ada satu jalur kode yang lupa
            // membersihkan yang lama; satu kolom membuat keadaan itu mustahil.
            //
            // nullOnDelete: menghapus tujuan yang sedang jadi utama tidak boleh
            // menggagalkan penghapusannya — cukup kembali ke tujuan tertua.
            $table->foreignId('primary_goal_id')
                ->nullable()
                ->after('prefers_syariah')
                ->constrained('financial_goals')
                ->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropConstrainedForeignId('primary_goal_id');
        });
    }
};
