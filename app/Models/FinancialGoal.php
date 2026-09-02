<?php

namespace App\Models;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\RiskProfile;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * TIDAK memakai trait SoftDeletes — lihat catatan di migrasi
 * `create_financial_goals_table`. Menghapus goal di aplikasi ini selalu
 * hard delete.
 */
class FinancialGoal extends Model
{
    use HasFactory;

    protected $fillable = [
        'user_id',
        'type',
        'name',
        'target_amount',
        'initial_amount',
        'daily_savings_target',
        'target_date',
        'estimated_return_rate',
        'estimated_inflation_rate',
        'risk_profile_override',
        'status',
    ];

    protected function casts(): array
    {
        return [
            'type' => GoalType::class,
            'status' => GoalStatus::class,
            'risk_profile_override' => RiskProfile::class,
            'target_amount' => 'decimal:2',
            'initial_amount' => 'decimal:2',
            'daily_savings_target' => 'decimal:2',
            'estimated_return_rate' => 'decimal:2',
            'estimated_inflation_rate' => 'decimal:2',
            'target_date' => 'date',
        ];
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function contributions(): HasMany
    {
        return $this->hasMany(GoalContribution::class);
    }

    public function calculations(): HasMany
    {
        return $this->hasMany(GoalCalculation::class);
    }

    /**
     * Snapshot perhitungan terakhir — dipakai menampilkan setoran bulanan
     * sesuai rencana di kartu tujuan.
     *
     * Relasi tersendiri, bukan $goal->calculations()->latest()->first() di
     * dalam perulangan: yang kedua menghasilkan satu kueri per tujuan (N+1),
     * sementara relasi ini bisa di-eager load sekaligus lewat with().
     */
    public function latestCalculation(): HasOne
    {
        return $this->hasOne(GoalCalculation::class)->latestOfMany();
    }
}
