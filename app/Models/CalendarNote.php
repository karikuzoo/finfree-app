<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CalendarNote extends Model
{
    protected $fillable = [
        'user_id',
        'note_date',
        'body',
    ];

    protected function casts(): array
    {
        return [
            // 'date:Y-m-d' supaya nilainya sampai ke frontend sebagai
            // "2026-08-17" — format yang dipakai kalender untuk mencocokkan
            // catatan dengan selnya.
            'note_date' => 'date:Y-m-d',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
