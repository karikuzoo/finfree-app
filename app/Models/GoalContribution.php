<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * **TIDAK AKTIF.** Pencatatan setoran harian sudah dipensiunkan dan
 * digantikan model alokasi: dana tujuan kini DITANDAI dari saldo sebuah
 * rekening (`financial_goals.allocated_amount`), bukan disetor sedikit demi
 * sedikit ke tujuan itu sendiri.
 *
 * Tidak ada lagi jalan untuk membuat baris baru di sini — controller, form,
 * dan route-nya sudah dihapus. Tabelnya SENGAJA dipertahankan supaya riwayat
 * yang terlanjur tercatat tidak ikut hilang; nilainya sudah dipindahkan ke
 * `allocated_amount` oleh migrasi add_allocation_to_financial_goals_table.
 *
 * Model ini hanya dipakai saat menghapus tujuan, agar baris lamanya ikut
 * terbawa. Bila suatu saat riwayat itu sudah pasti tidak dibutuhkan, tabelnya
 * bisa di-drop lewat migrasi tersendiri — dan berkas ini ikut dihapus.
 */
class GoalContribution extends Model
{
    use HasFactory;

    protected $fillable = [
        'financial_goal_id',
        'amount',
        'contributed_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'contributed_on' => 'date',
        ];
    }

    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }
}
