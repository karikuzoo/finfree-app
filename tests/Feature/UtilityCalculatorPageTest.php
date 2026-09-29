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
