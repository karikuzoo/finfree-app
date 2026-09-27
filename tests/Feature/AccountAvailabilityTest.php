<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * "Untuk tujuan" dan "bebas dipakai" per rekening.
 *
 * Sifat terpenting yang dijaga di sini: angka "bebas dipakai" yang
 * DITAMPILKAN adalah batas yang sama persis dengan yang DITEGAKKAN
 * LedgerGuard. Pengeluaran sebesar angka itu diterima; satu rupiah lebih
 * ditolak. Kalau keduanya berbeda, tampilan berbohong — pengguna diberi tahu
 * bebas Rp 45 juta lalu tetap ditolak saat memakai Rp 40 juta.
 */
class AccountAvailabilityTest extends TestCase
{
    use RefreshDatabase;

    private function rekening(User $user, float $awal = 50_000_000): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['name' => 'BCA - Utama', 'opening_balance' => $awal]);
    }

    private function tujuan(User $user, Account $rekening, float $dana, array $ubah = []): FinancialGoal
    {
        return $user->goals()->create(array_merge([
            'type' => GoalType::Custom->value,
            'name' => 'Beli Monas',
            'target_amount' => 100_000_000,
            'initial_amount' => 0,
            'allocated_amount' => $dana,
            'account_id' => $rekening->id,
            'target_date' => now(config('app.timezone'))->addMonths(24)->toDateString(),
            'estimated_return_rate' => 6,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::Medium->value,
        ], $ubah));
    }

    private function pengeluaran(Account $rekening, float $nominal): array
    {
        return [
            'account_id' => $rekening->id,
            'type' => 'expense',
            'name' => 'Belanja',
            'amount' => $nominal,
            'occurred_on' => now(config('app.timezone'))->toDateString(),
        ];
    }

    public function test_kartu_rekening_memuat_dana_tujuan_dan_sisa_bebas(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $this->tujuan($user, $rekening, 5_000_000);
        $this->tujuan($user, $rekening, 3_000_000, ['name' => 'Dana darurat']);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Index')
                ->where('accounts.0.balance', 50_000_000)
                ->where('accounts.0.allocated', 8_000_000)
                ->where('accounts.0.free', 42_000_000)
                // Diurutkan dari dana terbesar.
                ->where('accounts.0.allocated_goals.0.name', 'Beli Monas')
                ->where('accounts.0.allocated_goals.1.name', 'Dana darurat'));
    }

    /** Menyisihkan menandai saldo — saldo penuh tidak berubah, sisa bebasnya yang turun. */
    public function test_menyisihkan_tidak_mengubah_saldo_hanya_sisa_bebas(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $goal = $this->tujuan($user, $rekening, 0);

        $this->actingAs($user)
            ->post(route('goals.set-aside', $goal), ['amount' => 5_000_000])
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.balance', 50_000_000)
                ->where('accounts.0.free', 45_000_000));
    }

    public function test_rekening_tanpa_tujuan_seluruhnya_bebas(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 7_500_000);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.allocated', 0)
                ->where('accounts.0.allocated_goals', [])
                ->where('accounts.0.free', 7_500_000));
    }

    /** Inti berkas ini: angka yang ditampilkan = batas yang ditegakkan. */
    public function test_pengeluaran_sebesar_sisa_bebas_diterima(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $this->tujuan($user, $rekening, 5_000_000);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->pengeluaran($rekening, 45_000_000))
            ->assertSessionHasNoErrors();
    }

    public function test_pengeluaran_satu_rupiah_melebihi_sisa_bebas_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $this->tujuan($user, $rekening, 5_000_000);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->pengeluaran($rekening, 45_000_001))
            ->assertSessionHasErrors('amount');
    }

    /**
     * Tujuan yang sudah tercapai atau diarsipkan tetap menandai saldonya —
     * LedgerGuard menghitungnya, jadi tampilan juga harus menghitungnya.
     */
    public function test_tujuan_tercapai_dan_diarsipkan_tetap_menandai_saldo(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $this->tujuan($user, $rekening, 4_000_000, ['status' => GoalStatus::Achieved->value]);
        $this->tujuan($user, $rekening, 6_000_000, [
            'name' => 'Lama',
            'status' => GoalStatus::Archived->value,
        ]);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('accounts.0.free', 40_000_000));

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->pengeluaran($rekening, 40_000_001))
            ->assertSessionHasErrors('amount');
    }

    /** Form yang mengambil uang menerima angka yang sama, supaya batasnya terlihat sebelum menyimpan. */
    public function test_form_transaksi_dan_pembayaran_utang_menerima_sisa_bebas(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $this->tujuan($user, $rekening, 5_000_000);

        foreach (['transactions.index', 'debts.index'] as $halaman) {
            $this->actingAs($user)
                ->get(route($halaman))
                ->assertInertia(fn (Assert $page) => $page
                    ->where('accounts.0.free', 45_000_000)
                    ->where('accounts.0.allocated_goals.0.name', 'Beli Monas'));
        }
    }

    public function test_dana_tujuan_orang_lain_tidak_ikut_terhitung(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);

        $lain = User::factory()->create();
        $this->tujuan($lain, $this->rekening($lain), 9_000_000);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page->where('accounts.0.free', 50_000_000));
    }
}
