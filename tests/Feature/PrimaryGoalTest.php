<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Memilih tujuan utama secara manual.
 *
 * Sebelumnya tujuan utama selalu yang TERTUA dan tidak bisa diubah — tujuan
 * yang paling dipedulikan pengguna bisa tertimbun hanya karena dibuat
 * belakangan.
 */
class PrimaryGoalTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTujuan(User $user, string $nama, string $dibuatPada): FinancialGoal
    {
        $sebelumnya = Carbon::getTestNow();
        Carbon::setTestNow($dibuatPada);

        $goal = $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => $nama,
            'target_amount' => 100000000,
            'initial_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);

        Carbon::setTestNow($sebelumnya);

        return $goal;
    }

    private function utama(User $user): ?array
    {
        return app(DashboardSummaryService::class)->forUser($user)['primary_goal'];
    }

    // ── Perilaku bawaan ─────────────────────────────────────────────────

    /**
     * Tanpa pilihan, tujuan tertua tetap yang utama — perilaku yang berlaku
     * sebelum kolom pilihan ada, sehingga data lama tidak perlu dimigrasi.
     */
    public function test_tanpa_memilih_tujuan_tertua_yang_jadi_utama(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, 'Dibuat Duluan', '2026-01-10 08:00:00');
        $this->buatTujuan($user, 'Dibuat Belakangan', '2026-06-20 08:00:00');

        $this->assertNull($user->primary_goal_id);
        $this->assertSame('Dibuat Duluan', $this->utama($user)['name']);
    }

    // ── Memilih ─────────────────────────────────────────────────────────

    public function test_pengguna_dapat_memilih_tujuan_utama(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, 'Dibuat Duluan', '2026-01-10 08:00:00');
        $baru = $this->buatTujuan($user, 'Dibuat Belakangan', '2026-06-20 08:00:00');

        $this->actingAs($user)
            ->patch(route('goals.primary', $baru))
            ->assertSessionHasNoErrors();

        $this->assertSame($baru->id, $user->fresh()->primary_goal_id);
        $this->assertSame('Dibuat Belakangan', $this->utama($user->fresh())['name']);
    }

    /**
     * Satu kolom di users, bukan penanda per tujuan — memilih yang baru
     * otomatis melepas yang lama, tanpa ada jalur kode yang bisa lupa
     * membersihkannya.
     */
    public function test_memilih_yang_baru_melepas_yang_lama(): void
    {
        $user = User::factory()->create();
        $satu = $this->buatTujuan($user, 'Satu', '2026-01-10 08:00:00');
        $dua = $this->buatTujuan($user, 'Dua', '2026-06-20 08:00:00');

        $this->actingAs($user)->patch(route('goals.primary', $satu));
        $this->actingAs($user)->patch(route('goals.primary', $dua));

        $this->assertSame($dua->id, $user->fresh()->primary_goal_id);
    }

    public function test_urutan_daftar_tidak_berubah_saat_utama_dipindah(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, 'Dibuat Duluan', '2026-01-10 08:00:00');
        $baru = $this->buatTujuan($user, 'Dibuat Belakangan', '2026-06-20 08:00:00');

        $this->actingAs($user)->patch(route('goals.primary', $baru));

        $this->actingAs($user->fresh())
            ->get(route('goals.index'))
            ->assertInertia(fn ($page) => $page
                // Daftar tetap urut menurut created_at…
                ->where('goals.0.name', 'Dibuat Duluan')
                // …tetapi penanda utama menunjuk yang dipilih.
                ->where('primaryGoalId', $baru->id));
    }

    // ── Kasus yang mudah terlewat ───────────────────────────────────────

    /**
     * Menghapus tujuan yang sedang jadi utama tidak boleh menggagalkan
     * penghapusannya maupun merusak Dashboard. Foreign key-nya nullOnDelete,
     * sehingga pilihannya lepas sendiri dan sistem kembali ke tujuan tertua.
     */
    public function test_menghapus_tujuan_utama_mengembalikan_ke_yang_tertua(): void
    {
        $user = User::factory()->create();
        $lama = $this->buatTujuan($user, 'Dibuat Duluan', '2026-01-10 08:00:00');
        $baru = $this->buatTujuan($user, 'Dibuat Belakangan', '2026-06-20 08:00:00');

        $this->actingAs($user)->patch(route('goals.primary', $baru));
        $this->actingAs($user->fresh())->delete(route('goals.destroy', $baru));

        $user = $user->fresh();

        $this->assertNull($user->primary_goal_id);
        $this->assertSame($lama->id, $this->utama($user)['id']);
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7). Menunjuk tujuan
     * orang lain sebagai utama akan membocorkan nama dan nominalnya ke
     * Dashboard penyusup.
     */
    public function test_tidak_bisa_menunjuk_tujuan_orang_lain(): void
    {
        $milikOrangLain = $this->buatTujuan(
            User::factory()->create(),
            'Rahasia',
            '2026-01-10 08:00:00',
        );
        $penyusup = User::factory()->create();

        $this->actingAs($penyusup)
            ->patch(route('goals.primary', $milikOrangLain))
            ->assertForbidden();

        $this->assertNull($penyusup->fresh()->primary_goal_id);
    }

    public function test_tamu_tidak_bisa_memilih_tujuan_utama(): void
    {
        $goal = $this->buatTujuan(User::factory()->create(), 'Coba', '2026-01-10 08:00:00');

        $this->patch(route('goals.primary', $goal))->assertRedirect(route('login'));
    }

    /**
     * primary_goal_id sengaja di luar $fillable. Bila suatu saat dimasukkan,
     * ia bisa ikut terbawa payload pembaruan profil — dan seseorang bisa
     * menunjuk tujuan milik orang lain tanpa melewati pemeriksaan kepemilikan.
     */
    public function test_tujuan_utama_tidak_bisa_diubah_lewat_mass_assignment(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user, 'Coba', '2026-01-10 08:00:00');

        $user->update(['primary_goal_id' => $goal->id]);

        $this->assertNull($user->fresh()->primary_goal_id);
    }
}
