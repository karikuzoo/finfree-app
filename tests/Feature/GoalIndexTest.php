<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoalIndexTest extends TestCase
{
    use RefreshDatabase;

    private function buatTujuan(User $user, array $ubah = []): \App\Models\FinancialGoal
    {
        return $user->goals()->create(array_merge([
            'type' => GoalType::House->value,
            'name' => 'DP Rumah',
            'target_amount' => 200000000,
            'initial_amount' => 0,
            'target_date' => Carbon::now()->addYears(5)->toDateString(),
            'estimated_return_rate' => 7,
            'estimated_inflation_rate' => 3.5,
            'status' => GoalStatus::Active->value,
        ], $ubah));
    }

    public function test_tamu_tidak_bisa_membuka_daftar_tujuan(): void
    {
        $this->get(route('goals.index'))->assertRedirect(route('login'));
    }

    public function test_daftar_kosong_saat_belum_punya_tujuan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('goals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Goal/Index')
                ->has('goals', 0));
    }

    public function test_menampilkan_tujuan_beserta_progresnya(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user, ['initial_amount' => 50000000]);

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('goals', 1)
                ->where('goals.0.id', $goal->id)
                ->where('goals.0.name', 'DP Rumah')
                ->where('goals.0.current_amount', 50000000)
                ->where('goals.0.target_amount', 200000000)
                ->where('goals.0.progress_percentage', 25));
    }

    /**
     * Angka progres WAJIB identik dengan yang ditampilkan Dashboard. Keduanya
     * mengambil dari DashboardSummaryService; bila salah satunya suatu saat
     * menghitung sendiri, pengguna akan melihat dua nilai berbeda untuk tujuan
     * yang sama dan tidak tahu mana yang benar.
     */
    public function test_progres_konsisten_dengan_dashboard(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user, ['initial_amount' => 30000000]);
        $goal->contributions()->create([
            'amount' => 20000000,
            'contributed_on' => Carbon::now()->toDateString(),
        ]);

        $daftar = $this->actingAs($user)->get(route('goals.index'))
            ->viewData('page')['props']['goals'][0];

        $dashboard = $this->actingAs($user)->get(route('dashboard'))
            ->viewData('page')['props']['summary']['primary_goal'];

        $this->assertSame($dashboard['current_amount'], $daftar['current_amount']);
        $this->assertSame($dashboard['progress_percentage'], $daftar['progress_percentage']);
        $this->assertSame($dashboard['on_track'], $daftar['on_track']);
    }

    public function test_label_jenis_tujuan_dikirim_dari_enum(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('goals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('typeLabels.house', GoalType::House->label())
                ->where('typeLabels.emergency', GoalType::Emergency->label()));
    }

    public function test_ringkasan_keseluruhan_menjumlahkan_semua_tujuan(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, ['target_amount' => 100000000, 'initial_amount' => 25000000]);
        $this->buatTujuan($user, [
            'name' => 'Dana Darurat',
            'type' => GoalType::Emergency->value,
            'target_amount' => 60000000,
            'initial_amount' => 15000000,
            'target_date' => null,
        ]);

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertInertia(fn ($page) => $page
                ->has('goals', 2)
                ->where('totalTarget', 160000000)
                ->where('totalAssets', 40000000)
                ->where('overallProgress', 25));
    }

    /**
     * Tujuan tertua otomatis menjadi tujuan utama di Dashboard, jadi urutannya
     * bukan sekadar selera tampilan — halaman ini menandai yang pertama sebagai
     * "Tujuan Utama", dan penanda itu keliru bila urutannya berubah.
     */
    public function test_tujuan_diurutkan_dari_yang_paling_lama(): void
    {
        $user = User::factory()->create();

        Carbon::setTestNow('2026-01-10 08:00:00');
        $lama = $this->buatTujuan($user, ['name' => 'Dibuat Duluan']);

        Carbon::setTestNow('2026-06-20 08:00:00');
        $this->buatTujuan($user, ['name' => 'Dibuat Belakangan']);

        Carbon::setTestNow();

        $this->actingAs($user)
            ->get(route('goals.index'))
            ->assertInertia(fn ($page) => $page
                ->where('goals.0.id', $lama->id)
                ->where('goals.0.name', 'Dibuat Duluan'));
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tidak_menampilkan_tujuan_pengguna_lain(): void
    {
        $this->buatTujuan(User::factory()->create(), ['name' => 'Milik Orang Lain']);

        $this->actingAs(User::factory()->create())
            ->get(route('goals.index'))
            ->assertInertia(fn ($page) => $page->has('goals', 0));
    }

    public function test_setelah_membuat_tujuan_diarahkan_ke_daftar(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), [
                'type' => GoalType::Emergency->value,
                'name' => 'Dana Darurat',
                'target_amount' => 60000000,
                'estimated_return_rate' => 4,
            ])
            ->assertRedirect(route('goals.index'))
            ->assertSessionHas('status');
    }
}
