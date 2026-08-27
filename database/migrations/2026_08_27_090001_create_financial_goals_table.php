<?php

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\RiskProfile;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * Tujuan finansial tersimpan (PRD FR-9, "Tujuan" — beda dari kalkulator
 * utilitas sekali-pakai di GoalCalculatorController).
 *
 * **Tanpa `deleted_at`**, sama seperti tabel `users` — soft delete
 * bertentangan dengan FR-37 (penghapusan permanen). Menghapus goal di
 * aplikasi ini selalu hard delete lewat `cascadeOnDelete()` dari `users`.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_goals', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->constrained()->cascadeOnDelete();

            // Nilai enum berasal dari App\Enums\* — sama seperti
            // risk_profile di tabel users, bukan daftar string yang
            // ditulis ulang di sini.
            $table->enum('type', GoalType::values());
            $table->string('name');

            $table->decimal('target_amount', 18, 2);
            $table->decimal('initial_amount', 18, 2)->default(0);

            // NULL untuk dana darurat — tidak punya tenggat waktu tertentu,
            // beda dari tujuan seperti DP rumah yang punya tanggal target.
            $table->date('target_date')->nullable();

            $table->decimal('estimated_return_rate', 5, 2);
            $table->decimal('estimated_inflation_rate', 5, 2)->default(0);

            // Override profil risiko akun untuk goal spesifik (FR-24) —
            // misalnya akun agresif tapi dana darurat tetap konservatif.
            $table->enum('risk_profile_override', RiskProfile::values())->nullable();
            $table->enum('status', GoalStatus::values())->default(GoalStatus::Active->value);

            $table->timestamps();

            $table->index(['user_id', 'status']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_goals');
    }
};
