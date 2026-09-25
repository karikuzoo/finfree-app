<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Isi pesan penolakan LedgerGuard.
 *
 * Test lain sudah memastikan penolakannya TERJADI; berkas ini memastikan
 * pesannya BERGUNA. Keduanya hal berbeda, dan yang kedua mudah membusuk tanpa
 * ketahuan karena test biasanya cukup memeriksa ada-tidaknya galat.
 *
 * Kasus nyatanya: pesan lama berbunyi "Saldo BCA sudah ditandai untuk target
 * lain. Kurangi alokasi target terlebih dahulu." Padahal target lain hanya
 * memakai 56 juta dari saldo 227 juta — yang diminta memang jauh melampaui
 * isi rekening. Pengguna diarahkan mengurangi alokasi yang bukan penyebabnya.
 */
class LedgerGuardMessageTest extends TestCase
{
    use RefreshDatabase;

    private function pesan(\Closure $aksi): string
    {
        try {
            $aksi();
        } catch (\Illuminate\Validation\ValidationException $e) {
            return implode(' ', $e->errors()['amount'] ?? $e->errors()['allocated_amount'] ?? ['(kolom lain)']);
        }

        $this->fail('Tidak ada penolakan sama sekali.');
    }

    private function rekening(User $user, float $awal): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['name' => 'BCA', 'opening_balance' => $awal]);
    }

    /** Kekurangannya disebut — itu angka yang menentukan apa yang harus diubah. */
    public function test_pesan_saldo_kurang_menyebut_selisihnya(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 1_000_000);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $rekening->id,
                'type' => TransactionType::Expense->value,
                'name' => 'Belanja',
                'amount' => 1_500_000,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasErrors([
                'amount' => 'Saldo tidak mencukupi: BCA (kurang Rp 500.000).',
            ]);
    }

    public function test_pesan_pembayaran_berlebih_menyebut_kelebihannya(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 20_000_000);
        $utang = Debt::factory()->for($user)->create([
            'name' => 'Cicilan motor', 'principal' => 1_000_000,
        ]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $rekening->id,
                'type' => TransactionType::Payment->value,
                'name' => 'Bayar',
                'amount' => 1_200_000,
                'debt_id' => $utang->id,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasErrors([
                'amount' => 'Pembayaran melebihi sisa pokok: Cicilan motor (lebih Rp 200.000).',
            ]);
    }

    /**
     * Pesan alokasi menyebut KEDUA angkanya dan tidak menyuruh apa-apa.
     * "Kurangi alokasi target lain" hanya benar bila target lain memang
     * memakan saldonya — pada permintaan yang melampaui isi rekening, saran
     * itu mengirim pengguna ke tempat yang salah.
     */
    public function test_pesan_alokasi_menyebut_total_dan_saldonya(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);

        $goal = $user->goals()->create([
            'account_id' => $rekening->id,
            'type' => GoalType::Custom->value,
            'name' => 'DP Rumah',
            'target_amount' => 500_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 5,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::High->value,
        ]);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => $rekening->id,
                'allocated_amount' => 25_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasErrors([
                'allocated_amount' => 'Total dana untuk target di BCA jadi Rp 25.000.000, melebihi saldonya yang Rp 10.000.000.',
            ]);
    }

    /** Beberapa rekening minus sekaligus disebut satu per satu, bukan digabung kabur. */
    public function test_beberapa_rekening_minus_disebut_masing_masing(): void
    {
        $user = User::factory()->create();
        $satu = Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['name' => 'BCA', 'opening_balance' => 1_000_000]);
        $dua = Account::factory()->for($user)->jenis(AccountKind::Cash)
            ->create(['name' => 'Dompet', 'opening_balance' => 500_000]);

        // Dibuat minus lewat model, melewati controller — lalu dipanggil
        // langsung, supaya keadaan dua rekening minus sekaligus bisa diuji.
        Transaction::factory()->for($user)->for($satu)->pengeluaran(1_200_000)->create();
        Transaction::factory()->for($user)->for($dua)->pengeluaran(700_000)->create();

        $pesan = $this->pesan(
            fn () => app(\App\Services\LedgerGuard::class)->assertConsistent($user),
        );

        $this->assertStringContainsString('BCA (kurang Rp 200.000)', $pesan);
        $this->assertStringContainsString('Dompet (kurang Rp 200.000)', $pesan);
    }
}
