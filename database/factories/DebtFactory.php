<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Debt>
 */
class DebtFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => 'Cicilan motor',
            'principal' => 12_000_000,
            'monthly_principal' => 1_000_000,
            'due_on' => now(config('app.timezone'))->addYear()->toDateString(),
        ];
    }
}
