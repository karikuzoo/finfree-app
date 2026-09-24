<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * Utang atau cicilan.
 *
 * Sisa pokok tidak disimpan; lihat `remaining()`.
 */
class Debt extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'principal',
        'monthly_principal',
        'due_on',
    ];

    protected function casts(): array
    {
        return [
            'principal' => 'decimal:2',
            'monthly_principal' => 'decimal:2',
            'due_on' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** Pembayaran pokok terhadap utang ini. */
    public function payments(): HasMany
    {
        return $this->hasMany(Transaction::class);
    }

    /**
     * Sisa pokok = pokok awal − seluruh pembayaran.
     *
     * Memanggil ini di dalam perulangan memicu satu kueri per utang; muat
     * relasi `payments` lebih dulu bila memproses banyak baris sekaligus.
     */
    public function remaining(): float
    {
        return round(
            (float) $this->principal - (float) $this->payments()->sum('amount'),
            2,
        );
    }

    public function isSettled(): bool
    {
        return $this->remaining() <= 0;
    }
}
