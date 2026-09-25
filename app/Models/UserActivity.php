<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class UserActivity extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'financial_goal_id',
        'type',
        'goal_name',
        'amount',
    ];

    protected $casts = [
        'amount' => 'decimal:2',
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** NULL bila tujuannya sudah dihapus — `goal_name` tetap menyimpan namanya. */
    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }
}
