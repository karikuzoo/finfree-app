<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Menjaga dua hal yang gampang rusak diam-diam.
 *
 * 1. Verifikasi email. Middleware `verified` pada route dashboard hanya
 *    berfungsi bila model User mengimplementasikan MustVerifyEmail. Kalau
 *    baris `implements MustVerifyEmail` dihapus, middleware itu berubah jadi
 *    hiasan yang meloloskan semua orang — tanpa error, tanpa peringatan.
 *    Repo ini sempat dalam keadaan tersebut. Test di bawah membuatnya
 *    ketahuan seketika bila terulang.
 *
 * 2. Batas laju pada endpoint tamu (PRD FR-40). Register tanpa batas laju
 *    berarti siapa pun bisa membanjiri database dengan akun sampah.
 */
class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_pengguna_belum_terverifikasi_tertahan_dari_dashboard(): void
    {
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertRedirect(route('verification.notice'));
    }

    public function test_pengguna_terverifikasi_dapat_membuka_dashboard(): void
    {
        $user = User::factory()->create(); // factory default: sudah terverifikasi

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk();
    }

    public function test_halaman_publik_tetap_terbuka_bagi_yang_belum_terverifikasi(): void
    {
        // Kalkulator adalah pintu masuk pengguna baru (FR-44). Verifikasi email
        // tidak boleh ikut menutupnya.
        $user = User::factory()->unverified()->create();

        $this->actingAs($user)->get(route('home'))->assertOk();
        $this->actingAs($user)->get(route('calculator.goal'))->assertOk();
        $this->actingAs($user)->get(route('news.index'))->assertOk();
    }

    public function test_register_dibatasi_laju(): void
    {
        // Payload sengaja dibuat gagal validasi (password terlalu pendek).
        //
        // Alasannya: kalau pendaftaran berhasil, penggunanya langsung login,
        // dan permintaan berikutnya ditolak middleware `guest` sebelum sempat
        // dihitung pembatas laju — jadi yang teruji malah bukan pembatasnya.
        //
        // Memakai payload gagal juga lebih dekat dengan penyalahgunaan nyata:
        // penyerang tidak perlu berhasil mendaftar untuk menghabiskan sumber
        // daya server.
        $payload = [
            'name' => 'Pengguna',
            'email' => 'pengguna@contoh.test',
            'password' => 'x',
            'password_confirmation' => 'x',
        ];

        // Batasnya 5 per menit; percobaan keenam harus ditolak.
        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('register'), $payload)->assertStatus(302);
        }

        $this->post(route('register'), $payload)->assertStatus(429);

        $this->assertSame(0, User::count());
    }

    public function test_permintaan_tautan_reset_dibatasi_laju(): void
    {
        User::factory()->create(['email' => 'orang@contoh.test']);

        for ($i = 1; $i <= 5; $i++) {
            $this->post(route('password.email'), ['email' => 'orang@contoh.test']);
        }

        $this->post(route('password.email'), ['email' => 'orang@contoh.test'])
            ->assertStatus(429);
    }
}
