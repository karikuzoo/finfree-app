<?php

use App\Enums\GoalPriority;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * Menghubungkan target tabungan dengan rekening tempat dananya berada
 * (PRD FR-73).
 *
 * **Alokasi hanya MENANDAI saldo, tidak memindahkan uang.** Menandai 10 juta
 * di rekening BCA untuk "DP rumah" tidak mengurangi saldo BCA dan tidak
 * menciptakan uang baru — ia cuma menyatakan bagian mana dari saldo itu yang
 * sudah punya tujuan. Karena itu total alokasi seluruh target pada sebuah
 * rekening tidak boleh melebihi saldonya; dijaga GoalAllocationGuard.
 *
 * `account_id` NULLABLE dan dibatasi ke rekening bank/tunai saja (ditegakkan
 * di request, bukan di sini): nilai saham dan emas bergerak sendiri, sehingga
 * target yang ditandai di sana bisa meleset diam-diam setelah harganya turun.
 *
 * `allocated_amount` DIISI DARI dana yang sudah tercatat pada tiap tujuan —
 * `initial_amount` ditambah seluruh setorannya. Tanpa pengisian awal ini,
 * setiap tujuan yang sudah berjalan akan mendadak tampil nol persen pada hari
 * migrasi dijalankan, dan pengguna akan mengira progresnya hilang.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->foreignId('account_id')->nullable()->after('user_id')
                ->constrained()->nullOnDelete();

            $table->decimal('allocated_amount', 18, 2)->default(0)->after('initial_amount');

            $table->enum('priority', GoalPriority::values())
                ->default(GoalPriority::Medium->value)
                ->after('status');
        });

        // Isi dari dana yang sudah tercatat: dana awal + seluruh setoran.
        DB::statement(<<<'SQL'
            UPDATE financial_goals g
            SET allocated_amount = LEAST(
                g.target_amount,
                g.initial_amount + COALESCE((
                    SELECT SUM(c.amount) FROM goal_contributions c
                    WHERE c.financial_goal_id = g.id
                ), 0)
            )
        SQL);
    }

    public function down(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->dropConstrainedForeignId('account_id');
            $table->dropColumn(['allocated_amount', 'priority']);
        });
    }
};
