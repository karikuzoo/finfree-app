<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kalkulator publik. Yang diuji di sini adalah lapisan HTTP-nya — akses tanpa
 * login, validasi, dan bentuk props yang sampai ke halaman.
 *
 * Kebenaran rumusnya sendiri sudah dijaga GoalCalculatorServiceTest terhadap
 * docs/fixtures/calculator-cases.json, jadi tidak diulang di sini. Satu kasus
 * dipakai ulang sebagai pemeriksaan silang bahwa controller benar-benar
 * memanggil service dan tidak diam-diam menghitung sendiri.
 */
class GoalCalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_dapat_diakses_tanpa_login(): void
    {
        $this->get(route('calculator.goal'))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Calculator/Goal')
                    ->where('result', null)
                    ->where('input', null)
            );
    }

    public function test_daftar_kalkulator_dapat_diakses_tanpa_login(): void
    {
        $this->get(route('calculator.index'))->assertOk();
    }

    public function test_menghitung_dan_mengirim_hasil_sebagai_props(): void
    {
        // Sama dengan kasus "dasar-tanpa-inflasi" di test vector.
        $this->get(route('calculator.goal', [
            'target_amount' => 500_000_000,
            'current_amount' => 0,
            'months' => 120,
            'annual_return_rate' => 8,
        ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->component('Calculator/Goal')
                    ->where('result.monthly_contribution_required', 2_775_862)
                    ->where('result.future_value_target', 500_000_000)
                    ->where('result.already_achieved', false)
            );
    }

    public function test_inflasi_menaikkan_nominal_target(): void
    {
        // Sama dengan kasus "dasar-dengan-inflasi" di test vector, termasuk
        // dana awal 50 juta — tanpa itu hasilnya bukan angka yang sama.
        $this->get(route('calculator.goal', [
            'target_amount' => 500_000_000,
            'current_amount' => 50_000_000,
            'months' => 120,
            'annual_return_rate' => 8,
            'annual_inflation_rate' => 3.5,
        ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('result.future_value_target', 705_299_380)
                    ->where('result.monthly_contribution_required', 3_316_339)
            );
    }

    public function test_dana_awal_yang_sudah_melebihi_target_ditandai_tercapai(): void
    {
        $this->get(route('calculator.goal', [
            'target_amount' => 100_000_000,
            'current_amount' => 150_000_000,
            'months' => 60,
            'annual_return_rate' => 6,
        ]))
            ->assertOk()
            ->assertInertia(
                fn (Assert $page) => $page
                    ->where('result.already_achieved', true)
                    ->where('result.monthly_contribution_required', 0)
            );
    }

    public function test_menolak_input_yang_tidak_masuk_akal(): void
    {
        $this->get(route('calculator.goal', [
            'target_amount' => 0,
            'months' => 0,
            'annual_return_rate' => 99,
        ]))->assertSessionHasErrors([
            'target_amount',
            'months',
            'annual_return_rate',
        ]);
    }

    public function test_menolak_jangka_waktu_di_luar_batas(): void
    {
        $this->get(route('calculator.goal', [
            'target_amount' => 100_000_000,
            'months' => 721,
            'annual_return_rate' => 8,
        ]))->assertSessionHasErrors('months');
    }
}
