<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Investasi dan penilaian ulang (PRD FR-71, FR-72).
 *
 * Dua hal yang dijaga di sini:
 *
 * 1. Investasi BUKAN entitas terpisah — ia rekening berjenis non-likuid.
 *    Kalau suatu saat dipisah jadi tabel sendiri, nilainya berhenti ikut
 *    terhitung dalam total aset, dan test ini yang menangkapnya.
 * 2. Penilaian ulang menyimpan SELISIH sebagai transaksi, bukan menimpa
 *    saldo. Itu yang membuat perubahan nilai punya tanggal, muncul di
 *    riwayat, dan bisa dibatalkan.
 */
class InvestmentValuationTest extends TestCase
{
    use RefreshDatabase;

    private function aset(User $user, AccountKind $jenis, float $awal): Account
    {
        return Account::factory()->for($user)->jenis($jenis)
            ->create(['opening_balance' => $awal]);
    }

    private function nilai(User $user, Account $aset): float
    {
        return app(AccountBalanceService::class)->forUser($user)[$aset->id];
    }

    private function hariIni(): string
    {
        return now(config('app.timezone'))->toDateString();
    }

    // ── Daftar ──────────────────────────────────────────────────────────

    public function test_hanya_memuat_aset_yang_nilainya_bergerak_sendiri(): void
    {
        $user = User::factory()->create();
        $this->aset($user, AccountKind::Bank, 10_000_000);
        $this->aset($user, AccountKind::Cash, 500_000);
        $this->aset($user, AccountKind::Stock, 18_000_000);
        $this->aset($user, AccountKind::Gold, 10_000_000);

        $this->actingAs($user)
            ->get(route('investments.index'))
            ->assertInertia(fn ($page) => $page
                ->component('Investment/Index')
                ->has('investments', 2)
                ->where('totalValue', 28_000_000),
            );
    }

    public function test_investasi_tetap_terhitung_dalam_total_aset_keseluruhan(): void
    {
        $user = User::factory()->create();
        $this->aset($user, AccountKind::Bank, 10_000_000);
        $this->aset($user, AccountKind::Stock, 18_000_000);

        $this->assertSame(
            28_000_000.0,
            app(AccountBalanceService::class)->totalAssets($user),
        );
    }

    /**
     * Halaman ini punya formnya sendiri untuk menambah investasi, dan pilihan
     * jenisnya datang dari sini. Bila bank atau tunai sampai ikut terkirim,
     * pengguna bisa membuat rekening bank dari halaman Investasi — yang lalu
     * tidak pernah muncul di daftarnya sendiri.
     */
    public function test_pilihan_jenis_hanya_memuat_aset_investasi(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('investments.index'))
            ->assertInertia(function ($page) {
                $nilai = array_column($page->toArray()['props']['kinds'], 'value');

                foreach (AccountKind::nilaiLikuid() as $likuid) {
                    $this->assertNotContains($likuid, $nilai);
                }

                $this->assertContains(AccountKind::Stock->value, $nilai);
                $this->assertContains(AccountKind::Gold->value, $nilai);
            });
    }

    public function test_daftar_tidak_memuat_investasi_orang_lain(): void
    {
        $orangLain = User::factory()->create();
        $this->aset($orangLain, AccountKind::Gold, 99_000_000);

        $saya = User::factory()->create();
        $this->aset($saya, AccountKind::Stock, 1_000_000);

        $this->actingAs($saya)
            ->get(route('investments.index'))
            ->assertInertia(fn ($page) => $page
                ->has('investments', 1)
                ->where('totalValue', 1_000_000),
            );
    }

    // ── Penilaian ulang ─────────────────────────────────────────────────

    /**
     * Yang dikirim adalah nilai TOTAL terkini; selisihnya yang disimpan.
     * Kalau suatu saat berubah jadi menyimpan nilai mentah sebagai amount,
     * saldonya akan melonjak menjadi hampir dua kali lipat.
     */
    public function test_kenaikan_nilai_disimpan_sebagai_selisih(): void
    {
        $user = User::factory()->create();
        $saham = $this->aset($user, AccountKind::Stock, 18_000_000);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $saham), [
                'value' => 19_000_000,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(19_000_000.0, $this->nilai($user, $saham));
        $this->assertDatabaseHas('transactions', [
            'account_id' => $saham->id,
            'type' => TransactionType::Adjustment->value,
            'amount' => 1_000_000,
        ]);
    }

    public function test_penurunan_nilai_disimpan_sebagai_selisih_negatif(): void
    {
        $user = User::factory()->create();
        $emas = $this->aset($user, AccountKind::Gold, 10_000_000);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $emas), [
                'value' => 8_500_000,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(8_500_000.0, $this->nilai($user, $emas));
        $this->assertDatabaseHas('transactions', [
            'account_id' => $emas->id,
            'amount' => -1_500_000,
        ]);
    }

    /** Selisih dihitung dari nilai BERJALAN, bukan dari saldo awal. */
    public function test_penilaian_berturut_turut_dihitung_dari_nilai_berjalan(): void
    {
        $user = User::factory()->create();
        $saham = $this->aset($user, AccountKind::Stock, 10_000_000);

        foreach ([12_000_000, 11_000_000] as $nilai) {
            $this->actingAs($user)
                ->post(route('accounts.valuation.store', $saham), [
                    'value' => $nilai,
                    'occurred_on' => $this->hariIni(),
                ])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(11_000_000.0, $this->nilai($user, $saham));
        $this->assertDatabaseCount('transactions', 2);
    }

    public function test_menghapus_penilaian_mengembalikan_nilai_sebelumnya(): void
    {
        $user = User::factory()->create();
        $emas = $this->aset($user, AccountKind::Gold, 10_000_000);

        $this->actingAs($user)->post(route('accounts.valuation.store', $emas), [
            'value' => 12_000_000,
            'occurred_on' => $this->hariIni(),
        ]);

        Transaction::where('account_id', $emas->id)->delete();

        $this->assertSame(10_000_000.0, $this->nilai($user, $emas));
    }

    public function test_nilai_yang_sama_ditolak_tanpa_mencatat_apa_pun(): void
    {
        $user = User::factory()->create();
        $saham = $this->aset($user, AccountKind::Stock, 18_000_000);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $saham), [
                'value' => 18_000_000,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_nilai_negatif_dan_tanggal_masa_depan_ditolak(): void
    {
        $user = User::factory()->create();
        $saham = $this->aset($user, AccountKind::Stock, 18_000_000);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $saham), [
                'value' => -1,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertSessionHasErrors('value');

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $saham), [
                'value' => 19_000_000,
                'occurred_on' => now(config('app.timezone'))->addDay()->toDateString(),
            ])
            ->assertSessionHasErrors('occurred_on');

        $this->assertDatabaseCount('transactions', 0);
    }

    /**
     * Penilaian nol sah — aset bisa habis terjual. Yang tidak boleh adalah
     * membuat saldo rekening LAIN ikut minus, dan itu tetap dijaga
     * LedgerGuard karena penyesuaian melewati jalur yang sama.
     */
    public function test_nilai_nol_diterima(): void
    {
        $user = User::factory()->create();
        $emas = $this->aset($user, AccountKind::Gold, 10_000_000);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $emas), [
                'value' => 0,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(0.0, $this->nilai($user, $emas));
    }

    // ── Tanggal penilaian terakhir ──────────────────────────────────────

    public function test_tanggal_penilaian_terakhir_kosong_sebelum_pernah_dinilai(): void
    {
        $user = User::factory()->create();
        $this->aset($user, AccountKind::Stock, 18_000_000);

        $this->actingAs($user)
            ->get(route('investments.index'))
            ->assertInertia(fn ($page) => $page->where('investments.0.last_valued_on', null));
    }

    public function test_tanggal_penilaian_terakhir_mengambil_yang_paling_baru(): void
    {
        $user = User::factory()->create();
        $saham = $this->aset($user, AccountKind::Stock, 10_000_000);
        $kemarin = now(config('app.timezone'))->subDay()->toDateString();

        Transaction::factory()->for($user)->for($saham)->penyesuaian(500_000)
            ->pada($kemarin)->create();
        Transaction::factory()->for($user)->for($saham)->penyesuaian(250_000)
            ->pada($this->hariIni())->create();

        $this->actingAs($user)
            ->get(route('investments.index'))
            ->assertInertia(fn ($page) => $page
                ->where('investments.0.last_valued_on', $this->hariIni()),
            );
    }

    // ── Otorisasi ───────────────────────────────────────────────────────

    public function test_tidak_bisa_menilai_ulang_aset_orang_lain(): void
    {
        $orangLain = User::factory()->create();
        $aset = $this->aset($orangLain, AccountKind::Gold, 10_000_000);

        $this->actingAs(User::factory()->create())
            ->post(route('accounts.valuation.store', $aset), [
                'value' => 1,
                'occurred_on' => $this->hariIni(),
            ])
            ->assertForbidden();

        $this->assertDatabaseCount('transactions', 0);
    }

    public function test_tamu_tidak_bisa_membuka_investasi(): void
    {
        $this->get(route('investments.index'))->assertRedirect(route('login'));
    }
}
