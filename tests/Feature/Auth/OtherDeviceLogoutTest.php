<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Mengganti kata sandi mengeluarkan sesi lain, tetapi tidak sesi yang
 * menggantinya. Sesi "perangkat lain" ditiru dengan sesi yang masih
 * menyimpan sidik kata sandi lama — itulah yang dicocokkan
 * AuthenticateSession di setiap permintaan.
 */
class OtherDeviceLogoutTest extends TestCase
{
    use RefreshDatabase;

    public function test_sesi_yang_mengganti_kata_sandi_tetap_masuk(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'Rahasia123!',
                'password_confirmation' => 'Rahasia123!',
            ])
            ->assertSessionHasNoErrors();

        $this->get(route('dashboard'))->assertOk();
        $this->assertAuthenticatedAs($user);
    }

    public function test_sesi_dengan_kata_sandi_lama_dikeluarkan(): void
    {
        $user = User::factory()->create();
        $sidikLama = $user->password;

        // Perangkat lain mengganti kata sandinya.
        $user->forceFill(['password' => Hash::make('Rahasia123!')])->save();

        $this->actingAs($user)
            ->withSession(['password_hash_web' => $sidikLama])
            ->get(route('dashboard'))
            ->assertRedirect(route('login'));

        $this->assertGuest();
    }
}
