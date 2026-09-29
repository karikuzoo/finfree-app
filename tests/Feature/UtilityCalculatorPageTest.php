<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kalkulator Pinjaman/KPR (FR-41) dan Investasi (FR-42). Yang diuji lapisan
 * HTTP-nya — akses tanpa login, validasi, bentuk props. Rumusnya dijaga
 * GoalCalculatorServiceTest; satu kasus dipakai ulang sebagai pemeriksaan
 * silang bahwa controller benar-benar memanggil service.
 */
class UtilityCalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_kalkulator_pinjaman_bisa_dibuka_tanpa_login(): void
    {
        $this->get(route('calculator.loan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculator/Loan')
                ->where('input', null)
                ->where('result', null));
    }

    public function test_kalkulator_investasi_bisa_dibuka_tanpa_login(): void
    {
        $this->get(route('calculator.investment'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculator/Investment')
                ->where('input', null)
                ->where('result', null));
    }

    public function test_pinjaman_dihitung_seperti_bank(): void
    {
        $this->get(route('calculator.loan', [
            'principal' => 500000000,
            'annual_interest_rate' => 10,
            'months' => 240,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.monthly_installment', 4825109)
                ->where('result.months', 240)
                ->has('result.yearly', 20)
                ->where('result.yearly.19.balance', 0));
    }

    public function test_pinjaman_menolak_tenor_di_atas_tiga_puluh_tahun(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', ['principal' => 1000000, 'annual_interest_rate' => 10, 'months' => 361]))
            ->assertRedirect(route('calculator.loan'))
            ->assertSessionHasErrors(['months' => 'Tenor maksimal 360 bulan (30 tahun).']);
    }

    public function test_pinjaman_tanpa_bunga_diterima(): void
    {
        $this->get(route('calculator.loan', ['principal' => 12000000, 'annual_interest_rate' => 0, 'months' => 12]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.monthly_installment', 1000000)
                ->where('result.total_interest', 0));
    }

    // ── Jenis bunga & cek kesehatan cicilan ─────────────────────────────

    private function kpr(array $ubah = []): array
    {
        return array_merge([
            'principal' => 500000000,
            'annual_interest_rate' => 7,
            'months' => 240,
        ], $ubah);
    }

    public function test_tanpa_pendapatan_tidak_ada_cek_kesehatan(): void
    {
        $this->get(route('calculator.loan', $this->kpr()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('health', null)
                ->where('stress', null)
                ->where('result.installment_after_float', null));
    }

    public function test_bunga_tetap_lalu_mengambang_dinilai_dari_angsuran_tahap_kedua(): void
    {
        $halaman = $this->get(route('calculator.loan', $this->kpr([
            'rate_type' => 'fix_float',
            'fixed_years' => 3,
            'floating_rate' => 11,
            'monthly_income' => 25000000,
            'monthly_expenses' => 8000000,
        ])))->assertOk();

        $props = $halaman->viewData('page')['props'];
        $this->assertSame(36, $props['result']['fixed_months']);
        $this->assertGreaterThan($props['result']['monthly_installment'], $props['result']['installment_after_float']);
        $this->assertSame($props['result']['installment_after_float'] + 0, $props['health']['worst']['installments']);
        $this->assertSame('setelah 3 tahun bunga tetap', $props['health']['worst']['label']);
    }

    public function test_bunga_mengambang_diuji_dengan_bunga_naik(): void
    {
        $halaman = $this->get(route('calculator.loan', $this->kpr([
            'rate_type' => 'floating',
            'floating_rate' => 10,
            'monthly_income' => 20000000,
        ])))->assertOk();

        $props = $halaman->viewData('page')['props'];
        // Tabel utama tetap bunga sekarang; skenario naik terpisah.
        $this->assertSame(0.07 / 12, $props['result']['monthly_rate']);
        $this->assertSame(4825109, $props['stress']['monthly_installment']);
        $this->assertSame('bila bunga naik ke 10%', $props['health']['worst']['label']);
    }

    public function test_bunga_tetap_mengabaikan_isian_mengambang(): void
    {
        $this->get(route('calculator.loan', $this->kpr(['rate_type' => 'fixed', 'floating_rate' => 99, 'fixed_years' => 99])))
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->assertInertia(fn (Assert $page) => $page->where('result.installment_after_float', null));
    }

    public function test_fix_float_tanpa_bunga_mengambang_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'fix_float', 'fixed_years' => 3])))
            ->assertSessionHasErrors('floating_rate');
    }

    public function test_masa_tetap_tidak_boleh_sepanjang_tenor(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'fix_float', 'fixed_years' => 20, 'floating_rate' => 11])))
            ->assertSessionHasErrors(['fixed_years' => 'Masa bunga tetap harus lebih pendek dari tenor. Bila bunganya tetap sepanjang tenor, pilih "Tetap".']);
    }

    public function test_cek_kesehatan_memakai_pajak_tahunan_dan_cicilan_lain(): void
    {
        $this->get(route('calculator.loan', $this->kpr([
            'annual_interest_rate' => 0,
            'months' => 100,
            'monthly_income' => 20000000,
            'other_installments' => 1000000,
            'monthly_expenses' => 10000000,
            'annual_taxes' => 1200000,
        ])))
            ->assertInertia(fn (Assert $page) => $page
                // Angsuran 5 jt + cicilan lain 1 jt = 30% → sehat; sisa 3,9 jt.
                ->where('health.now.dsr', 30)
                ->where('health.monthly_taxes', 100000)
                ->where('health.now.residual', 3900000)
                ->where('health.status', 'healthy'));
    }

    public function test_investasi_menghitung_nilai_akhir(): void
    {
        $this->get(route('calculator.investment', [
            'initial_amount' => 10000000,
            'monthly_contribution' => 1000000,
            'months' => 24,
            'annual_return_rate' => 0,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.final_value', 34000000)
                ->where('result.total_contribution', 24000000)
                ->where('result.investment_growth', 0));
    }

    public function test_investasi_dana_awal_saja_diterima(): void
    {
        $this->get(route('calculator.investment', [
            'initial_amount' => 10000000,
            'monthly_contribution' => 0,
            'months' => 12,
            'annual_return_rate' => 10,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('result.final_value', 11000000));
    }

    /** Dana awal dan setoran sama-sama nol: tidak ada yang bisa ditampilkan. */
    public function test_investasi_tanpa_dana_sama_sekali_ditolak(): void
    {
        $this->from(route('calculator.investment'))
            ->get(route('calculator.investment', ['monthly_contribution' => 0, 'months' => 12, 'annual_return_rate' => 8]))
            ->assertRedirect(route('calculator.investment'))
            ->assertSessionHasErrors(['monthly_contribution' => 'Isi dana awal, setoran bulanan, atau keduanya.']);
    }

    public function test_daftar_kalkulator_menautkan_ketiganya(): void
    {
        $this->get(route('calculator.index'))->assertOk();
        // Isi kartunya diuji di sisi React; di sini cukup memastikan ketiga
        // route-nya terdaftar dan bisa dibuka tamu.
        foreach (['calculator.goal', 'calculator.loan', 'calculator.investment'] as $nama) {
            $this->get(route($nama))->assertOk();
        }
    }
}
