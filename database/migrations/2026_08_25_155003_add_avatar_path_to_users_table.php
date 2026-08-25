<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Foto profil (PRD FR-3).
 *
 * Yang disimpan hanya *jalur* berkasnya, bukan isi gambarnya. Menyimpan gambar
 * sebagai blob di database membuat setiap query yang menyentuh baris user ikut
 * menyeret data biner, dan backup membengkak tanpa alasan.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->string('avatar_path')->nullable()->after('prefers_syariah');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn('avatar_path');
        });
    }
};
