<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

/**
 * Ekspor data tujuan (PRD FR-38).
 */
class GoalExportTest extends TestCase
{
    use RefreshDatabase;

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

    private function isi($response): string
    {
        ob_start();
        $response->sendContent();

        return ob_get_clean();
    }

    // ── Akses ───────────────────────────────────────────────────────────

    public function test_tamu_tidak_bisa_mengunduh(): void
    {
        $this->get(route('goals.export.json'))->assertRedirect(route('login'));
        $this->get(route('goals.export.csv'))->assertRedirect(route('login'));
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

        $isi = $this->isi($this->actingAs($saya)->get(route('goals.export.json')));

        $this->assertStringContainsString('Punya Saya', $isi);
        $this->assertStringNotContainsString('Punya Orang Lain', $isi);
    }

    // ── JSON ────────────────────────────────────────────────────────────

    public function test_json_memuat_tujuan_setoran_dan_perhitungan(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);
        $goal->calculations()->create([
            'monthly_contribution_required' => 3000000,
            'total_contribution_projection' => 180000000,
            'total_investment_growth_projection' => 20000000,
            'calculation_snapshot' => [],
            'formula_version' => 1,
        ]);

        $data = json_decode(
            $this->isi($this->actingAs($user)->get(route('goals.export.json'))),
            true,
        );

        $tujuan = $data['tujuan'][0];

        $this->assertSame('DP Rumah', $tujuan['nama']);
        // JSON menulis float bulat tanpa desimal, jadi terbaca integer.
        $this->assertSame(200000000, $tujuan['nominal_target']);
        $this->assertSame('Bonus tahunan', $tujuan['setoran'][0]['catatan']);
        $this->assertSame(3000000, $tujuan['perhitungan'][0]['setoran_bulanan_dibutuhkan']);
        $this->assertSame(1, $tujuan['perhitungan'][0]['versi_rumus']);
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
            'nationality' => 'Indonesia',
            'occupation' => 'Karyawan swasta',
        ]);
        $this->buatTujuan($user);

        $isi = $this->isi($this->actingAs($user)->get(route('goals.export.json')));

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

    // ── CSV ─────────────────────────────────────────────────────────────

    public function test_csv_memuat_baris_setoran(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $isi = $this->isi($this->actingAs($user)->get(route('goals.export.csv')));

        $this->assertStringContainsString('Tanggal,Tujuan,Nominal,Catatan', $isi);
        $this->assertStringContainsString('2026-09-01,"DP Rumah",1500000,"Bonus tahunan"', $isi);
    }

    /**
     * BOM UTF-8 wajib ada. Tanpanya Excel di Windows membaca berkas sebagai
     * ANSI, dan setiap huruf beraksen maupun tanda kutip lengkung pada catatan
     * berubah menjadi karakter aneh — kerusakan yang baru terlihat setelah
     * berkasnya dibuka pengguna, bukan saat diunduh.
     */
    public function test_csv_diawali_bom_utf8(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $isi = $this->isi($this->actingAs($user)->get(route('goals.export.csv')));

        $this->assertStringStartsWith("\xEF\xBB\xBF", $isi);
    }

    public function test_berkas_diunduh_bukan_ditampilkan(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $response = $this->actingAs($user)->get(route('goals.export.json'));

        $disposisi = $response->headers->get('content-disposition');

        $this->assertStringContainsString('attachment', $disposisi);
        $this->assertStringContainsString(
            'fingoal-tujuan-'.now()->format('Y-m-d').'.json',
            $disposisi,
        );
    }

    public function test_pengguna_tanpa_tujuan_tetap_mendapat_berkas_kosong(): void
    {
        $user = User::factory()->create();

        $data = json_decode(
            $this->isi($this->actingAs($user)->get(route('goals.export.json'))),
            true,
        );

        $this->assertSame([], $data['tujuan']);
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
            $this->isi($this->actingAs($user)->get(route('goals.export.json')));

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

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }
}
