<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class ProfileAvatarTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Disk palsu — test tidak boleh menulis ke storage sungguhan.
        Storage::fake('public');
    }

    public function test_pengguna_tanpa_foto_mendapat_inisial_nama(): void
    {
        $user = User::factory()->create(['name' => 'Muhammad Ihsan']);

        $this->assertNull($user->avatar_url);
        $this->assertSame('MI', $user->initials);
    }

    public function test_inisial_menangani_nama_satu_kata(): void
    {
        $this->assertSame(
            'B',
            User::factory()->create(['name' => 'Budi'])->initials,
        );
    }

    public function test_foto_dapat_diunggah_dan_diperkecil(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('foto.jpg', 1200, 800),
            ])
            ->assertSessionHasNoErrors()
            ->assertRedirect();

        $user->refresh();

        $this->assertNotNull($user->avatar_path);
        Storage::disk('public')->assertExists($user->avatar_path);

        // Gambar 1200×800 harus keluar sebagai persegi 256×256, bukan tersimpan
        // apa adanya — inilah yang membedakan menyimpan berkas dari mengolahnya.
        [$width, $height] = getimagesizefromstring(
            Storage::disk('public')->get($user->avatar_path),
        );

        $this->assertSame(256, $width);
        $this->assertSame(256, $height);
    }

    public function test_mengunggah_foto_baru_menghapus_berkas_lama(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('pertama.jpg', 400, 400),
        ]);
        $pertama = $user->refresh()->avatar_path;

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('kedua.jpg', 400, 400),
        ]);
        $kedua = $user->refresh()->avatar_path;

        $this->assertNotSame($pertama, $kedua);
        Storage::disk('public')->assertMissing($pertama);
        Storage::disk('public')->assertExists($kedua);
    }

    public function test_foto_dapat_dihapus(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ]);
        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)
            ->delete(route('profile.avatar.destroy'))
            ->assertRedirect();

        $this->assertNull($user->refresh()->avatar_path);
        Storage::disk('public')->assertMissing($path);
    }

    public function test_menghapus_akun_ikut_menghapus_berkas_fotonya(): void
    {
        // FR-37 menuntut penghapusan menyeluruh. Berkas foto yang tertinggal
        // adalah gambar wajah pengguna yang tidak lagi dimiliki siapa pun,
        // sehingga tak akan pernah dibersihkan oleh apa pun.
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ]);
        $path = $user->refresh()->avatar_path;

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

        Storage::disk('public')->assertMissing($path);
        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }

    public function test_menolak_berkas_yang_bukan_gambar(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->create('dokumen.pdf', 100, 'application/pdf'),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_menolak_gambar_yang_dimensinya_melampaui_batas(): void
    {
        $user = User::factory()->create();

        // Sengaja hanya lebarnya yang melewati batas (4100 × 10 piksel).
        // Versi pertama test ini memakai 5000 × 5000, dan PHP kehabisan memori
        // saat MEMBUAT gambar palsunya — sebelum validasi sempat berjalan.
        // Kejadian itu sekaligus bukti bahwa batas dimensi ini memang perlu.
        $this->actingAs($user)
            ->post(route('profile.avatar.update'), [
                'avatar' => UploadedFile::fake()->image('lebar.jpg', 4100, 10),
            ])
            ->assertSessionHasErrors('avatar');

        $this->assertNull($user->refresh()->avatar_path);
    }

    public function test_tamu_tidak_dapat_mengunggah_foto(): void
    {
        $this->post(route('profile.avatar.update'), [
            'avatar' => UploadedFile::fake()->image('foto.jpg', 400, 400),
        ])->assertRedirect(route('login'));
    }
}
