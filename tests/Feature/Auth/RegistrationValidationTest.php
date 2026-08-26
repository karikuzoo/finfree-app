<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * Aturan validasi pendaftaran.
 *
 * Aturan kata sandi disetel sekali di AppServiceProvider lewat
 * `Password::defaults()`, sehingga berlaku sama di pendaftaran, reset lewat
 * email, dan ubah kata sandi di profil. Test terakhir di berkas ini menjaga
 * ketiganya tetap seragam — tanpa itu, seseorang bisa memasang kata sandi lemah
 * lewat jalur reset meski pendaftarannya ketat.
 */
class RegistrationValidationTest extends TestCase
{
    use RefreshDatabase;

    private function payload(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'password' => 'Rahasia123!',
            'password_confirmation' => 'Rahasia123!',
        ], $ubah);
    }

    public function test_pendaftaran_yang_benar_diterima(): void
    {
        $this->post(route('register'), $this->payload())
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('users', ['email' => 'budi@contoh.test']);
    }

    // ── Nama ────────────────────────────────────────────────────────────

    public static function namaDitolak(): array
    {
        return [
            'mengandung angka' => ['Budi 123'],
            'mengandung simbol' => ['Budi@Santoso'],
            'hanya angka' => ['12345'],
            'kosong' => [''],
        ];
    }

    /** @dataProvider namaDitolak */
    public function test_menolak_nama_yang_bukan_huruf(string $nama): void
    {
        $this->post(route('register'), $this->payload(['name' => $nama]))
            ->assertSessionHasErrors('name');

        $this->assertSame(0, User::count());
    }

    public static function namaDiterima(): array
    {
        return [
            'dua kata' => ['Budi Santoso'],
            'beraksen' => ['José Ramírez'],
            'apostrof' => ["Siti Nur'aini"],
            'gelar bertitik' => ['H. Ahmad Dahlan'],
            'tanda hubung' => ['Anne-Marie'],
        ];
    }

    /**
     * Nama sungguhan memuat spasi, apostrof, titik, dan tanda hubung.
     * Membatasi ke huruf saja terdengar rapi, tetapi berarti menolak orang
     * yang namanya memang begitu.
     *
     * @dataProvider namaDiterima
     */
    public function test_menerima_nama_yang_wajar(string $nama): void
    {
        $this->post(route('register'), $this->payload([
            'name' => $nama,
            'email' => 'unik'.md5($nama).'@contoh.test',
        ]))->assertSessionHasNoErrors();
    }

    // ── Email ───────────────────────────────────────────────────────────

    public static function emailDitolak(): array
    {
        return [
            'tanpa @' => ['budicontoh.test'],
            'tanpa domain' => ['budi@'],
            'spasi di tengah' => ['budi santoso@contoh.test'],
            'huruf besar' => ['Budi@Contoh.test'],
            'kosong' => [''],
        ];
    }

    /** @dataProvider emailDitolak */
    public function test_menolak_email_yang_formatnya_salah(string $email): void
    {
        $this->post(route('register'), $this->payload(['email' => $email]))
            ->assertSessionHasErrors('email');
    }

    public function test_menolak_email_yang_sudah_terdaftar(): void
    {
        User::factory()->create(['email' => 'budi@contoh.test']);

        $this->post(route('register'), $this->payload())
            ->assertSessionHasErrors('email');
    }

    // ── Kata sandi ──────────────────────────────────────────────────────

    public static function kataSandiDitolak(): array
    {
        return [
            'terlalu pendek' => ['Ab1!'],
            'tanpa huruf besar' => ['rahasia123!'],
            'tanpa huruf kecil' => ['RAHASIA123!'],
            'tanpa angka' => ['RahasiaAbc!'],
            'tanpa simbol' => ['Rahasia123'],
            'huruf saja' => ['rahasia'],
        ];
    }

    /** @dataProvider kataSandiDitolak */
    public function test_menolak_kata_sandi_yang_tidak_memenuhi_syarat(string $sandi): void
    {
        $this->post(route('register'), $this->payload([
            'password' => $sandi,
            'password_confirmation' => $sandi,
        ]))->assertSessionHasErrors('password');

        $this->assertSame(0, User::count());
    }

    public function test_menerima_kata_sandi_enam_karakter_yang_lengkap(): void
    {
        // Tepat di batas: 6 karakter, memuat keempat jenis.
        $this->post(route('register'), $this->payload([
            'password' => 'Ab1!cd',
            'password_confirmation' => 'Ab1!cd',
        ]))->assertSessionHasNoErrors();
    }

    public function test_menolak_konfirmasi_yang_tidak_cocok(): void
    {
        $this->post(route('register'), $this->payload([
            'password_confirmation' => 'Berbeda123!',
        ]))->assertSessionHasErrors('password');
    }

    // ── Keseragaman antar jalur ─────────────────────────────────────────

    public function test_aturan_kata_sandi_berlaku_juga_saat_mengubah_dari_profil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->put(route('password.update'), [
                'current_password' => 'password',
                'password' => 'lemah',
                'password_confirmation' => 'lemah',
            ])
            ->assertSessionHasErrors('password');

        $this->assertTrue(Hash::check('password', $user->refresh()->password));
    }

    public function test_pesan_validasi_berbahasa_indonesia(): void
    {
        $this->post(route('register'), $this->payload([
            'password' => 'abc',
            'password_confirmation' => 'abc',
        ]));

        $pesan = implode(' ', session('errors')->getBag('default')->all());

        $this->assertStringContainsString('Kata sandi', $pesan);
        $this->assertStringNotContainsString('The password field', $pesan);
    }
}
