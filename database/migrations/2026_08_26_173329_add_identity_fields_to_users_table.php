<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Data identitas pengguna (PRD FR-3).
 *
 * **Keempatnya nullable dan sepenuhnya opsional.** UU 27/2022 PDP menganut
 * prinsip minimasi data: mengumpulkan data pribadi tanpa keperluan yang jelas
 * adalah beban, bukan aset. Karena itu tiap kolom di bawah disertai alasan
 * kenapa aplikasi ini memintanya — dan tidak ada satu pun yang wajib diisi
 * untuk memakai aplikasi.
 *
 * Ketika akun dihapus (FR-37), seluruh kolom ini ikut lenyap karena berada di
 * tabel `users` yang dihapus permanen, bukan soft delete.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            // Dipakai kalkulator dana pensiun (FR-20) untuk menurunkan jangka
            // waktu dari usia sekarang ke usia pensiun, sehingga pengguna tidak
            // perlu menghitung sendiri berapa bulan tersisa.
            $table->date('birth_date')->nullable()->after('avatar_path');

            // Sebagian instrumen investasi di Indonesia punya syarat
            // kewarganegaraan yang berbeda. Relevan saat mesin rekomendasi
            // (FR-10) mulai menyaring instrumen.
            $table->string('nationality', 100)->nullable()->after('birth_date');

            // Untuk pengingat setoran bulanan lewat WhatsApp/SMS yang
            // direncanakan di Fase 2. Sampai fitur itu ada, kolom ini hanya
            // tersimpan dan tidak dipakai untuk apa pun.
            $table->string('phone', 20)->nullable()->after('nationality');

            // Stabilitas penghasilan mempengaruhi profil risiko yang masuk
            // akal — pekerja tetap dan pekerja lepas tidak layak disarankan
            // alokasi yang sama.
            $table->string('occupation', 100)->nullable()->after('phone');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn([
                'birth_date',
                'nationality',
                'phone',
                'occupation',
            ]);
        });
    }
};
