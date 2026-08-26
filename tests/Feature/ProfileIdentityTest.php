<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Data identitas di halaman profil (PRD FR-3).
 *
 * Seluruhnya opsional — itu bagian dari prinsip minimasi data UU 27/2022 PDP,
 * bukan sekadar kemudahan. Test pertama menjaga sifat opsional itu supaya tidak
 * berubah jadi wajib tanpa disadari.
 */
class ProfileIdentityTest extends TestCase
{
    use RefreshDatabase;

    private function dasar(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
        ], $ubah);
    }

    public function test_seluruh_data_identitas_boleh_dikosongkan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar())
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertNull($user->birth_date);
        $this->assertNull($user->phone);
        $this->assertNull($user->occupation);
        $this->assertNull($user->nationality);
    }

    public function test_data_identitas_tersimpan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar([
                'birth_date' => '1995-08-17',
                'nationality' => 'Indonesia',
                'phone' => '0812 3456 7890',
                'occupation' => 'Karyawan swasta',
            ]))
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame('1995-08-17', $user->birth_date->toDateString());
        $this->assertSame('Indonesia', $user->nationality);
        $this->assertSame('0812 3456 7890', $user->phone);
        $this->assertSame('Karyawan swasta', $user->occupation);
    }

    public function test_tanggal_lahir_dikirim_ke_frontend_dalam_format_input_date(): void
    {
        // <input type="date"> hanya menerima "YYYY-MM-DD". Kalau cast-nya
        // berubah jadi datetime penuh, fieldnya tampil kosong meski datanya ada.
        $user = User::factory()->create(['birth_date' => '1995-08-17']);

        $this->actingAs($user)
            ->get(route('profile.edit'))
            ->assertInertia(
                fn ($page) => $page->where('auth.user.birth_date', '1995-08-17')
            );
    }

    public static function tanggalLahirDitolak(): array
    {
        return [
            'belum 17 tahun' => [now()->subYears(10)->toDateString()],
            'masa depan' => [now()->addYear()->toDateString()],
            'tahun tidak masuk akal' => ['0195-01-01'],
            'bukan tanggal' => ['kemarin'],
        ];
    }

    /** @dataProvider tanggalLahirDitolak */
    public function test_menolak_tanggal_lahir_yang_tidak_wajar(string $tanggal): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar(['birth_date' => $tanggal]))
            ->assertSessionHasErrors('birth_date');

        $this->assertNull($user->refresh()->birth_date);
    }

    public static function nomorTeleponDiterima(): array
    {
        return [
            'awalan nol' => ['081234567890'],
            'berspasi' => ['0812 3456 7890'],
            'kode negara' => ['+62 812 3456 7890'],
            'bertanda hubung' => ['021-5551234'],
            'berkurung' => ['(021) 5551234'],
        ];
    }

    /**
     * Nomor Indonesia ditulis bermacam-macam. Menolak salah satu bentuk yang
     * sah lebih merugikan daripada menerima yang tidak rapi.
     *
     * @dataProvider nomorTeleponDiterima
     */
    public function test_menerima_berbagai_penulisan_nomor_telepon(string $nomor): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar(['phone' => $nomor]))
            ->assertSessionHasNoErrors();

        $this->assertSame($nomor, $user->refresh()->phone);
    }

    public function test_menolak_nomor_telepon_berisi_huruf(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar(['phone' => '0812-HUBUNGI']))
            ->assertSessionHasErrors('phone');
    }

    public function test_menolak_kewarganegaraan_berisi_angka(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar(['nationality' => 'Indonesia 62']))
            ->assertSessionHasErrors('nationality');
    }

    public function test_aturan_nama_di_profil_sama_dengan_saat_mendaftar(): void
    {
        // Kalau di sini lebih longgar, pengguna bisa mendaftar dengan nama yang
        // sah lalu menggantinya dengan apa pun lewat halaman profil.
        $user = User::factory()->create();

        $this->actingAs($user)
            ->patch(route('profile.update'), $this->dasar(['name' => 'Budi 123']))
            ->assertSessionHasErrors('name');
    }

    public function test_data_identitas_ikut_terhapus_saat_akun_dihapus(): void
    {
        $user = User::factory()->create(['phone' => '081234567890']);

        $this->actingAs($user)->delete(route('profile.destroy'), [
            'password' => 'password',
        ]);

        $this->assertDatabaseMissing('users', ['id' => $user->id]);
    }
}
