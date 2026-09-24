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
 * Utang & cicilan (PRD FR-70).
 *
 * Aritmetika pembayaran sudah dijaga AccountBalanceTest dan TransactionTest;
 * yang diuji di sini adalah pengelolaan utangnya sendiri, dan satu sifat yang
 * paling mudah rusak: **status lunas diturunkan dari riwayat, bukan disimpan**.
 * Begitu ia jadi kolom atau tombol, ia bisa berbeda dari angkanya sendiri —
 * dan utang bertanda lunas dengan sisa tiga juta membuat seluruh halaman
 * berhenti bisa dipercaya.
 */
class DebtTest extends TestCase
{
    use RefreshDatabase;

    private function rekening(User $user, float $awal = 20_000_000): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    /** @return array<string, mixed> */
    private function isian(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Cicilan motor',
            'principal' => 12_000_000,
            'monthly_principal' => 1_000_000,
            'due_on' => now(config('app.timezone'))->addYear()->toDateString(),
        ], $ubah);
    }

    private function sisa(User $user, Debt $utang): float
    {
        return app(AccountBalanceService::class)->debtRemaining($user)[$utang->id];
    }

    // ── Pencatatan ──────────────────────────────────────────────────────

    public function test_utang_tersimpan_dan_mengurangi_kekayaan_bersih(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 20_000_000);

        $this->actingAs($user)
            ->post(route('debts.store'), $this->isian())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('debts', ['name' => 'Cicilan motor', 'user_id' => $user->id]);
        $this->assertSame(8_000_000.0, app(AccountBalanceService::class)->netWorth($user));
    }

    /**
     * Mencatat utang TIDAK menambah saldo rekening. Uangnya sudah lama
     * diterima dan dibelanjakan; kalau saldonya ikut naik, pengguna
     * seolah mendapat penghasilan hanya karena mengakui punya utang.
     */
    public function test_mencatat_utang_tidak_menambah_saldo_rekening(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 5_000_000);

        $this->actingAs($user)->post(route('debts.store'), $this->isian());

        $this->assertSame(
            5_000_000.0,
            app(AccountBalanceService::class)->forUser($user)[$rekening->id],
        );
    }

    public function test_pokok_nol_dan_negatif_ditolak(): void
    {
        $user = User::factory()->create();

        foreach ([0, -1_000] as $nilai) {
            $this->actingAs($user)
                ->post(route('debts.store'), $this->isian(['principal' => $nilai]))
                ->assertSessionHasErrors('principal');
        }

        $this->assertDatabaseCount('debts', 0);
    }

    public function test_cicilan_bulanan_boleh_nol(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('debts.store'), $this->isian(['monthly_principal' => 0]))
            ->assertSessionHasNoErrors();
    }

    /**
     * Jatuh tempo di masa LAMPAU sengaja diizinkan — utang yang sudah lewat
     * tenggat justru yang paling perlu terlihat. Menolaknya hanya memaksa
     * pengguna memalsukan tanggal agar datanya bisa masuk.
     */
    public function test_jatuh_tempo_yang_sudah_lewat_diterima(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('debts.store'), $this->isian([
                'due_on' => now(config('app.timezone'))->subMonths(3)->toDateString(),
            ]))
            ->assertSessionHasNoErrors();
    }

    public function test_jatuh_tempo_boleh_kosong(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('debts.store'), $this->isian(['due_on' => null]))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('debts', ['due_on' => null]);
    }

    // ── Status lunas ────────────────────────────────────────────────────

    public function test_status_lunas_diturunkan_dari_riwayat_pembayaran(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $utang = Debt::factory()->for($user)->create(['principal' => 1_000_000]);

        $this->actingAs($user)
            ->get(route('debts.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Debt/Index')
                ->where('debts.0.settled', false)
                ->where('debts.0.remaining', 1_000_000),
            );

        $bayar = Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $utang)->create();

        $this->actingAs($user)
            ->get(route('debts.index'))
            ->assertInertia(fn ($page) => $page
                ->where('debts.0.settled', true)
                ->where('debts.0.remaining', 0)
                ->where('debts.0.progress', 100),
            );

        // Dan kembali aktif sendiri begitu pembayarannya dihapus.
        $bayar->delete();

        $this->actingAs($user)
            ->get(route('debts.index'))
            ->assertInertia(fn ($page) => $page->where('debts.0.settled', false));
    }

    /** Cicilan utang yang sudah lunas tidak lagi membebani rencana bulanan. */
    public function test_rencana_pokok_bulanan_hanya_menghitung_utang_aktif(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);

        Debt::factory()->for($user)->create([
            'principal' => 5_000_000, 'monthly_principal' => 500_000,
        ]);
        $lunas = Debt::factory()->for($user)->create([
            'principal' => 1_000_000, 'monthly_principal' => 300_000,
        ]);

        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $lunas)->create();

        $this->actingAs($user)
            ->get(route('debts.index'))
            ->assertInertia(fn ($page) => $page->where('monthlyPrincipal', 500_000));
    }

    // ── Perubahan ───────────────────────────────────────────────────────

    public function test_menurunkan_pokok_di_bawah_yang_sudah_dibayar_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $utang = Debt::factory()->for($user)->create(['principal' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(3_000_000, $utang)->create();

        $this->actingAs($user)
            ->patch(route('debts.update', $utang), $this->isian(['principal' => 1_000_000]))
            ->assertSessionHasErrors('principal');

        $this->assertSame(5_000_000.0, (float) $utang->fresh()->principal);
        $this->assertSame(2_000_000.0, $this->sisa($user, $utang));
    }

    public function test_pokok_boleh_dinaikkan(): void
    {
        $user = User::factory()->create();
        $utang = Debt::factory()->for($user)->create(['principal' => 5_000_000]);

        $this->actingAs($user)
            ->patch(route('debts.update', $utang), $this->isian(['principal' => 7_000_000]))
            ->assertSessionHasNoErrors();

        $this->assertSame(7_000_000.0, $this->sisa($user, $utang));
    }

    // ── Penghapusan ─────────────────────────────────────────────────────

    public function test_utang_tanpa_pembayaran_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $utang = Debt::factory()->for($user)->create();

        $this->actingAs($user)
            ->delete(route('debts.destroy', $utang))
            ->assertSessionHasNoErrors();

        $this->assertDatabaseMissing('debts', ['id' => $utang->id]);
    }

    /**
     * Menghapus utang berikut pembayarannya akan membuat saldo rekening
     * melonjak naik seolah uang itu tidak pernah dibayarkan.
     */
    public function test_utang_dengan_riwayat_pembayaran_tidak_bisa_dihapus(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $utang = Debt::factory()->for($user)->create();

        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $utang)->create();

        $this->actingAs($user)
            ->delete(route('debts.destroy', $utang))
            ->assertSessionHasErrors('debt');

        $this->assertDatabaseHas('debts', ['id' => $utang->id]);
    }

    // ── Pembayaran lewat endpoint transaksi ─────────────────────────────

    /**
     * Tombol "Catat pembayaran" di halaman utang mengirim ke endpoint
     * TRANSAKSI. Test ini memastikan jalur itu benar-benar bekerja dari sisi
     * utang — bukan sekadar berfungsi saat dipanggil dari halaman transaksi.
     */
    public function test_pembayaran_dari_halaman_utang_mengurangi_kas_dan_utang(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 20_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 5_000_000]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $rekening->id,
                'type' => TransactionType::Payment->value,
                'name' => 'Bayar Cicilan motor',
                'amount' => 1_000_000,
                'debt_id' => $utang->id,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(4_000_000.0, $this->sisa($user, $utang));
        $this->assertSame(
            19_000_000.0,
            app(AccountBalanceService::class)->forUser($user)[$rekening->id],
        );
    }

    // ── Pemisahan antar pengguna ────────────────────────────────────────

    public function test_utang_tidak_bocor_antar_pengguna(): void
    {
        $orangLain = User::factory()->create();
        Debt::factory()->for($orangLain)->create(['principal' => 99_000_000]);

        $saya = User::factory()->create();
        Debt::factory()->for($saya)->create(['principal' => 1_000_000]);

        $this->actingAs($saya)
            ->get(route('debts.index'))
            ->assertInertia(fn ($page) => $page
                ->has('debts', 1)
                ->where('totalRemaining', 1_000_000),
            );
    }

    public function test_tidak_bisa_menyunting_atau_menghapus_utang_orang_lain(): void
    {
        $utang = Debt::factory()->for(User::factory()->create())->create();
        $saya = User::factory()->create();

        $this->actingAs($saya)
            ->patch(route('debts.update', $utang), $this->isian())
            ->assertForbidden();

        $this->actingAs($saya)
            ->delete(route('debts.destroy', $utang))
            ->assertForbidden();

        $this->assertDatabaseHas('debts', ['id' => $utang->id]);
    }

    public function test_tamu_tidak_bisa_membuka_utang(): void
    {
        $this->get(route('debts.index'))->assertRedirect(route('login'));
    }
}
