<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Anggaran bulanan — satu baris per pengguna.
 *
 * Angkanya RENCANA, bukan kenyataan. Yang sudah terjadi ada di
 * `transactions`; lihat alasan pemisahannya di komentar migrasinya.
 */
class Budget extends Model
{
    use HasFactory;

    protected $fillable = [
        'planned_income',
        'planned_expenses',
        'monthly_reserve',
    ];

    protected function casts(): array
    {
        return [
            'planned_income' => 'decimal:2',
            'planned_expenses' => 'decimal:2',
            'monthly_reserve' => 'decimal:2',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
