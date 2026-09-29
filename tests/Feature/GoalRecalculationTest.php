<?php

namespace Tests\Feature;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * FR-36: tawaran rekalkulasi saat realisasi meleset.
 *
 * Skenario bersama, imbal hasil dan inflasi 0% supaya angkanya bisa dihitung
 * tangan: target 120 juta dalam 36 bulan → rencana 3.333.334 / bulan. Setahun
 * kemudian dana baru 10 juta (seharusnya 40 juta), sisa 24 bulan.
 */
class GoalRecalculationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function tujuanTertinggal(User $user): FinancialGoal
    {
        Carbon::setTestNow('2026-01-01 09:00:00');

        $this->actingAs($user)->post(route('goals.store'), [
            'name' => 'DP Rumah',
            'target_amount' => 120_000_000,
            'initial_amount' => 0,
            'target_date' => '2029-01-01',
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
        ])->assertSessionHasNoErrors();

        $goal = $user->goals()->sole();
        $this->assertSame('3333334.00', $goal->latestCalculation->monthly_contribution_required);

        Carbon::setTestNow('2027-01-01 09:00:00');
        $goal->update(['allocated_amount' => 10_000_000]);

        return $goal->fresh();
    }

    private function ringkasan(User $user): array
    {
        $halaman = $this->actingAs($user)->get(route('goals.index'))->assertOk();

        return $halaman->viewData('page')['props']['goals'][0];
    }

    public function test_tujuan_tertinggal_mendapat_tiga_tawaran(): void
    {
        $user = User::factory()->create();
        $this->tujuanTertinggal($user);

        $goal = $this->ringkasan($user);

        $this->assertSame('behind', $goal['on_track']['status']);
        $this->assertSame([
            'required_monthly_contribution' => 4_583_334,
            'planned_monthly_contribution' => 3_333_334.0,
            'options' => [
                // 110 jt / 24 bulan, dibulatkan ke atas.
                'contribution' => ['monthly_contribution' => 4_583_334],
                // 110 jt / 33 bulan = 3.333.333,33 → cukup dengan setoran lama.
                'date' => ['target_date' => '2029-10-01', 'months_added' => 9],
                // 10 jt + 3.333.334 × 24 = 90.000.016 → ke bawah ke ribuan.
                'target' => ['target_amount' => 90_000_000],
            ],
        ], $goal['recalculation']);
    }

    public function test_tujuan_sesuai_rencana_tidak_ditawari(): void
    {
        $user = User::factory()->create();
        $this->tujuanTertinggal($user)->update(['allocated_amount' => 45_000_000]);

        $goal = $this->ringkasan($user);

        $this->assertSame('on_track', $goal['on_track']['status']);
        $this->assertNull($goal['recalculation']);
    }

    public function test_menaikkan_setoran(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)
            ->post(route('goals.recalculate', $goal), ['option' => 'contribution'])
            ->assertSessionHasNoErrors()
            ->assertSessionHas('status', 'Setoran bulanan dinaikkan untuk "DP Rumah".');

        $goal->refresh();
        $this->assertSame('120000000.00', $goal->target_amount);
        $this->assertSame('2029-01-01', $goal->target_date->toDateString());
        $this->assertSame('4583334.00', $goal->latestCalculation->monthly_contribution_required);
        $this->assertSame(
            ['option' => 'contribution', 'baseline_amount' => 10_000_000],
            $goal->latestCalculation->calculation_snapshot['recalculation'],
        );

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'financial_goal_id' => $goal->id,
            'type' => 'goal_recalculated',
        ]);
    }

    /**
     * Inti alasan garis awal on-track dipindah: sesudah rencana baru diterima,
     * tujuannya tidak boleh tetap "tertinggal" dengan selisih yang sama dan
     * terus ditawari hal yang baru saja diterimanya.
     */
    public function test_sesudah_rekalkulasi_status_diukur_dari_rencana_baru(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)->post(route('goals.recalculate', $goal), ['option' => 'contribution']);

        $ringkasan = $this->ringkasan($user);
        $this->assertSame('on_track', $ringkasan['on_track']['status']);
        $this->assertNull($ringkasan['recalculation']);

        // Setengah jalan dari sisa 24 bulan: seharusnya 10 jt + ½ × 110 jt.
        Carbon::setTestNow('2028-01-01 09:00:00');
        $goal->update(['allocated_amount' => 60_000_000]);

        $ringkasan = $this->ringkasan($user);
        $this->assertSame('behind', $ringkasan['on_track']['status']);
        $this->assertEqualsWithDelta(5_000_000, $ringkasan['on_track']['gap_amount'], 100_000);
    }

    public function test_memundurkan_tanggal(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)
            ->post(route('goals.recalculate', $goal), ['option' => 'date'])
            ->assertSessionHasNoErrors();

        $goal->refresh();
        $this->assertSame('2029-10-01', $goal->target_date->toDateString());
        $this->assertSame('120000000.00', $goal->target_amount);
        $this->assertSame('3333334.00', $goal->latestCalculation->monthly_contribution_required);
    }

    public function test_menurunkan_target(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)
            ->post(route('goals.recalculate', $goal), ['option' => 'target'])
            ->assertSessionHasNoErrors();

        $goal->refresh();
        $this->assertSame('90000000.00', $goal->target_amount);
        $this->assertSame('2029-01-01', $goal->target_date->toDateString());
        $this->assertSame('3333334.00', $goal->latestCalculation->monthly_contribution_required);
    }

    /**
     * Angkanya dihitung ulang di server. Nominal yang dikirim bersama
     * pilihannya diabaikan — kalau tidak, siapa pun bisa "menurunkan" target
     * ke angka sembarang lewat satu permintaan.
     */
    public function test_nominal_dari_browser_diabaikan(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)->post(route('goals.recalculate', $goal), [
            'option' => 'target',
            'target_amount' => 1,
        ]);

        $this->assertSame('90000000.00', $goal->fresh()->target_amount);
    }

    public function test_pilihan_yang_tidak_lagi_tersedia_ditolak(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);
        // Di tab lain dana sudah dikejar — tawaran di halaman ini jadi basi.
        $goal->update(['allocated_amount' => 45_000_000]);
        $jumlahSnapshot = $goal->calculations()->count();

        $this->actingAs($user)
            ->post(route('goals.recalculate', $goal), ['option' => 'contribution'])
            ->assertSessionHasErrors('option');

        $this->assertSame($jumlahSnapshot, $goal->calculations()->count());
    }

    public function test_pilihan_tak_dikenal_ditolak(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuanTertinggal($user);

        $this->actingAs($user)
            ->post(route('goals.recalculate', $goal), ['option' => 'hapus-semua'])
            ->assertSessionHasErrors('option');
    }

    public function test_tidak_bisa_merekalkulasi_tujuan_orang_lain(): void
    {
        $goal = $this->tujuanTertinggal(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->post(route('goals.recalculate', $goal), ['option' => 'target'])
            ->assertForbidden();

        $this->assertSame('120000000.00', $goal->fresh()->target_amount);
    }

    public function test_tujuan_tanpa_tenggat_tidak_ditawari(): void
    {
        $user = User::factory()->create();
        $this->tujuanTertinggal($user)->update(['target_date' => null]);

        $this->assertNull($this->ringkasan($user)['recalculation']);
    }
}
