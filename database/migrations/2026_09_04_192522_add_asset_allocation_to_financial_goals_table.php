<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->json('asset_allocation')->nullable()->after('daily_savings_target');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::table('financial_goals', function (Blueprint $table) {
            $table->dropColumn('asset_allocation');
        });
    }
};
