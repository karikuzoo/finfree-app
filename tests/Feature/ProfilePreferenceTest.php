<?php

namespace Tests\Feature;

use App\Enums\RiskProfile;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfilePreferenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_baru_mendapat_nilai_awal_yang_masuk_akal(): void
    {
        $user = User::factory()->create();

        $this->assertSame(RiskProfile::Moderate, $user->risk_profile);
        $this->assertSame('IDR', $user->currency_preference);
        $this->assertFalse($user->prefers_syariah);
    }

    public function test_preferensi_dapat_diperbarui(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.preferences.update'), [
                'risk_profile' => 'aggressive',
                'prefers_syariah' => true,
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertSame(RiskProfile::Aggressive, $user->risk_profile);
        $this->assertTrue($user->prefers_syariah);
    }

    public function test_menolak_profil_risiko_di_luar_daftar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.preferences.update'), [
                'risk_profile' => 'sangat-agresif',
                'prefers_syariah' => false,
            ])
            ->assertSessionHasErrors('risk_profile');

        $this->assertSame(RiskProfile::Moderate, $user->refresh()->risk_profile);
    }

    public function test_tamu_tidak_dapat_mengubah_preferensi(): void
    {
        $this->patch(route('profile.preferences.update'), [
            'risk_profile' => 'aggressive',
            'prefers_syariah' => true,
        ])->assertRedirect(route('login'));
    }

    public function test_halaman_profil_mengirim_daftar_profil_risiko(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertOk()
            ->assertInertia(
                fn ($page) => $page
                    ->has('riskProfiles', 3)
                    ->where('riskProfiles.0.value', 'conservative')
                    ->where('riskProfiles.0.label', 'Konservatif')
            );
    }

    /**
     * Penjaga koordinasi lintas fitur.
     *
     * Kolom `financial_goals.risk_profile_override` (FR-24) yang dikerjakan
     * terpisah wajib memakai nilai yang sama persis. Test ini mengunci
     * daftarnya, sehingga menambah atau mengganti nilai enum tidak bisa
     * dilakukan diam-diam oleh salah satu sisi.
     */
    public function test_nilai_enum_profil_risiko_terkunci(): void
    {
        $this->assertSame(
            ['conservative', 'moderate', 'aggressive'],
            RiskProfile::values(),
        );
    }
}
