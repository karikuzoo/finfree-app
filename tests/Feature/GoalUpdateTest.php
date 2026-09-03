<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoalUpdateTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTujuan(User $user, array $ubah = []): FinancialGoal
    {
        return $user->goals()->create(array_merge([
            'type' => GoalType::Custom->value,
            'name' => 'DP Rumah',
            'target_amount' => 200000000,
            'initial_amount' => 0,
            'target_date' => Carbon::now()->addYears(5)->toDateString(),
            'estimated_return_rate' => 5,
            'estimated_inflation_rate' => 3,
            'status' => GoalStatus::Active->value,
        ], $ubah));
    }

    private function payload(FinancialGoal $goal, array $ubah = []): array
    {
        return array_merge([
            'name' => $goal->name,
            'target_amount' => (float) $goal->target_amount,
            'initial_amount' => (float) $goal->initial_amount,
            'target_date' => $goal->target_date?->toDateString(),
            'estimated_return_rate' => (float) $goal->estimated_return_rate,
            'estimated_inflation_rate' => (float) $goal->estimated_inflation_rate,
        ], $ubah);
    }

    // ── Akses & kepemilikan ─────────────────────────────────────────────

    public function test_tamu_tidak_bisa_membuka_form_ubah(): void
    {
        $goal = $this->buatTujuan(User::factory()->create());

        $this->get(route('goals.edit', $goal))->assertRedirect(route('login'));
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tidak_bisa_mengubah_tujuan_orang_lain(): void
    {
        $goal = $this->buatTujuan(User::factory()->create(), ['name' => 'Punya Orang Lain']);
        $penyusup = User::factory()->create();

        $this->actingAs($penyusup)
            ->get(route('goals.edit', $goal))
            ->assertForbidden();

        $this->actingAs($penyusup)
            ->patch(route('goals.update', $goal), $this->payload($goal, ['name' => 'Dibajak']))
            ->assertForbidden();

        $this->assertSame('Punya Orang Lain', $goal->fresh()->name);
    }

    public function test_form_ubah_terisi_nilai_yang_tersimpan(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user, ['initial_amount' => 10000000]);

        $this->actingAs($user)
            ->get(route('goals.edit', $goal))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Goal/Edit')
                ->where('goal.name', 'DP Rumah')
                ->where('goal.target_amount', 200000000)
                ->where('goal.initial_amount', 10000000));
    }

    // ── Perubahan ───────────────────────────────────────────────────────

    public function test_perubahan_tersimpan(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, [
                'name' => 'DP Rumah Pertama',
                'target_amount' => 300000000,
            ]))
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goals.index'));

        $goal->refresh();

        $this->assertSame('DP Rumah Pertama', $goal->name);
        $this->assertSame('300000000.00', $goal->target_amount);
    }

    /**
     * Mengubah rencana TIDAK boleh menyentuh setoran yang sudah tercatat.
     * Riwayat itu satu-satunya bukti apa yang benar-benar sudah disisihkan
     * pengguna; kehilangannya jauh lebih mahal daripada salah target.
     */
    public function test_setoran_tidak_ikut_berubah(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);
        $goal->contributions()->create([
            'amount' => 5000000,
            'contributed_on' => Carbon::now()->toDateString(),
        ]);

        $this->actingAs($user)->patch(route('goals.update', $goal), $this->payload($goal, [
            'target_amount' => 999000000,
        ]));

        $this->assertSame(1, $goal->contributions()->count());
        $this->assertSame('5000000.00', $goal->contributions()->sole()->amount);
    }

    /**
     * Snapshot lama DIPERTAHANKAN, yang baru ditambahkan. formula_version dan
     * calculation_snapshot ada justru supaya angka yang pernah dijanjikan ke
     * pengguna tetap bisa dijelaskan kemudian (PRD D-6).
     */
    public function test_perhitungan_dihitung_ulang_tanpa_menghapus_yang_lama(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);
        $goal->calculations()->create([
            'monthly_contribution_required' => 1000000,
            'total_contribution_projection' => 60000000,
            'total_investment_growth_projection' => 0,
            'calculation_snapshot' => [],
            'formula_version' => 1,
        ]);

        $this->actingAs($user)->patch(route('goals.update', $goal), $this->payload($goal, [
            'target_amount' => 400000000,
        ]));

        $this->assertSame(2, $goal->calculations()->count());
        $this->assertNotSame(
            '1000000.00',
            $goal->fresh()->latestCalculation->monthly_contribution_required,
            'Snapshot terbaru seharusnya hasil hitungan ulang, bukan yang lama.',
        );
    }

    public function test_tujuan_bisa_diubah_menjadi_tanpa_tenggat(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, ['target_date' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull($goal->fresh()->target_date);
    }

    // ── Validasi ────────────────────────────────────────────────────────

    /**
     * JEBAKAN yang melahirkan UpdateGoalRequest tersendiri.
     *
     * Aturan `after:today` milik StoreGoalRequest akan menolak tujuan yang
     * tenggatnya sudah lewat — sehingga pengguna yang cuma ingin memperbaiki
     * salah ketik pada NAMA tidak bisa menyimpan apa pun, ditolak oleh kolom
     * yang bahkan tidak ia sentuh. Yang dilarang seharusnya menyetel tenggat
     * BARU ke masa lalu, bukan membiarkan yang sudah ada.
     */
    public function test_tujuan_yang_tenggatnya_sudah_lewat_masih_bisa_diubah(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-01-10 08:00:00');
        $goal = $this->buatTujuan($user, ['target_date' => '2026-03-01']);

        Carbon::setTestNow('2026-09-03 08:00:00'); // Tenggatnya sudah lewat.

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, [
                'name' => 'Nama Diperbaiki',
            ]))
            ->assertSessionHasNoErrors();

        $this->assertSame('Nama Diperbaiki', $goal->fresh()->name);
    }

    public function test_menolak_tenggat_baru_di_masa_lalu(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, [
                'target_date' => Carbon::now()->subMonth()->toDateString(),
            ]))
            ->assertSessionHasErrors('target_date');
    }

    public function test_menolak_nama_kosong(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, ['name' => '']))
            ->assertSessionHasErrors('name');
    }

    public function test_menolak_imbal_hasil_yang_tidak_masuk_akal(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.update', $goal), $this->payload($goal, [
                'estimated_return_rate' => 45,
            ]))
            ->assertSessionHasErrors('estimated_return_rate');
    }
}
