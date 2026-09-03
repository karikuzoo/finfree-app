<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;
use ZipArchive;

/**
 * Ekspor data tujuan sebagai satu berkas Excel (PRD FR-38).
 *
 * Berkas .xlsx pada dasarnya adalah zip berisi XML, jadi isinya diperiksa
 * dengan membongkar zip-nya — bukan dengan pustaka pembaca spreadsheet
 * tersendiri yang hanya akan menambah dependensi demi pengujian.
 */
class GoalExportTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    private function buatTujuan(User $user, string $nama = 'DP Rumah'): FinancialGoal
    {
        $goal = $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => $nama,
            'target_amount' => 200000000,
            'initial_amount' => 10000000,
            'target_date' => '2031-01-01',
            'estimated_return_rate' => 5,
            'estimated_inflation_rate' => 3,
            'status' => GoalStatus::Active->value,
        ]);

        $goal->contributions()->create([
            'amount' => 1500000,
            'contributed_on' => '2026-09-01',
            'note' => 'Bonus tahunan',
        ]);

        return $goal;
    }

    /** Simpan respons unduhan ke berkas sementara agar bisa dibongkar. */
    private function unduh(User $user): string
    {
        $response = $this->actingAs($user)->get(route('goals.export'));
        $response->assertOk();

        $jalur = tempnam(sys_get_temp_dir(), 'uji-ekspor-').'.xlsx';
        file_put_contents($jalur, $response->streamedContent());

        return $jalur;
    }

    /** Seluruh teks di dalam berkas xlsx, digabung jadi satu. */
    private function isiBerkas(string $jalur): string
    {
        $zip = new ZipArchive();
        $this->assertTrue($zip->open($jalur) === true, 'Berkas xlsx tidak bisa dibuka sebagai zip.');

        $teks = '';
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $teks .= $zip->getFromIndex($i);
        }
        $zip->close();

        return $teks;
    }

    private function namaSheet(string $jalur): array
    {
        $zip = new ZipArchive();
        $zip->open($jalur);
        $workbook = $zip->getFromName('xl/workbook.xml');
        $zip->close();

        preg_match_all('/<sheet name="([^"]+)"/', $workbook, $cocok);

        return $cocok[1];
    }

    // ── Akses ───────────────────────────────────────────────────────────

    public function test_tamu_tidak_bisa_mengunduh(): void
    {
        $this->get(route('goals.export'))->assertRedirect(route('login'));
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7) — dan pada ekspor,
     * satu kesalahan penyaringan membocorkan SELURUH riwayat orang lain
     * sekaligus dalam satu berkas.
     */
    public function test_hanya_memuat_data_milik_sendiri(): void
    {
        $this->buatTujuan(User::factory()->create(), 'Punya Orang Lain');
        $saya = User::factory()->create();
        $this->buatTujuan($saya, 'Punya Saya');

        $isi = $this->isiBerkas($this->unduh($saya));

        $this->assertStringContainsString('Punya Saya', $isi);
        $this->assertStringNotContainsString('Punya Orang Lain', $isi);
    }

    // ── Bentuk berkas ───────────────────────────────────────────────────

    public function test_berkas_punya_tiga_sheet(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $this->assertSame(
            ['Ringkasan', 'Tujuan', 'Setoran'],
            $this->namaSheet($this->unduh($user)),
        );
    }

    public function test_berkas_diunduh_dengan_nama_bertanggal(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $disposisi = $this->actingAs($user)
            ->get(route('goals.export'))
            ->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $disposisi);
        $this->assertStringContainsString(
            'fingoal-'.now()->format('Y-m-d').'.xlsx',
            $disposisi,
        );
    }

    // ── Isi ─────────────────────────────────────────────────────────────

    public function test_memuat_tujuan_beserta_angkanya(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $isi = $this->isiBerkas($this->unduh($user));

        foreach (['DP Rumah', '200000000', '2031-01-01', 'Bonus tahunan'] as $harus) {
            $this->assertStringContainsString($harus, $isi, "Tidak menemukan: {$harus}");
        }
    }

    /**
     * INTI keputusan lingkup ekspor ini.
     *
     * Berkas unduhan gampang tersimpan bertahun-tahun di folder Downloads,
     * terkirim ke orang lain, atau ikut tersalin ke cadangan awan. Data
     * identitas tidak dibutuhkan pengguna untuk mengolah catatan keuangannya
     * sendiri, jadi ia tidak pernah ikut — dan hash kata sandi jelas tidak.
     */
    public function test_tidak_memuat_data_profil_maupun_kredensial(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
            'phone' => '0812 3456 7890',
            'occupation' => 'Karyawan swasta',
        ]);
        $this->buatTujuan($user);

        $isi = $this->isiBerkas($this->unduh($user));

        foreach ([
            'budi@contoh.test',
            '0812 3456 7890',
            'Karyawan swasta',
            $user->password,
        ] as $rahasia) {
            $this->assertStringNotContainsString(
                $rahasia,
                $isi,
                "Data profil/kredensial bocor ke berkas ekspor: {$rahasia}",
            );
        }
    }

    public function test_pengguna_tanpa_tujuan_tetap_mendapat_berkas_utuh(): void
    {
        $user = User::factory()->create();

        // Berkas kosong tetap harus punya ketiga sheet — pengguna yang belum
        // punya tujuan sebaiknya menerima berkas yang wajar, bukan galat.
        $this->assertSame(
            ['Ringkasan', 'Tujuan', 'Setoran'],
            $this->namaSheet($this->unduh($user)),
        );
    }

    /**
     * Jumlah kueri tidak boleh naik seiring banyaknya tujuan. Ekspor menyentuh
     * seluruh riwayat sekaligus — justru di sinilah N+1 paling terasa.
     */
    public function test_jumlah_kueri_tidak_bertambah_seiring_jumlah_tujuan(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, 'Satu');

        $hitung = function () use ($user) {
            $n = 0;
            \Illuminate\Support\Facades\DB::listen(function () use (&$n) {
                $n++;
            });
            $this->actingAs($user)->get(route('goals.export'))->streamedContent();

            return $n;
        };

        $satu = $hitung();

        foreach (['Dua', 'Tiga', 'Empat'] as $nama) {
            $this->buatTujuan($user, $nama);
        }

        $this->assertSame(
            $satu,
            $hitung(),
            'Jumlah kueri naik saat tujuan bertambah — tanda N+1.',
        );
    }
}
