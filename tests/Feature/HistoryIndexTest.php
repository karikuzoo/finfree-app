<?php

namespace Tests\Feature;

use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryIndexTest extends TestCase
{
    use RefreshDatabase;

    private function catatAktivitas(User $user, array $ubah = []): UserActivity
    {
        return $user->activities()->create(array_merge([
            'type' => 'contribution_recorded',
            'goal_name' => 'Dana Darurat',
            'amount' => 50000,
        ], $ubah));
    }

    public function test_tamu_tidak_bisa_membuka_riwayat(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_kosong_saat_belum_ada_aktivitas(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities.data', []));
    }

    public function test_hanya_menampilkan_aktivitas_milik_pengguna_sendiri(): void
    {
        $user = User::factory()->create();
        $lainnya = User::factory()->create();

        $this->catatAktivitas($user, ['goal_name' => 'Punya Saya']);
        $this->catatAktivitas($lainnya, ['goal_name' => 'Punya Orang Lain']);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.goal_name', 'Punya Saya'));
    }

    public function test_terurut_dari_yang_paling_baru(): void
    {
        $user = User::factory()->create();

        $lama = $this->catatAktivitas($user, ['goal_name' => 'Lebih Dulu']);
        $lama->created_at = now()->subDays(2);
        $lama->save();

        $baru = $this->catatAktivitas($user, ['goal_name' => 'Belakangan']);
        $baru->created_at = now();
        $baru->save();

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->where('activities.data.0.goal_name', 'Belakangan')
                ->where('activities.data.1.goal_name', 'Lebih Dulu'));
    }

    public function test_dipaginasi_20_per_halaman(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $this->catatAktivitas($user);
        }

        $halamanSatu = $this->actingAs($user)
            ->get(route('history.index'))
            ->viewData('page')['props']['activities'];

        $this->assertCount(20, $halamanSatu['data']);
        $this->assertSame(1, $halamanSatu['current_page']);
        $this->assertSame(2, $halamanSatu['last_page']);
        $this->assertNotNull($halamanSatu['next_page_url']);
        $this->assertNull($halamanSatu['prev_page_url']);

        $halamanDua = $this->actingAs($user)
            ->get(route('history.index', ['page' => 2]))
            ->viewData('page')['props']['activities'];

        $this->assertCount(5, $halamanDua['data']);
        $this->assertNotNull($halamanDua['prev_page_url']);
    }

    public function test_bentuk_data_tiap_aktivitas(): void
    {
        $user = User::factory()->create();
        $this->catatAktivitas($user, [
            'type' => 'contribution_recorded',
            'goal_name' => 'Dana Darurat',
            'amount' => 75000,
        ]);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->where('activities.data.0.type', 'contribution_recorded')
                ->where('activities.data.0.goal_name', 'Dana Darurat')
                ->where('activities.data.0.amount', 75000)
                ->has('activities.data.0.occurred_at'));
    }
}
