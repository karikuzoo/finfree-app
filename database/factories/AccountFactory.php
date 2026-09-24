<?php

namespace Database\Factories;

use App\Enums\AccountKind;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Account>
 */
class AccountFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'name' => $this->faker->randomElement(['BCA Utama', 'Kantong Impian', 'Dompet']),
            'kind' => AccountKind::Bank->value,
            'institution' => 'Bank BCA',
            'opening_balance' => $this->faker->numberBetween(1_000_000, 50_000_000),
        ];
    }

    /** Rekening likuid tanpa saldo awal — titik berangkat paling bersih untuk test. */
    public function kosong(): static
    {
        return $this->state(fn () => ['opening_balance' => 0]);
    }

    public function jenis(AccountKind $kind): static
    {
        return $this->state(fn () => [
            'kind' => $kind->value,
            'institution' => $kind->likuid() ? 'Bank BCA' : 'Sekuritas',
        ]);
    }
}
