<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invarian yang ditegakkan saat MENULIS transaksi (PRD FR-64..FR-69).
 *
 * Pelengkap AccountBalanceTest, yang menguji aritmetikanya. Di sini yang
 * diuji adalah penolakannya: masukan yang akan merusak buku besar harus
 * ditolak sebelum tersimpan, dengan pesan yang bisa dibaca pengguna — bukan
 * tersimpan lalu menghasilkan angka mustahil, dan bukan pula halaman 500.
 *
 * Diturunkan dari test invarian prototipe Arus (`tests/finance.mjs`).
 */
class TransactionTest extends TestCase
{
    use RefreshDatabase;

    private function rekening(User $user, float $awal = 1_000_000, ?AccountKind $jenis = null): Account
    {
        return Account::factory()->for($user)
            ->jenis($jenis ?? AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    /** @return array<string, mixed> */
    private function isian(Account $rekening, array $ubah = []): array
    {
        return array_merge([
            'account_id' => $rekening->id,
            'type' => TransactionType::Expense->value,
            'name' => 'Belanja bulanan',
            'amount' => 100_000,
            'category' => 'Belanja',
            'occurred_on' => now(config('app.timezone'))->toDateString(),
        ], $ubah);
    }

    private function saldo(User $user, Account $rekening): float
    {
        return app(AccountBalanceService::class)->forUser($user)[$rekening->id];
    }

    // ── Pencatatan yang benar ───────────────────────────────────────────

    public function test_transaksi_tersimpan_dan_mengubah_saldo(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, ['amount' => 250_000]))
            ->assertSessionHasNoErrors();

        $this->assertSame(750_000.0, $this->saldo($user, $rekening));
    }

    public function test_menghapus_transaksi_membalik_pengaruhnya(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $transaksi = Transaction::factory()->for($user)->for($rekening)
            ->pengeluaran(300_000)->create();

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $transaksi))
            ->assertSessionHasNoErrors();

        $this->assertSame(1_000_000.0, $this->saldo($user, $rekening));
        $this->assertDatabaseMissing('transactions', ['id' => $transaksi->id]);
    }

    // ── Saldo tidak boleh minus ─────────────────────────────────────────

    /**
     * Ini invarian terpenting di berkas ini: aplikasi mencatat uang yang
     * benar-benar ada, jadi saldo minus selalu berarti pencatatannya keliru.
     */
    public function test_pengeluaran_melebihi_saldo_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 1_000_000);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, ['amount' => 1_000_001]))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transactions', 0);
        $this->assertSame(1_000_000.0, $this->saldo($user, $rekening));
    }

    /**
     * Penolakan harus MEMBATALKAN penulisan sepenuhnya. Baris yang terlanjur
     * tersimpan lalu ditolak akan meninggalkan saldo minus yang tidak pernah
     * terlihat oleh siapa pun.
     */
    public function test_penolakan_tidak_meninggalkan_baris_setengah_jadi(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 500_000);

        Transaction::factory()->for($user)->for($rekening)->pengeluaran(400_000)->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, ['amount' => 200_000]))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transactions', 1);
        $this->assertSame(100_000.0, $this->saldo($user, $rekening));
    }

    /** Menyunting nominal ke atas juga bisa membuat saldo minus. */
    public function test_menyunting_nominal_hingga_saldo_minus_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 500_000);
        $transaksi = Transaction::factory()->for($user)->for($rekening)
            ->pengeluaran(100_000)->create();

        $this->actingAs($user)
            ->patch(route('transactions.update', $transaksi), $this->isian($rekening, [
                'amount' => 600_000,
            ]))
            ->assertSessionHasErrors('amount');

        $this->assertSame(100_000.0, (float) $transaksi->fresh()->amount);
    }

    /** Menghapus pemasukan bisa membuat pengeluaran setelahnya tak tertutup. */
    public function test_menghapus_pemasukan_yang_membuat_saldo_minus_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 0);

        $gaji = Transaction::factory()->for($user)->for($rekening)->pemasukan(5_000_000)->create();
        Transaction::factory()->for($user)->for($rekening)->pengeluaran(3_000_000)->create();

        $this->actingAs($user)
            ->delete(route('transactions.destroy', $gaji))
            ->assertSessionHasErrors('transaction');

        $this->assertDatabaseHas('transactions', ['id' => $gaji->id]);
    }

    // ── Transfer ────────────────────────────────────────────────────────

    public function test_transfer_ke_rekening_sendiri_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'type' => TransactionType::Transfer->value,
                'to_account_id' => $rekening->id,
                'category' => null,
            ]))
            ->assertSessionHasErrors('to_account_id');
    }

    public function test_transfer_wajib_punya_rekening_tujuan(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'type' => TransactionType::Transfer->value,
                'category' => null,
            ]))
            ->assertSessionHasErrors('to_account_id');
    }

    /**
     * Kolom sisa dari pilihan sebelumnya harus ditolak, bukan diabaikan.
     * Pengeluaran yang diam-diam menyimpan `debt_id` akan terhitung sebagai
     * pembayaran utang di tempat lain.
     */
    public function test_rekening_tujuan_pada_jenis_selain_transfer_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $lain = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'to_account_id' => $lain->id,
            ]))
            ->assertSessionHasErrors('to_account_id');
    }

    public function test_utang_pada_jenis_selain_pembayaran_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $utang = Debt::factory()->for($user)->create();

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'debt_id' => $utang->id,
            ]))
            ->assertSessionHasErrors('debt_id');
    }

    // ── Nominal & tanggal ───────────────────────────────────────────────

    public function test_nominal_negatif_ditolak_kecuali_penyesuaian(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, ['amount' => -50_000]))
            ->assertSessionHasErrors('amount');

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'type' => TransactionType::Adjustment->value,
                'name' => 'Penyesuaian nilai',
                'amount' => -50_000,
                'category' => null,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(950_000.0, $this->saldo($user, $rekening));
    }

    public function test_nominal_nol_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, ['amount' => 0]))
            ->assertSessionHasErrors('amount');
    }

    public function test_tanggal_di_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'occurred_on' => now(config('app.timezone'))->addDay()->toDateString(),
            ]))
            ->assertSessionHasErrors('occurred_on');
    }

    // ── Pembayaran utang ────────────────────────────────────────────────

    public function test_pembayaran_melebihi_sisa_pokok_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 20_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 1_000_000]);

        $this->actingAs($user)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'type' => TransactionType::Payment->value,
                'name' => 'Bayar cicilan',
                'amount' => 1_500_000,
                'debt_id' => $utang->id,
                'category' => null,
            ]))
            ->assertSessionHasErrors('amount');

        $this->assertDatabaseCount('transactions', 0);
    }

    // ── Pemisahan antar pengguna ────────────────────────────────────────

    /**
     * Memakai rekening orang lain hanya dengan menebak ID-nya adalah bentuk
     * kebocoran yang paling mudah terlewat (CONTRIBUTING §7).
     */
    public function test_tidak_bisa_mencatat_ke_rekening_orang_lain(): void
    {
        $orangLain = User::factory()->create();
        $rekeningOrangLain = $this->rekening($orangLain);

        $saya = User::factory()->create();
        $this->rekening($saya);

        $this->actingAs($saya)
            ->post(route('transactions.store'), $this->isian($rekeningOrangLain))
            ->assertSessionHasErrors('account_id');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tidak_bisa_membayar_utang_orang_lain(): void
    {
        $orangLain = User::factory()->create();
        $utangOrangLain = Debt::factory()->for($orangLain)->create();

        $saya = User::factory()->create();
        $rekening = $this->rekening($saya, 20_000_000);

        $this->actingAs($saya)
            ->post(route('transactions.store'), $this->isian($rekening, [
                'type' => TransactionType::Payment->value,
                'amount' => 100_000,
                'debt_id' => $utangOrangLain->id,
                'category' => null,
            ]))
            ->assertSessionHasErrors('debt_id');
    }

    public function test_tidak_bisa_menyunting_atau_menghapus_transaksi_orang_lain(): void
    {
        $orangLain = User::factory()->create();
        $rekening = $this->rekening($orangLain);
        $transaksi = Transaction::factory()->for($orangLain)->for($rekening)
            ->pengeluaran(100_000)->create();

        $saya = User::factory()->create();

        $this->actingAs($saya)
            ->patch(route('transactions.update', $transaksi), $this->isian($rekening))
            ->assertForbidden();

        $this->actingAs($saya)
            ->delete(route('transactions.destroy', $transaksi))
            ->assertForbidden();

        $this->assertDatabaseHas('transactions', ['id' => $transaksi->id]);
    }

    public function test_tamu_tidak_bisa_mencatat_transaksi(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        $this->post(route('transactions.store'), $this->isian($rekening))
            ->assertRedirect(route('login'));
    }

    // ── Daftar ──────────────────────────────────────────────────────────

    public function test_daftar_hanya_memuat_bulan_yang_diminta_dan_milik_sendiri(): void
    {
        $orangLain = User::factory()->create();
        Transaction::factory()->for($orangLain)
            ->for($this->rekening($orangLain))->pengeluaran(100_000)->create();

        $saya = User::factory()->create();
        $rekening = $this->rekening($saya, 50_000_000);
        $bulanLalu = now(config('app.timezone'))->subMonthNoOverflow();

        Transaction::factory()->for($saya)->for($rekening)->pengeluaran(100_000)->create();
        Transaction::factory()->for($saya)->for($rekening)->pengeluaran(200_000)
            ->pada($bulanLalu->toDateString())->create();

        $this->actingAs($saya)
            ->get(route('transactions.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Transaction/Index')
                ->has('transactions.data', 1),
            );

        $this->actingAs($saya)
            ->get(route('transactions.index', ['bulan' => $bulanLalu->format('Y-m')]))
            ->assertInertia(fn ($page) => $page->has('transactions.data', 1));
    }
}
