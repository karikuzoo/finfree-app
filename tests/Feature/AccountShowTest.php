<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Database\Factories\TransactionFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Halaman detail rekening: ringkasan dan mutasi dengan saldo berjalan.
 */
class AccountShowTest extends TestCase
{
    use RefreshDatabase;

    private User $user;

    private Account $bank;

    protected function setUp(): void
    {
        parent::setUp();

        $this->user = User::factory()->create();
        $this->bank = Account::factory()->for($this->user)->jenis(AccountKind::Bank)
            ->create(['name' => 'BCA', 'opening_balance' => 10_000_000]);
    }

    private function catat(): TransactionFactory
    {
        return Transaction::factory()->for($this->user)->for($this->bank)->state(['category' => null]);
    }

    /** Mutasi semua jenis, termasuk transfer MASUK dari rekening lain. */
    private function isiRiwayat(): void
    {
        $lain = Account::factory()->for($this->user)->jenis(AccountKind::Cash)
            ->create(['name' => 'Dompet', 'opening_balance' => 5_000_000]);
        $utang = Debt::factory()->for($this->user)->create(['principal' => 20_000_000]);

        $this->catat()->pemasukan(8_000_000)->pada('2026-09-01')->create(['name' => 'Gaji']);
        $this->catat()->pengeluaran(1_500_000)->pada('2026-09-03')->create(['name' => 'Belanja']);
        $this->catat()->transfer(2_000_000, $lain)->pada('2026-09-05')->create(['name' => 'Ke dompet']);
        Transaction::factory()->for($this->user)->for($lain)->transfer(500_000, $this->bank)
            ->pada('2026-09-06')->create(['name' => 'Setor tunai']);
        $this->catat()->pembayaran(1_000_000, $utang)->pada('2026-09-10')->create(['name' => 'Cicilan']);
        // Dua transaksi di tanggal yang sama: urutannya ditentukan id.
        $this->catat()->pengeluaran(250_000)->pada('2026-09-10')->create(['name' => 'Kopi']);
        // Transaksi rekening LAIN yang tidak menyentuh BCA — tidak boleh ikut.
        Transaction::factory()->for($this->user)->for($lain)->pengeluaran(100_000)
            ->pada('2026-09-07')->create(['name' => 'Parkir']);
    }

    public function test_detail_menampilkan_ringkasan_yang_sama_dengan_kartu(): void
    {
        $this->isiRiwayat();
        $saldo = app(AccountBalanceService::class)->forUser($this->user)[$this->bank->id];

        $this->actingAs($this->user)
            ->get(route('accounts.show', $this->bank))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Account/Show')
                ->where('account.id', $this->bank->id)
                ->where('account.name', 'BCA')
                // JSON mengirim 13750000, bukan 13750000.0 — bandingkan nilainya.
                ->where('account.balance', fn ($v) => (float) $v === $saldo)
                ->where('account.opening_balance', 10_000_000)
                ->has('kinds'));
    }

    /**
     * Saldo sesudah baris teratas = saldo di kartu, dan tiap baris berselisih
     * tepat sebesar mutasinya dengan baris di bawahnya. Gagal bila CASE di
     * AccountController::mutasi() tidak lagi sejalan dengan
     * TransactionType::menambahSaldo() atau AccountBalanceService.
     */
    public function test_saldo_berjalan_cocok_dengan_saldo_kartu(): void
    {
        $this->isiRiwayat();
        $saldo = app(AccountBalanceService::class)->forUser($this->user)[$this->bank->id];

        $mutasi = $this->actingAs($this->user)
            ->get(route('accounts.show', $this->bank))
            ->viewData('page')['props']['mutations']['data'];

        $this->assertSame(
            ['Kopi', 'Cicilan', 'Setor tunai', 'Ke dompet', 'Belanja', 'Gaji'],
            array_column($mutasi, 'name'),
        );
        $this->assertEquals($saldo, $mutasi[0]['balance_after']);
        $this->assertEquals(
            [-250_000, -1_000_000, 500_000, -2_000_000, -1_500_000, 8_000_000],
            array_column($mutasi, 'amount'),
        );

        for ($i = 0; $i < count($mutasi) - 1; $i++) {
            $this->assertEquals(
                $mutasi[$i + 1]['balance_after'] + $mutasi[$i]['amount'],
                $mutasi[$i]['balance_after'],
            );
        }

        // Baris tertua = saldo awal + mutasinya sendiri.
        $this->assertEquals(10_000_000 + 8_000_000, end($mutasi)['balance_after']);
    }

    public function test_transfer_menyebut_rekening_di_seberangnya(): void
    {
        $this->isiRiwayat();

        $mutasi = collect($this->actingAs($this->user)
            ->get(route('accounts.show', $this->bank))
            ->viewData('page')['props']['mutations']['data'])->keyBy('name');

        $this->assertSame('Dompet', $mutasi['Ke dompet']['counterpart']);
        $this->assertSame('Transfer', $mutasi['Ke dompet']['type_label']);
        $this->assertSame('Dompet', $mutasi['Setor tunai']['counterpart']);
        $this->assertSame('Transfer masuk', $mutasi['Setor tunai']['type_label']);
    }

    public function test_penyesuaian_nilai_negatif_mengurangi_saldo(): void
    {
        $saham = Account::factory()->for($this->user)->jenis(AccountKind::Stock)
            ->create(['opening_balance' => 20_000_000]);
        Transaction::factory()->for($this->user)->for($saham)->penyesuaian(5_000_000)->pada('2026-09-01')->create();
        Transaction::factory()->for($this->user)->for($saham)->penyesuaian(-2_000_000)->pada('2026-09-15')->create();

        $mutasi = $this->actingAs($this->user)
            ->get(route('accounts.show', $saham))
            ->assertInertia(fn (Assert $page) => $page->where('account.last_valuation', '2026-09-15'))
            ->viewData('page')['props']['mutations']['data'];

        $this->assertEquals([-2_000_000, 5_000_000], array_column($mutasi, 'amount'));
        $this->assertEquals(23_000_000, $mutasi[0]['balance_after']);
    }

    /** Saldo berjalan dihitung atas SELURUH riwayat, bukan hanya halaman yang tampil. */
    public function test_saldo_berjalan_tetap_benar_di_halaman_berikutnya(): void
    {
        for ($i = 1; $i <= 25; $i++) {
            $this->catat()->pemasukan(100_000)->pada(sprintf('2026-08-%02d', $i))->create();
        }

        $halaman2 = $this->actingAs($this->user)
            ->get(route('accounts.show', [$this->bank, 'page' => 2]))
            ->viewData('page')['props']['mutations'];

        $this->assertCount(5, $halaman2['data']);
        // Baris tertua: saldo awal + satu pemasukan.
        $this->assertEquals(10_100_000, end($halaman2['data'])['balance_after']);
        $this->assertEquals(10_500_000, $halaman2['data'][0]['balance_after']);
    }

    public function test_rekening_tanpa_transaksi_mutasinya_kosong(): void
    {
        $this->actingAs($this->user)
            ->get(route('accounts.show', $this->bank))
            ->assertInertia(fn (Assert $page) => $page
                ->has('mutations.data', 0)
                ->where('account.balance', 10_000_000));
    }

    public function test_dana_tujuan_dirinci_per_tujuan(): void
    {
        $this->user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => 'DP Rumah',
            'target_amount' => 100_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 3_000_000,
            'account_id' => $this->bank->id,
            'target_date' => now()->addYears(3)->toDateString(),
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);

        $this->actingAs($this->user)
            ->get(route('accounts.show', $this->bank))
            ->assertInertia(fn (Assert $page) => $page
                ->where('account.allocated', 3_000_000)
                ->where('account.free', 7_000_000)
                ->where('account.allocated_goals.0.name', 'DP Rumah'));
    }

    public function test_rekening_orang_lain_tidak_bisa_dibuka(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('accounts.show', $this->bank))
            ->assertForbidden();
    }
}
