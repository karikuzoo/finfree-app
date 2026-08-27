<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class GoalCalculation extends Model
{
    use HasFactory;

    // Cuma created_at — baris ini snapshot historis yang tidak pernah diedit.
    const UPDATED_AT = null;

    protected $fillable = [
        'financial_goal_id',
        'monthly_contribution_required',
        'total_contribution_projection',
        'total_investment_growth_projection',
        'calculation_snapshot',
        'formula_version',
    ];

    protected function casts(): array
    {
        return [
            'monthly_contribution_required' => 'decimal:2',
            'total_contribution_projection' => 'decimal:2',
            'total_investment_growth_projection' => 'decimal:2',
            'calculation_snapshot' => 'array',
            'created_at' => 'datetime',
        ];
    }

    public function financialGoal(): BelongsTo
    {
        return $this->belongsTo(FinancialGoal::class);
    }

    /**
     * Bangun atribut baris ini langsung dari array hasil
     * GoalCalculatorService::calculateMonthlyContribution() — supaya
     * bentuk outputnya tidak ditulis ulang di dua tempat.
     */
    public static function fromCalculatorResult(array $result): array
    {
        return [
            'monthly_contribution_required' => $result['monthly_contribution_required'],
            'total_contribution_projection' => $result['total_contribution_projection'],
            'total_investment_growth_projection' => $result['total_investment_growth_projection'],
            'calculation_snapshot' => $result,
            'formula_version' => $result['formula_version'],
        ];
    }
}
