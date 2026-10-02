<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Route yang menerima kata sandi dari orang yang sudah masuk dibatasi 6 kali
 * per menit — batas laju login tidak melindunginya. Lihat routes/auth.php.
 */
class SensitiveRouteThrottleTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{string, string, array<string, string>}> */
    public static function routeBerkataSandi(): array
    {
        return [
            'konfirmasi kata sandi' => ['post', '/confirm-password', ['password' => 'salah-tebak']],
            'ganti kata sandi' => ['put', '/password', [
                'current_password' => 'salah-tebak',
                'password' => 'Rahasia123!',
                'password_confirmation' => 'Rahasia123!',
            ]],
            'hapus akun' => ['delete', '/profile', ['password' => 'salah-tebak']],
        ];
    }

    /** @dataProvider routeBerkataSandi */
    public function test_tebakan_ketujuh_dalam_semenit_ditolak(string $metode, string $alamat, array $data): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 6; $i++) {
            $this->actingAs($user)->{$metode}($alamat, $data)->assertStatus(302);
        }

        $this->actingAs($user)->{$metode}($alamat, $data)->assertStatus(429);
        $this->assertNotNull($user->fresh(), 'Akun terhapus padahal kata sandinya salah.');
    }
}
