<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Header keamanan dari App\Http\Middleware\SecurityHeaders.
 */
class SecurityHeadersTest extends TestCase
{
    use RefreshDatabase;

    private function assertHeaderKeamanan($response): void
    {
        $response
            ->assertHeader('X-Frame-Options', 'DENY')
            ->assertHeader('Content-Security-Policy', "frame-ancestors 'none'")
            ->assertHeader('X-Content-Type-Options', 'nosniff')
            ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
    }

    public function test_halaman_publik_membawa_header_keamanan(): void
    {
        $this->assertHeaderKeamanan($this->get(route('home')));
    }

    public function test_halaman_setelah_masuk_membawa_header_keamanan(): void
    {
        $this->assertHeaderKeamanan(
            $this->actingAs(User::factory()->create())->get(route('dashboard')),
        );
    }

    public function test_halaman_error_juga_membawa_header_keamanan(): void
    {
        $this->assertHeaderKeamanan($this->get('/alamat-yang-tidak-ada')->assertNotFound());
    }

    public function test_hsts_tidak_dikirim_di_luar_produksi(): void
    {
        // HSTS di mesin pengembangan membuat browser menolak http://127.0.0.1.
        $this->get('https://localhost/')->assertHeaderMissing('Strict-Transport-Security');
    }
}
