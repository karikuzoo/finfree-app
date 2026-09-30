<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Memindahkan foto profil dari disk 'public' ke disk privat 'local'.
 *
 * Disk 'public' punya tautan public/storage, sehingga setiap foto di sana
 * bisa dibuka siapa saja lewat /storage/avatars/<nama> — tanpa login, dan
 * melewati pemeriksaan pemilik di AvatarFileController. Sejak 30 Sep 2026
 * AvatarService menulis ke disk 'local' (storage/app/private), yang tidak
 * punya tautan publik.
 *
 * Kolom users.avatar_path TIDAK berubah ("avatars/<nama>.webp" relatif ke
 * disk), jadi yang dipindahkan hanya berkasnya. Berkas yang sudah ada di
 * tujuan dilewati, supaya aman dijalankan ulang.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->pindahkan(from: 'public', to: 'local');
    }

    /**
     * Mengembalikan ke disk publik — hanya bila kode AvatarService juga
     * dikembalikan. Menjalankan down() saja membuat foto tidak bisa dibuka.
     */
    public function down(): void
    {
        $this->pindahkan(from: 'local', to: 'public');
    }

    private function pindahkan(string $from, string $to): void
    {
        // Test memakai RefreshDatabase, yang menjalankan SEMUA migrasi — dan
        // meski basis datanya fingoal_test, disk penyimpanannya milik
        // pengembang. Tanpa ini, menjalankan test memindahkan foto sungguhan
        // (terjadi saat migrasi ini pertama kali dibuat).
        if (app()->runningUnitTests()) {
            return;
        }

        $asal = Storage::disk($from);
        $tujuan = Storage::disk($to);

        foreach ($asal->files('avatars') as $path) {
            if (! $tujuan->exists($path)) {
                $tujuan->put($path, $asal->get($path));
            }

            $asal->delete($path);
        }
    }
};
