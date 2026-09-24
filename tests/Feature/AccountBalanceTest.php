<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Invarian saldo, kekayaan bersih, dan arus kas (PRD FR-63..FR-69).
 *
 * Isi berkas ini diturunkan dari test invarian prototipe Arus
 * (`tests/finance.mjs`), ditulis ulang untuk AccountBalanceService. Yang
 * dijaga bukan angka tertentu melainkan SIFAT yang harus selalu berlaku:
 * transfer tidak menciptakan uang, pembayaran pokok tidak mengubah kekayaan
 * bersih, dan penyesuaian nilai tidak masuk arus kas. Ketiganya mudah rusak
 * tanpa ada yang menyadarinya, karena angkanya tetap terlihat masuk akal.
 */
class AccountBalanceTest extends TestCase
{
    use RefreshDatabase;

    private AccountBalanceService $saldo;

    protected function setUp(): void
    {
        parent::setUp();
        $this->saldo = app(AccountBalanceService::class);
    }

    private function rekening(User $user, float $awal = 0, ?AccountKind $jenis = null): Account
    {
        return Account::factory()
            ->for($user)
            ->jenis($jenis ?? AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    private function bulanIni(): string
    {
        return now(config('app.timezone'))->format('Y-m');
    }

    // ── Saldo ───────────────────────────────────────────────────────────

    public function test_rekening_tanpa_transaksi_bersaldo_sama_dengan_saldo_awal(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 1_000_000);

        $this->assertSame(1_000_000.0, $this->saldo->forUser($user)[$rekening->id]);
    }

    /**
     * Tiap transaksi mengubah saldo TEPAT SEKALI. Penjumlahan ganda adalah
     * kekeliruan klasik saat saldo dihitung dari dua arah (transaksi keluar
     * dan transfer masuk) dalam satu perulangan.
     */
    public function test_pemasukan_dan_pengeluaran_mengubah_saldo_tepat_sekali(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 1_000_000);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(500_000)->create();
        Transaction::factory()->for($user)->for($rekening)->pengeluaran(200_000)->create();

        $this->assertSame(1_300_000.0, $this->saldo->forUser($user)[$rekening->id]);
    }

    /**
     * Transfer memindahkan uang, bukan menciptakannya. Kalau total aset
     * berubah setelah transfer, ada sisi yang tidak dihitung.
     */
    public function test_transfer_tidak_mengubah_total_aset(): void
    {
        $user = User::factory()->create();
        $asal = $this->rekening($user, 1_000_000);
        $tujuan = $this->rekening($user, 0);

        $sebelum = $this->saldo->totalAssets($user);

        Transaction::factory()->for($user)->for($asal)->transfer(400_000, $tujuan)->create();

        $saldo = $this->saldo->forUser($user);

        $this->assertSame(600_000.0, $saldo[$asal->id]);
        $this->assertSame(400_000.0, $saldo[$tujuan->id]);
        $this->assertSame($sebelum, $this->saldo->totalAssets($user));
    }

    /**
     * Penyesuaian nilai mengubah kekayaan tanpa ada uang yang berpindah —
     * dan boleh negatif, karena harga emas dan saham bisa turun.
     */
    public function test_penyesuaian_nilai_boleh_negatif_dan_tidak_menyentuh_rekening_lain(): void
    {
        $user = User::factory()->create();
        $emas = $this->rekening($user, 1_000_000, AccountKind::Gold);
        $bank = $this->rekening($user, 500_000);

        Transaction::factory()->for($user)->for($emas)->penyesuaian(-300_000)->create();

        $saldo = $this->saldo->forUser($user);

        $this->assertSame(700_000.0, $saldo[$emas->id]);
        $this->assertSame(500_000.0, $saldo[$bank->id]);
    }

    // ── Kekayaan bersih ─────────────────────────────────────────────────

    public function test_kekayaan_bersih_mengurangi_sisa_pokok_utang(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 10_000_000);
        Debt::factory()->for($user)->create(['principal' => 4_000_000]);

        $this->assertSame(10_000_000.0, $this->saldo->totalAssets($user));
        $this->assertSame(6_000_000.0, $this->saldo->netWorth($user));
    }

    /**
     * Membayar pokok utang memindahkan angka dari satu sisi neraca ke sisi
     * lain: kas berkurang, utang berkurang sama besar. Kekayaan bersih TIDAK
     * boleh bergerak. Kalau bergerak, salah satu sisi terhitung dua kali.
     */
    public function test_pembayaran_pokok_tidak_mengubah_kekayaan_bersih(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 4_000_000]);

        $sebelum = $this->saldo->netWorth($user);

        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_500_000, $utang)->create();

        $this->assertSame(8_500_000.0, $this->saldo->forUser($user)[$rekening->id]);
        $this->assertSame(2_500_000.0, $this->saldo->debtRemaining($user)[$utang->id]);
        $this->assertSame($sebelum, $this->saldo->netWorth($user));
    }

    public function test_menghapus_pembayaran_mengembalikan_sisa_utang(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 4_000_000]);

        $bayar = Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $utang)->create();

        $bayar->delete();

        $this->assertSame(4_000_000.0, $this->saldo->debtRemaining($user)[$utang->id]);
        $this->assertSame(10_000_000.0, $this->saldo->forUser($user)[$rekening->id]);
    }

    // ── Arus kas ────────────────────────────────────────────────────────

    /**
     * Transfer dan penyesuaian TIDAK masuk arus kas. Membeli reksa dana
     * bukan pengeluaran, dan harga emas yang naik bukan pemasukan —
     * memasukkannya membuat laporan bulanan tampak jauh lebih buruk atau
     * lebih baik daripada keadaan sebenarnya.
     */
    public function test_arus_kas_mengabaikan_transfer_dan_penyesuaian(): void
    {
        $user = User::factory()->create();
        $bank = $this->rekening($user, 20_000_000);
        $saham = $this->rekening($user, 0, AccountKind::Stock);

        Transaction::factory()->for($user)->for($bank)->pemasukan(15_000_000)->create();
        Transaction::factory()->for($user)->for($bank)->pengeluaran(3_000_000)->create();
        Transaction::factory()->for($user)->for($bank)->transfer(5_000_000, $saham)->create();
        Transaction::factory()->for($user)->for($saham)->penyesuaian(750_000)->create();

        $arus = $this->saldo->monthlyCashFlow($user, $this->bulanIni());

        $this->assertSame(15_000_000.0, $arus['income']);
        $this->assertSame(3_000_000.0, $arus['expense']);
        $this->assertSame(12_000_000.0, $arus['net']);
    }

    public function test_pembayaran_pokok_mengurangi_sisa_arus_kas(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 20_000_000);
        $utang = Debt::factory()->for($user)->create(['principal' => 5_000_000]);

        Transaction::factory()->for($user)->for($rekening)->pemasukan(10_000_000)->create();
        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(1_000_000, $utang)->create();

        $arus = $this->saldo->monthlyCashFlow($user, $this->bulanIni());

        $this->assertSame(1_000_000.0, $arus['principal']);
        $this->assertSame(9_000_000.0, $arus['net']);
    }

    public function test_arus_kas_hanya_menghitung_bulan_yang_diminta(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $bulanLalu = now(config('app.timezone'))->subMonthNoOverflow();

        Transaction::factory()->for($user)->for($rekening)->pemasukan(7_000_000)->create();
        Transaction::factory()->for($user)->for($rekening)->pemasukan(9_000_000)
            ->pada($bulanLalu->toDateString())->create();

        $this->assertSame(
            7_000_000.0,
            $this->saldo->monthlyCashFlow($user, $this->bulanIni())['income'],
        );
        $this->assertSame(
            9_000_000.0,
            $this->saldo->monthlyCashFlow($user, $bulanLalu->format('Y-m'))['income'],
        );
    }

    // ── Komposisi aset ──────────────────────────────────────────────────

    public function test_komposisi_aset_dijumlahkan_per_jenis_dan_berjumlah_seratus_persen(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 30_000_000);
        $this->rekening($user, 10_000_000);
        $this->rekening($user, 10_000_000, AccountKind::Gold);

        $komposisi = $this->saldo->assetComposition($user);

        $bank = $komposisi->firstWhere('kind', 'bank');
        $emas = $komposisi->firstWhere('kind', 'gold');

        $this->assertSame(40_000_000.0, $bank['amount'], 'Dua rekening bank seharusnya digabung.');
        $this->assertSame(80.0, $bank['percentage']);
        $this->assertSame(20.0, $emas['percentage']);
        $this->assertSame(100.0, round($komposisi->sum('percentage'), 1));
    }

    public function test_komposisi_tanpa_aset_tidak_membagi_dengan_nol(): void
    {
        $user = User::factory()->create();
        $this->rekening($user, 0);

        $this->assertSame(0.0, $this->saldo->assetComposition($user)->first()['percentage']);
    }

    // ── Pemisahan antar pengguna ────────────────────────────────────────

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_saldo_dan_utang_tidak_bocor_antar_pengguna(): void
    {
        $orangLain = User::factory()->create();
        $this->rekening($orangLain, 99_000_000);
        Debt::factory()->for($orangLain)->create(['principal' => 50_000_000]);

        $saya = User::factory()->create();
        $this->rekening($saya, 1_000_000);

        $this->assertSame(1_000_000.0, $this->saldo->totalAssets($saya));
        $this->assertSame(1_000_000.0, $this->saldo->netWorth($saya));
        $this->assertCount(1, $this->saldo->forUser($saya));
        $this->assertSame([], $this->saldo->debtRemaining($saya));
    }

    public function test_pengguna_tanpa_rekening_menghasilkan_nol(): void
    {
        $user = User::factory()->create();

        $this->assertSame([], $this->saldo->forUser($user));
        $this->assertSame(0.0, $this->saldo->totalAssets($user));
        $this->assertSame(0.0, $this->saldo->netWorth($user));
    }
}
