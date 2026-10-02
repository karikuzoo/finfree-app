<?php

namespace Tests\Feature;

use App\Models\User;
use App\Services\CalculationHistoryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Riwayat kalkulasi cepat (PRD FR-45): dicatat otomatis untuk pengguna yang
 * login, bisa dibuka kembali, tanpa data cek kesehatan KPR.
 */
class CalculationHistoryTest extends TestCase
{
    use RefreshDatabase;

    private const TUJUAN = [
        'target_amount' => '120000000',
        'months' => '24',
        'annual_return_rate' => '0',
    ];

    private const INVESTASI = [
        'monthly_contribution' => '1000000',
        'months' => '12',
        'annual_return_rate' => '0',
    ];

    private const PINJAMAN = [
        'principal' => '500000000',
        'annual_interest_rate' => '10',
        'months' => '240',
    ];

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    public function test_hitungan_pengguna_yang_masuk_tercatat_di_ketiga_kalkulator(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('calculator.goal', self::TUJUAN))->assertOk();
        $this->actingAs($user)->get(route('calculator.investment', self::INVESTASI))->assertOk();
        $this->actingAs($user)->get(route('calculator.loan', self::PINJAMAN))->assertOk();

        $this->assertSame(
            ['goal', 'investment', 'loan'],
            $user->calculationHistories()->orderBy('id')->pluck('calculator')->all(),
        );

        $tujuan = $user->calculationHistories()->where('calculator', 'goal')->first();
        $this->assertEquals(self::TUJUAN, array_intersect_key($tujuan->input, self::TUJUAN));
        $this->assertEquals(5_000_000, $tujuan->summary['monthly_contribution']);
    }

    public function test_tamu_tidak_dicatat(): void
    {
        $this->get(route('calculator.goal', self::TUJUAN))->assertOk();

        $this->assertDatabaseCount('calculation_histories', 0);
    }

    public function test_isian_tidak_sah_tidak_dicatat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get(route('calculator.goal', [...self::TUJUAN, 'months' => '0']));

        $this->assertDatabaseCount('calculation_histories', 0);
    }

    /** Isian yang sama — urutan query berbeda sekalipun — satu baris, naik ke atas. */
    public function test_isian_yang_sama_tidak_dicatat_dua_kali(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-10-01 08:00');
        $this->actingAs($user)->get(route('calculator.goal', self::TUJUAN));
        $this->actingAs($user)->get(route('calculator.investment', self::INVESTASI));

        Carbon::setTestNow('2026-10-01 09:00');
        $this->actingAs($user)->get(route('calculator.goal', array_reverse(self::TUJUAN, true)));

        $this->assertSame(2, $user->calculationHistories()->count());
        $this->assertSame(
            'goal',
            $user->calculationHistories()->orderByDesc('updated_at')->value('calculator'),
        );
    }

    public function test_hanya_menyimpan_batas_terakhir(): void
    {
        $user = User::factory()->create();
        $layanan = app(CalculationHistoryService::class);

        for ($i = 1; $i <= CalculationHistoryService::LIMIT + 3; $i++) {
            Carbon::setTestNow(Carbon::parse('2026-10-01')->addMinutes($i));
            $layanan->record($user, 'investment', [...self::INVESTASI, 'months' => (string) $i], ['final_value' => $i]);
        }

        $this->assertSame(CalculationHistoryService::LIMIT, $user->calculationHistories()->count());
        // Yang terbuang adalah yang paling lama.
        $this->assertFalse($user->calculationHistories()->where('summary->final_value', 1)->exists());
    }

    /**
     * Data keuangan pribadi tidak pernah disimpan — baik lewat badan POST cek
     * kesehatan, maupun bila suatu saat ikut terselip ke isian yang dicatat.
     */
    public function test_data_cek_kesehatan_kpr_tidak_disimpan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('calculator.loan.health', self::PINJAMAN), [
            'monthly_income' => 25_000_000,
            'monthly_expenses' => 7_000_000,
        ])->assertOk();

        app(CalculationHistoryService::class)->record($user, 'loan', [...self::PINJAMAN, 'months' => '120', 'monthly_income' => '25000000'], []);

        foreach ($user->calculationHistories as $baris) {
            $this->assertArrayNotHasKey('monthly_income', $baris->input);
            $this->assertArrayNotHasKey('monthly_expenses', $baris->input);
        }
        $this->assertSame(2, $user->calculationHistories()->count());
    }

    public function test_halaman_riwayat_menautkan_kembali_ke_kalkulatornya(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('calculator.loan', [
            'principal' => '500000000',
            'rate_type' => 'tiered',
            'months' => '240',
            'tiers' => [['rate' => '3.75', 'until_year' => '3'], ['rate' => '9.75']],
        ]));

        $response = $this->actingAs($user)->get(route('calculator.history'));

        $response->assertInertia(fn (Assert $page) => $page
            ->component('Calculator/History')
            ->has('histories', 1)
            ->where('histories.0.calculator', 'loan'));

        // Membuka kembali tautannya menghitung ulang isian yang sama.
        $url = $response->viewData('page')['props']['histories'][0]['url'];
        $this->assertStringContainsString('tiers%5B0%5D%5Brate%5D=3.75', $url);

        $this->actingAs($user)->get($url)
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculator/Loan')
                ->where('input.rate_type', 'tiered')
                ->has('input.tiers', 2));
    }

    public function test_halaman_riwayat_hanya_memuat_milik_sendiri(): void
    {
        $saya = User::factory()->create();
        $orangLain = User::factory()->create();
        $this->actingAs($orangLain)->get(route('calculator.goal', self::TUJUAN));

        $this->actingAs($saya)->get(route('calculator.history'))
            ->assertInertia(fn (Assert $page) => $page->has('histories', 0));
    }

    public function test_menghapus_satu_dan_semua(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('calculator.goal', self::TUJUAN));
        $this->actingAs($user)->get(route('calculator.investment', self::INVESTASI));

        $satu = $user->calculationHistories()->first();
        $this->actingAs($user)->delete(route('calculator.history.destroy', $satu))->assertRedirect();
        $this->assertModelMissing($satu);
        $this->assertSame(1, $user->calculationHistories()->count());

        $this->actingAs($user)->delete(route('calculator.history.clear'))->assertRedirect();
        $this->assertSame(0, $user->calculationHistories()->count());
    }

    public function test_tidak_bisa_menghapus_riwayat_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $this->actingAs($pemilik)->get(route('calculator.goal', self::TUJUAN));
        $baris = $pemilik->calculationHistories()->first();

        $this->actingAs(User::factory()->create())
            ->delete(route('calculator.history.destroy', $baris))
            ->assertForbidden();

        $this->assertModelExists($baris);
    }

    public function test_riwayat_ikut_terhapus_bersama_akun(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user)->get(route('calculator.goal', self::TUJUAN));

        $user->delete();

        $this->assertDatabaseCount('calculation_histories', 0);
    }
}
