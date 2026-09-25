<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Pengelolaan rekening & aset (PRD FR-63).
 *
 * Berkas ini lahir karena ketiadaannya sempat meloloskan bug: menambah
 * rekening baru melempar error 500, sebab aturan "jenis dikunci bila ada
 * riwayat" membaca properti pada rekening yang belum ada. Test paling dasar —
 * menyimpan sebuah rekening — belum pernah ada. Sekarang ada.
 */
class AccountTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, mixed> */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'name' => 'BCA Utama',
            'kind' => AccountKind::Bank->value,
            'institution' => 'Bank BCA',
            'opening_balance' => 5_000_000,
        ], $ubah);
    }

    // ── Menambah ────────────────────────────────────────────────────────

    /** Jalur paling dasar, dan justru inilah yang dulu melempar 500. */
    public function test_rekening_baru_tersimpan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('accounts', [
            'user_id' => $user->id,
            'name' => 'BCA Utama',
            'kind' => AccountKind::Bank->value,
        ]);
    }

    public function test_setiap_jenis_rekening_bisa_dibuat(): void
    {
        $user = User::factory()->create();

        foreach (AccountKind::cases() as $jenis) {
            $this->actingAs($user)
                ->post(route('accounts.store'), $this->isian([
                    'name' => $jenis->label(),
                    'kind' => $jenis->value,
                ]))
                ->assertSessionHasNoErrors();
        }

        $this->assertDatabaseCount('accounts', count(AccountKind::cases()));
    }

    public function test_lembaga_boleh_kosong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['institution' => null]))
            ->assertSessionHasNoErrors();
    }

    public function test_saldo_awal_nol_diterima_tetapi_negatif_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['opening_balance' => 0]))
            ->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['opening_balance' => -1]))
            ->assertSessionHasErrors('opening_balance');
    }

    public function test_menolak_masukan_yang_tidak_sah(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['name' => '']))
            ->assertSessionHasErrors('name');

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['kind' => 'kripto']))
            ->assertSessionHasErrors('kind');
    }

    // ── Mengubah ────────────────────────────────────────────────────────

    public function test_rekening_bisa_diubah(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian([
                'name' => 'BCA Tabungan',
                'opening_balance' => 7_500_000,
            ]))
            ->assertSessionHasNoErrors();

        $rekening->refresh();

        $this->assertSame('BCA Tabungan', $rekening->name);
        $this->assertSame(7_500_000.0, (float) $rekening->opening_balance);
    }

    public function test_jenis_bisa_diubah_selama_belum_ada_transaksi(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->jenis(AccountKind::Bank)->create();

        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian([
                'kind' => AccountKind::Cash->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame(AccountKind::Cash, $rekening->fresh()->kind);
    }

    /**
     * Mengubah rekening bank menjadi saham membuat dana target yang sudah
     * ditandai di sana mendadak berada di instrumen yang tidak boleh
     * menampungnya — nilainya bisa turun dan targetnya meleset diam-diam.
     */
    public function test_jenis_dikunci_setelah_ada_transaksi(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['opening_balance' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)->pengeluaran(100_000)->create();

        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian([
                'kind' => AccountKind::Stock->value,
            ]))
            ->assertSessionHasErrors('kind');

        $this->assertSame(AccountKind::Bank, $rekening->fresh()->kind);
    }

    /** Mengirim jenis yang SAMA tidak boleh ikut tertolak oleh penguncian itu. */
    public function test_rekening_bertransaksi_tetap_bisa_diganti_namanya(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['opening_balance' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)->pengeluaran(100_000)->create();

        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian([
                'name' => 'Nama baru',
                'kind' => AccountKind::Bank->value,
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama baru', $rekening->fresh()->name);
    }

    // ── Menghapus ───────────────────────────────────────────────────────

    public function test_rekening_tanpa_riwayat_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $rekening))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('accounts', ['id' => $rekening->id]);
    }

    /**
     * Ditolak dengan pesan, bukan dibiarkan jatuh ke pelanggaran foreign key
     * yang muncul sebagai halaman 500.
     */
    public function test_rekening_dengan_transaksi_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $rekening = Account::factory()->for($user)->create(['opening_balance' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)->pengeluaran(100_000)->create();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $rekening))
            ->assertSessionHasErrors('account');

        $this->assertDatabaseHas('accounts', ['id' => $rekening->id]);
    }

    /** Termasuk bila ia hanya menjadi TUJUAN transfer, bukan asalnya. */
    public function test_rekening_yang_hanya_menerima_transfer_juga_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $asal = Account::factory()->for($user)->create(['opening_balance' => 5_000_000]);
        $tujuan = Account::factory()->for($user)->create(['opening_balance' => 0]);

        Transaction::factory()->for($user)->for($asal)->transfer(1_000_000, $tujuan)->create();

        $this->actingAs($user)
            ->delete(route('accounts.destroy', $tujuan))
            ->assertSessionHasErrors('account');
    }

    // ── Daftar & otorisasi ──────────────────────────────────────────────

    public function test_daftar_memuat_saldo_dan_komposisi_milik_sendiri(): void
    {
        $orangLain = User::factory()->create();
        Account::factory()->for($orangLain)->create(['opening_balance' => 99_000_000]);

        $saya = User::factory()->create();
        $rekening = Account::factory()->for($saya)->create(['opening_balance' => 5_000_000]);
        Transaction::factory()->for($saya)->for($rekening)->pemasukan(1_000_000)->create();

        $this->actingAs($saya)
            ->get(route('accounts.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Account/Index')
                ->has('accounts', 1)
                ->where('accounts.0.balance', 6_000_000)
                ->where('totalAssets', 6_000_000),
            );
    }

    public function test_tidak_bisa_menyentuh_rekening_orang_lain(): void
    {
        $rekening = Account::factory()->for(User::factory()->create())->create();
        $saya = User::factory()->create();

        $this->actingAs($saya)
            ->patch(route('accounts.update', $rekening), $this->isian())
            ->assertForbidden();

        $this->actingAs($saya)
            ->delete(route('accounts.destroy', $rekening))
            ->assertForbidden();

        $this->assertDatabaseHas('accounts', ['id' => $rekening->id]);
    }

    public function test_tamu_tidak_bisa_menambah_rekening(): void
    {
        $this->post(route('accounts.store'), $this->isian())
            ->assertRedirect(route('login'));
    }
}
