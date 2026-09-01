<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('reminders', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            $table->string('title', 200);

            // Tanggal dan jam disimpan dalam SATU kolom, bukan dipisah.
            // Pengurutan, penyaringan "hari ini", dan pencarian pengingat
            // terdekat semuanya jadi satu perbandingan sederhana. Dipisah,
            // ketiganya menuntut penggabungan kolom di setiap kueri.
            $table->dateTime('remind_at');

            // Ditandai selesai, BUKAN dihapus — supaya pengingat yang sudah
            // dikerjakan tetap terlihat di tanggalnya sebagai catatan riwayat.
            // Menghapusnya membuat kalender bulan lalu tampak kosong padahal
            // ada yang dikerjakan.
            $table->timestamp('completed_at')->nullable();

            $table->timestamps();

            // Menopang dua kueri utama: pengingat hari ini, dan pengingat
            // sepanjang satu bulan untuk kalender.
            $table->index(['user_id', 'remind_at']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('reminders');
    }
};
