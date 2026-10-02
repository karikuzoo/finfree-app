<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/** Satu baris riwayat kalkulasi (PRD FR-45). Lihat CalculationHistoryService. */
class CalculationHistory extends Model
{
    protected $fillable = [
        'user_id',
        'calculator',
        'input',
        'input_hash',
        'summary',
    ];

    protected function casts(): array
    {
        return [
            'input' => 'array',
            'summary' => 'array',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
