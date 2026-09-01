<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

class Reminder extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'title',
        'remind_at',
        'completed_at',
    ];

    protected function casts(): array
    {
        return [
            'remind_at' => 'datetime',
            'completed_at' => 'datetime',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /** Pengingat pada satu hari kalender, tanpa memandang jamnya. */
    public function scopeOnDate(Builder $query, Carbon $date): Builder
    {
        return $query->whereBetween('remind_at', [
            $date->copy()->startOfDay(),
            $date->copy()->endOfDay(),
        ]);
    }

    public function scopeInMonth(Builder $query, Carbon $month): Builder
    {
        return $query->whereBetween('remind_at', [
            $month->copy()->startOfMonth()->startOfDay(),
            $month->copy()->endOfMonth()->endOfDay(),
        ]);
    }
}
