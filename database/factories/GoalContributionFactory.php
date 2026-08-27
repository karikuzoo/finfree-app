<?php

namespace Database\Factories;

use App\Models\FinancialGoal;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\GoalContribution>
 */
class GoalContributionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'financial_goal_id' => FinancialGoal::factory(),
            'amount' => $this->faker->numberBetween(200_000, 5_000_000),
            'contributed_on' => $this->faker->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'note' => null,
        ];
    }
}
