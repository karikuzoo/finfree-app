<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Password;
use Tests\TestCase;

/**
 * Dua hal yang dijaga di sini.
 *
 * 1. Meminta tautan reset yang baru membatalkan tautan sebelumnya. Ini
 *    perilaku keamanan yang benar (satu email, satu tautan aktif), tapi
 *    mudah rusak diam-diam kalau repositori token diganti. Test ini juga
 *    berfungsi sebagai dokumentasi: kebingungan nyata pernah terjadi karena
 *    tautan lama ditolak tanpa penjelasan.
 *
 * 2. Pesannya berbahasa Indonesia. Berkas lang/id/passwords.php dan
 *    lang/id/auth.php sempat tidak ada, sehingga Laravel jatuh ke pesan
 *    bawaan berbahasa Inggris karena APP_FALLBACK_LOCALE=en. Kegagalannya
 *    tidak menimbulkan error apa pun — pesannya hanya berganti bahasa.
 */
class PasswordResetMessageTest extends TestCase
{
    use RefreshDatabase;

    public function test_tautan_lama_ditolak_setelah_tautan_baru_diminta(): void
    {
        $user = User::factory()->create(['email' => 'orang@contoh.test']);

        $tokenLama = Password::createToken($user);
        $tokenBaru = Password::createToken($user);

        $this->assertNotSame($tokenLama, $tokenBaru);

        $this->post(route('password.store'), [
            'token' => $tokenLama,
            'email' => 'orang@contoh.test',
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ])->assertSessionHasErrors('email');
    }

    public function test_tautan_terbaru_tetap_berhasil(): void
    {
        $user = User::factory()->create(['email' => 'orang@contoh.test']);

        Password::createToken($user);
        $tokenBaru = Password::createToken($user);

        $this->post(route('password.store'), [
            'token' => $tokenBaru,
            'email' => 'orang@contoh.test',
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ])->assertSessionHasNoErrors();
    }

    public function test_pesan_token_tidak_berlaku_berbahasa_indonesia(): void
    {
        User::factory()->create(['email' => 'orang@contoh.test']);

        $this->post(route('password.store'), [
            'token' => 'token-yang-tidak-pernah-ada',
            'email' => 'orang@contoh.test',
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ]);

        $pesan = session('errors')->first('email');

        $this->assertStringContainsString('tidak berlaku', $pesan);
        $this->assertStringNotContainsString('This password reset token', $pesan);
    }

    public function test_pesan_gagal_masuk_berbahasa_indonesia(): void
    {
        User::factory()->create(['email' => 'orang@contoh.test']);

        $this->post(route('login'), [
            'email' => 'orang@contoh.test',
            'password' => 'salah-sekali',
        ]);

        $pesan = session('errors')->first('email');

        $this->assertStringNotContainsString('These credentials', $pesan);
        $this->assertStringContainsString('salah', $pesan);
    }

    /**
     * Pesan gagal masuk tidak boleh membedakan "email tidak terdaftar" dari
     * "kata sandi salah" — itu memberi tahu penyerang email mana yang punya
     * akun di sini (CLAUDE.md §10.4).
     */
    public function test_pesan_gagal_masuk_tidak_membocorkan_email_terdaftar(): void
    {
        User::factory()->create(['email' => 'terdaftar@contoh.test']);

        $this->post(route('login'), ['email' => 'terdaftar@contoh.test', 'password' => 'salah']);
        $pesanTerdaftar = session('errors')->first('email');

        $this->flushSession();

        $this->post(route('login'), ['email' => 'tidak-ada@contoh.test', 'password' => 'salah']);
        $pesanTidakTerdaftar = session('errors')->first('email');

        $this->assertSame($pesanTerdaftar, $pesanTidakTerdaftar);
    }
}
