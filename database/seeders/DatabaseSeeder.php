<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    /**
     * Dijalankan lewat `php artisan db:seed`.
     *
     * Isi database tidak pernah ikut git — hanya skemanya, lewat migrasi.
     * Karena itu setiap orang yang baru clone mendapat tabel kosong, dan
     * seeder inilah yang mengisinya dengan data secukupnya untuk mencoba
     * aplikasi.
     */
    public function run(): void
    {
        $this->call(DemoSeeder::class);
    }
}
