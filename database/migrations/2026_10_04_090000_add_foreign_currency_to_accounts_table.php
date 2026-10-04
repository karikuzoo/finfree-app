<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Tabungan mata uang asing (jenis `valas`, 4 Okt 2026).
 *
 * - `currency`: kode ISO 4217 (USD, SGD, …), hanya untuk valas. Jumlah
 *   valasnya memakai kolom `units` yang sudah ada — keterangan, sama seperti
 *   gram emas. Nilai rupiahnya tetap dari saldo awal + riwayat.
 * - `kind` di PostgreSQL adalah varchar + CHECK (bawaan `$table->enum`), jadi
 *   jenis baru berarti CHECK-nya diganti. Daftarnya ditulis di sini, bukan
 *   dari AccountKind::values(): migrasi harus menghasilkan skema yang sama
 *   kapan pun dijalankan, apa pun isi enum di masa depan.
 */
return new class extends Migration
{
    private const LAMA = ['bank', 'cash', 'stock', 'fund', 'gold'];

    private const BARU = ['bank', 'cash', 'stock', 'fund', 'gold', 'valas'];

    public function up(): void
    {
        Schema::table('accounts', function (Blueprint $table) {
            $table->char('currency', 3)->nullable()->after('kind');
        });

        $this->gantiCek(self::BARU);
    }

    public function down(): void
    {
        // Rekening valas tidak punya padanan di skema lama.
        DB::table('accounts')->where('kind', 'valas')->delete();

        $this->gantiCek(self::LAMA);

        Schema::table('accounts', function (Blueprint $table) {
            $table->dropColumn('currency');
        });
    }

    private function gantiCek(array $jenis): void
    {
        DB::statement('ALTER TABLE accounts DROP CONSTRAINT IF EXISTS accounts_kind_check');
        DB::statement(
            'ALTER TABLE accounts ADD CONSTRAINT accounts_kind_check CHECK (kind IN ('
            .implode(', ', array_map(fn ($j) => "'{$j}'", $jenis))
            .'))',
        );
    }
};
