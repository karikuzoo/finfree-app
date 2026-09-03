<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\GoalContribution;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Mengubah & menghapus setoran (PRD FR-33) — disunting dari dialog tanggal di
 * kalender.
 */
class ContributionEditTest extends TestCase
{
    use RefreshDatabase;

    private function setoran(User $user, array $ubah = []): GoalContribution
    {
        $goal = $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => 'Dana Darurat',
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);

        return $goal->contributions()->create(array_merge([
            'amount' => 500000,
            'contributed_on' => Carbon::today()->toDateString(),
            'note' => 'Setoran awal',
        ], $ubah));
    }

    public function test_nominal_dan_catatan_dapat_diubah(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user);

        $this->actingAs($user)
            ->patch(route('goals.contributions.update', $setoran), [
                'amount' => 750000,
                'note' => 'Diperbaiki, harusnya 750rb',
            ])
            ->assertSessionHasNoErrors();

        $setoran->refresh();

        $this->assertSame('750000.00', $setoran->amount);
        $this->assertSame('Diperbaiki, harusnya 750rb', $setoran->note);
    }

    /**
     * Tanggal TIDAK ikut berubah meski dikirim. Setoran disunting dari dialog
     * tanggal; memindahkannya membuat barisnya lenyap dari dialog yang sedang
     * terbuka — pengguna menekan simpan lalu melihat entrinya hilang.
     */
    public function test_tanggal_tidak_ikut_berubah(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user, ['contributed_on' => '2026-09-01']);

        $this->actingAs($user)->patch(route('goals.contributions.update', $setoran), [
            'amount' => 500000,
            'contributed_on' => '2026-08-01',
        ]);

        $this->assertSame('2026-09-01', $setoran->fresh()->contributed_on->toDateString());
    }

    public function test_setoran_dapat_dihapus(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user);

        $this->actingAs($user)
            ->delete(route('goals.contributions.destroy', $setoran))
            ->assertSessionHasNoErrors();

        $this->assertSame(0, GoalContribution::count());
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7). Setoran tidak
     * menyimpan user_id sendiri — kepemilikannya diperiksa lewat tujuannya,
     * dan itu justru yang mudah terlewat.
     */
    public function test_tidak_bisa_menyentuh_setoran_orang_lain(): void
    {
        $setoran = $this->setoran(User::factory()->create());
        $penyusup = User::factory()->create();

        $this->actingAs($penyusup)
            ->patch(route('goals.contributions.update', $setoran), ['amount' => 1])
            ->assertForbidden();

        $this->actingAs($penyusup)
            ->delete(route('goals.contributions.destroy', $setoran))
            ->assertForbidden();

        $this->assertSame(1, GoalContribution::count());
        $this->assertSame('500000.00', $setoran->fresh()->amount);
    }

    public function test_menolak_nominal_yang_tidak_sah(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user);

        $this->actingAs($user)
            ->patch(route('goals.contributions.update', $setoran), ['amount' => 0])
            ->assertSessionHasErrors('amount');

        $this->assertSame('500000.00', $setoran->fresh()->amount);
    }

    public function test_tamu_tidak_bisa_mengubah_setoran(): void
    {
        $setoran = $this->setoran(User::factory()->create());

        $this->patch(route('goals.contributions.update', $setoran), ['amount' => 1])
            ->assertRedirect(route('login'));
    }

    /**
     * Kalender WAJIB mengirim id tiap setoran. Tanpa itu frontend tidak punya
     * pegangan untuk menyunting, dan tombolnya diam-diam hilang tanpa error.
     */
    public function test_kalender_mengirim_id_tiap_setoran(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user, ['contributed_on' => '2026-09-10']);

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-09-01'));

        $hari = collect($kalender['contributions'])->firstWhere('date', '2026-09-10');

        $this->assertSame($setoran->id, $hari['entries'][0]['id']);
    }

    /**
     * Setelah setoran diubah, ringkasan yang dibaca Dashboard harus ikut
     * berubah — tidak ada nilai yang di-cache di FinancialGoal.
     */
    public function test_ringkasan_ikut_berubah_setelah_setoran_diubah(): void
    {
        $user = User::factory()->create();
        $setoran = $this->setoran($user);

        $this->actingAs($user)->patch(route('goals.contributions.update', $setoran), [
            'amount' => 2000000,
        ]);

        $ringkasan = app(DashboardSummaryService::class)->forUser($user);

        $this->assertSame(2000000.0, $ringkasan['goals'][0]['current_amount']);
    }
}
