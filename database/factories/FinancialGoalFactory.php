<?php

namespace Database\Factories;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\FinancialGoal>
 */
class FinancialGoalFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'type' => $this->faker->randomElement(GoalType::cases())->value,
            'name' => $this->faker->randomElement(['DP Rumah', 'Dana Pensiun', 'Mobil Baru', 'Dana Darurat']),
            'target_amount' => $this->faker->numberBetween(20_000_000, 500_000_000),
            'initial_amount' => $this->faker->numberBetween(0, 10_000_000),
            'target_date' => $this->faker->dateTimeBetween('+1 year', '+15 years')->format('Y-m-d'),
            'estimated_return_rate' => $this->faker->randomFloat(2, 3, 10),
            'estimated_inflation_rate' => $this->faker->randomFloat(2, 2, 5),
            'status' => GoalStatus::Active->value,
        ];
    }

    public function achieved(): static
    {
        return $this->state(fn () => ['status' => GoalStatus::Achieved->value]);
    }

    public function archived(): static
    {
        return $this->state(fn () => ['status' => GoalStatus::Archived->value]);
    }

    /** Dana darurat tidak punya tenggat — kasus target_date NULL. */
    public function withoutTargetDate(): static
    {
        return $this->state(fn () => ['target_date' => null]);
    }
}
