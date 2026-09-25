<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Lapisan "uang" pada dashboard (PRD FR-79..FR-82).
 *
 * Dashboard menggabungkan dua lapisan: `summary` soal TUJUAN, dan `wealth`
 * soal UANG. Berkas ini menjaga yang kedua — terutama satu sifat yang mudah
 * lepas tanpa terlihat: **arus kas mengikuti bulan yang sedang dilihat**.
 * Kalau ia diam-diam kembali ke bulan berjalan, angka di atas layar dan
 * kalender di bawahnya akan bicara tentang periode berbeda, dan tidak ada
 * apa pun di layar yang menjelaskan selisihnya.
 */
class DashboardWealthTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function rekening(User $user, float $awal = 10_000_000, ?AccountKind $jenis = null): Account
    {
        return Account::factory()->for($user)->jenis($jenis ?? AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    private function wealth(User $user, ?string $bulan = null): array
    {
        $rute = $bulan === null
            ? route('dashboard')
            : route('dashboard', ['bulan' => $bulan]);

        return $this->actingAs($user)->get($rute)
            ->viewData('page')['props']['wealth'];
    }

    // ── Kekayaan bersih ─────────────────────────────────────────────────

    public function test_kekayaan_bersih_adalah_aset_dikurangi_utang(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 20_000_000);
        Debt::factory()->for($user)->create(['principal' => 8_000_000]);

        $wealth = $this->wealth($user);

        $this->assertSame(20_000_000.0, $wealth['total_assets']);
        $this->assertSame(8_000_000.0, $wealth['total_debt']);
        $this->assertSame(12_000_000.0, $wealth['net_worth']);
    }

    public function test_menandai_belum_ada_rekening(): void
    {
        $user = User::factory()->create();

        $this->assertFalse($this->wealth($user)['has_accounts']);

        $this->rekening($user);

        $this->assertTrue($this->wealth($user)['has_accounts']);
    }

    // ── Arus kas mengikuti bulan yang dilihat ───────────────────────────

    /**
     * Inti berkas ini. Menggeser kalender ke bulan lalu HARUS menggeser angka
     * arus kas di atasnya juga.
     */
    public function test_arus_kas_mengikuti_bulan_yang_sedang_dilihat(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(7_000_000)
            ->pada('2026-09-05')->create();
        Transaction::factory()->for($user)->for($rekening)->pemasukan(9_000_000)
            ->pada('2026-08-05')->create();

        $this->assertSame(7_000_000.0, $this->wealth($user)['cash_flow']['income']);
        $this->assertSame(9_000_000.0, $this->wealth($user, '2026-08')['cash_flow']['income']);
    }

    public function test_arus_kas_mengurangi_pengeluaran_dan_pokok_utang(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(10_000_000)
            ->pada('2026-09-01')->create();
        Transaction::factory()->for($user)->for($rekening)->pengeluaran(3_000_000)
            ->pada('2026-09-02')->create();
        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $utang)->pada('2026-09-03')->create();

        $arus = $this->wealth($user)['cash_flow'];

        $this->assertSame(1_000_000.0, $arus['principal']);
        $this->assertSame(6_000_000.0, $arus['net']);
    }

    // ── Komposisi & transaksi terbaru ───────────────────────────────────

    public function test_komposisi_aset_dikelompokkan_per_jenis(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 30_000_000);
        $this->rekening($user, 10_000_000, AccountKind::Gold);

        $komposisi = collect($this->wealth($user)['composition']);

        $this->assertSame(75.0, $komposisi->firstWhere('kind', 'bank')['percentage']);
        $this->assertSame(25.0, $komposisi->firstWhere('kind', 'gold')['percentage']);
    }

    /** Lima terakhir saja, yang terbaru di atas — daftar penuhnya ada di halamannya sendiri. */
    public function test_transaksi_terbaru_dibatasi_lima_dan_terbaru_di_atas(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);

        foreach (range(1, 8) as $hari) {
            Transaction::factory()->for($user)->for($rekening)->pengeluaran(100_000)
                ->pada(sprintf('2026-09-%02d', $hari))->create();
        }

        $terbaru = $this->wealth($user)['recent_transactions'];

        $this->assertCount(5, $terbaru);
        $this->assertSame('2026-09-08', $terbaru[0]['occurred_on']);
        $this->assertSame('2026-09-04', $terbaru[4]['occurred_on']);
    }

    /**
     * Transaksi terbaru TIDAK disaring per bulan, beda dari arus kas. Kalau
     * ikut tersaring, pengguna yang menengok bulan lalu akan melihat panel
     * "terbaru" berisi hal-hal lama — atau kosong sama sekali di bulan yang
     * tidak punya transaksi.
     */
    public function test_transaksi_terbaru_tidak_ikut_tersaring_bulan(): void
    {
        Carbon::setTestNow(Carbon::parse('2026-09-15 09:00:00'));

        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);

        Transaction::factory()->for($user)->for($rekening)->pengeluaran(100_000)
            ->pada('2026-09-10')->create();

        $terbaru = $this->wealth($user, '2026-07')['recent_transactions'];

        $this->assertCount(1, $terbaru);
        $this->assertSame('2026-09-10', $terbaru[0]['occurred_on']);
    }

    // ── Pemisahan antar pengguna ────────────────────────────────────────

    public function test_angka_kekayaan_tidak_bocor_antar_pengguna(): void
    {
        $orangLain = User::factory()->create();
        $rekeningOrangLain = $this->rekening($orangLain, 99_000_000);
        Transaction::factory()->for($orangLain)->for($rekeningOrangLain)
            ->pemasukan(50_000_000)->create();

        $saya = User::factory()->create();
        $this->rekening($saya, 1_000_000);

        $wealth = $this->wealth($saya);

        $this->assertSame(1_000_000.0, $wealth['net_worth']);
        $this->assertSame(0.0, $wealth['cash_flow']['income']);
        $this->assertCount(0, $wealth['recent_transactions']);
    }
}
