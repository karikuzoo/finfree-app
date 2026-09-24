<?php

namespace Database\Factories;

use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<\App\Models\Transaction>
 */
class TransactionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'account_id' => Account::factory(),
            'type' => TransactionType::Expense->value,
            'name' => $this->faker->randomElement(['Belanja bulanan', 'Makan & kopi', 'Transportasi']),
            'amount' => $this->faker->numberBetween(50_000, 3_000_000),
            'to_account_id' => null,
            'debt_id' => null,
            'category' => 'Belanja',
            'occurred_on' => now(config('app.timezone'))->toDateString(),
        ];
    }

    public function pemasukan(float $nominal): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Income->value,
            'name' => 'Gaji bulanan',
            'category' => 'Gaji',
            'amount' => $nominal,
        ]);
    }

    public function pengeluaran(float $nominal): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Expense->value,
            'amount' => $nominal,
        ]);
    }

    public function transfer(float $nominal, Account $tujuan): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Transfer->value,
            'name' => 'Pindah dana',
            'category' => null,
            'amount' => $nominal,
            'to_account_id' => $tujuan->id,
        ]);
    }

    /** Boleh negatif — satu-satunya jenis yang nilainya bisa turun sendiri. */
    public function penyesuaian(float $nominal): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Adjustment->value,
            'name' => 'Penyesuaian nilai',
            'category' => null,
            'amount' => $nominal,
        ]);
    }

    public function pembayaran(float $nominal, Debt $utang): static
    {
        return $this->state(fn () => [
            'type' => TransactionType::Payment->value,
            'name' => 'Bayar cicilan',
            'category' => null,
            'amount' => $nominal,
            'debt_id' => $utang->id,
        ]);
    }

    public function pada(string $tanggal): static
    {
        return $this->state(fn () => ['occurred_on' => $tanggal]);
    }
}
