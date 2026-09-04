<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Perbandingan alokasi yang disarankan dengan yang benar-benar dipegang
 * pengguna (PRD FR-52..FR-56).
 *
 * Alokasi nyata dicatat di halaman Dompet sebagai nominal rupiah per
 * instrumen; saran datang dari InvestmentAllocationService sebagai persentase.
 * Yang diuji di sini adalah penyamaan keduanya — titik paling mudah keliru.
 */
class AllocationComparisonTest extends TestCase
{
    use RefreshDatabase;

    private function buatTujuan(User $user, ?array $alokasi = null): FinancialGoal
    {
        return $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => 'Dana Darurat',
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'target_date' => null,
            'estimated_return_rate' => 4,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'asset_allocation' => $alokasi,
        ]);
    }

    private function banding(User $user): array
    {
        return app(DashboardSummaryService::class)
            ->forUser($user)['goals'][0]['allocation_comparison'];
    }

    private function baris(array $banding, string $instrumen): ?array
    {
        return collect($banding['rows'])->firstWhere('instrument', $instrumen);
    }

    // ── Belum mencatat apa pun ──────────────────────────────────────────

    /**
     * Tanpa catatan alokasi, `has_actual` false — frontend memakainya untuk
     * tetap menggambar saran alih-alih pai kosong, yang terbaca sebagai
     * kerusakan dan bukan sebagai "belum diisi".
     */
    public function test_tanpa_catatan_alokasi_ditandai_belum_ada(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user);

        $banding = $this->banding($user);

        $this->assertFalse($banding['has_actual']);
        $this->assertSame(0.0, $banding['total_actual']);
    }

    public function test_alokasi_bernilai_nol_semua_tetap_dianggap_belum_ada(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, [
            'tabungan' => 0, 'saham' => 0, 'obligasi' => 0,
            'deposito' => 0, 'emas' => 0, 'custom' => [],
        ]);

        $this->assertFalse($this->banding($user)['has_actual']);
    }

    // ── Perhitungan persentase ──────────────────────────────────────────

    /**
     * Penyebutnya total yang DIALOKASIKAN, bukan current_amount. Pengguna yang
     * baru menempatkan separuh dananya akan melihat semua persentasenya timpang
     * tanpa tahu sebabnya bila memakai current_amount.
     */
    public function test_persentase_dihitung_dari_total_yang_dialokasikan(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, [
            'deposito' => 7500000,
            'emas' => 2500000,
            'custom' => [],
        ]);

        $banding = $this->banding($user);

        $this->assertTrue($banding['has_actual']);
        $this->assertSame(10000000.0, $banding['total_actual']);
        $this->assertSame(75.0, $this->baris($banding, 'Deposito')['actual_percentage']);
        $this->assertSame(25.0, $this->baris($banding, 'Emas')['actual_percentage']);
    }

    /**
     * Kunci JSON ("deposito") harus dipetakan ke label yang sama dengan
     * InvestmentAllocationService ("Deposito"). Tanpa itu keduanya terhitung
     * dua instrumen berbeda dan perbandingannya kehilangan arti.
     */
    public function test_nama_instrumen_disamakan_dengan_saran(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, ['obligasi' => 1000000, 'custom' => []]);

        $baris = $this->baris($this->banding($user), 'Obligasi/SBN');

        $this->assertNotNull($baris, 'Kunci "obligasi" tidak dipetakan ke "Obligasi/SBN".');
        $this->assertSame(100.0, $baris['actual_percentage']);
        $this->assertGreaterThan(0, $baris['suggested_percentage']);
    }

    /**
     * Instrumen yang dipegang tetapi TIDAK disarankan — kas, atau instrumen
     * tambahan pengguna — justru yang paling perlu terlihat. Membuangnya
     * karena tak ada di saran menyembunyikan penyimpangan terbesar.
     */
    public function test_instrumen_di_luar_saran_tetap_muncul(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, [
            'tabungan' => 5000000,
            'custom' => [['name' => 'Reksa Dana', 'amount' => 5000000]],
        ]);

        $banding = $this->banding($user);

        $this->assertNotNull($this->baris($banding, 'Tabungan/Kas'));
        $this->assertNotNull($this->baris($banding, 'Reksa Dana'));
        $this->assertSame(0.0, $this->baris($banding, 'Tabungan/Kas')['suggested_percentage']);
    }

    public function test_instrumen_tambahan_bernama_sama_dijumlahkan(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, [
            'custom' => [
                ['name' => 'Reksa Dana', 'amount' => 3000000],
                ['name' => 'Reksa Dana', 'amount' => 2000000],
            ],
        ]);

        $banding = $this->banding($user);

        $this->assertSame(5000000.0, $this->baris($banding, 'Reksa Dana')['actual_amount']);
        $this->assertCount(
            1,
            collect($banding['rows'])->where('instrument', 'Reksa Dana'),
            'Nama yang sama seharusnya dijumlahkan, bukan jadi dua baris.',
        );
    }

    // ── Penyimpangan ────────────────────────────────────────────────────

    /**
     * Selisih dinyatakan dalam POIN PERSEN, bukan persen dari persen: 70%
     * yang menjadi 25% adalah selisih 45 poin, bukan turun 64%.
     *
     * Angka sarannya sendiri TIDAK dipatok di sini. Tabel alokasi di
     * InvestmentAllocationService bersifat ilustratif dan memang akan berubah
     * (PRD D-7); test yang mematoknya akan gagal setiap kali tabel itu
     * ditinjau, padahal aturan yang dijaga tidak berubah sama sekali.
     */
    public function test_selisih_dihitung_dalam_poin_persen(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, ['deposito' => 2500000, 'emas' => 7500000, 'custom' => []]);

        foreach ($this->banding($user)['rows'] as $baris) {
            $this->assertSame(
                round($baris['actual_percentage'] - $baris['suggested_percentage'], 1),
                $baris['delta'],
                "Selisih {$baris['instrument']} bukan pengurangan poin persen.",
            );
        }
    }

    /**
     * Ambang ±10 poin persen (FR-54). Selisih kecil wajar terjadi, dan
     * menandainya hanya melatih pengguna mengabaikan peringatan.
     *
     * Alokasinya disusun MENGIKUTI saran yang berlaku, dibaca lebih dulu dari
     * service — bukan ditebak. Dengan begitu test tetap benar meski tabel
     * alokasinya suatu saat ditinjau ulang.
     */
    public function test_penyimpangan_kecil_tidak_ditandai(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        // Terjemahkan saran menjadi nominal: total 10 juta, persis sesuai porsi.
        $peta = [
            'Saham' => 'saham',
            'Obligasi/SBN' => 'obligasi',
            'Deposito' => 'deposito',
            'Emas' => 'emas',
        ];

        $alokasi = ['custom' => []];
        foreach (app(DashboardSummaryService::class)->forUser($user)['goals'][0]['suggested_allocation'] as $saran) {
            $kunci = $peta[$saran['instrument']] ?? null;
            if ($kunci !== null) {
                $alokasi[$kunci] = $saran['percentage'] / 100 * 10000000;
            }
        }

        $goal->update(['asset_allocation' => $alokasi]);

        foreach ($this->banding($user->fresh())['rows'] as $baris) {
            $this->assertFalse(
                $baris['off_track'],
                "{$baris['instrument']} ditandai melenceng padahal persis mengikuti saran.",
            );
        }
    }

    /**
     * Ambang itu ketat: 10 poin pas TIDAK ditandai, di atasnya baru ditandai.
     * Batas yang bergeser satu poin membuat peringatan muncul atau hilang
     * tanpa ada yang menyadari perubahannya.
     */
    public function test_ambang_penandaan_tepat_di_sepuluh_poin(): void
    {
        $user = User::factory()->create();
        $goal = $this->buatTujuan($user);

        $saran = collect(
            app(DashboardSummaryService::class)->forUser($user)['goals'][0]['suggested_allocation']
        )->firstWhere('instrument', 'Emas');

        // Emas dibuat tepat 10 poin di atas sarannya, sisanya ke deposito.
        $porsiEmas = ($saran['percentage'] + 10) / 100;
        $goal->update(['asset_allocation' => [
            'emas' => $porsiEmas * 10000000,
            'deposito' => (1 - $porsiEmas) * 10000000,
            'custom' => [],
        ]]);

        $emas = $this->baris($this->banding($user->fresh()), 'Emas');

        $this->assertSame(10.0, $emas['delta']);
        $this->assertFalse($emas['off_track'], 'Tepat 10 poin belum melewati ambang.');
    }

    public function test_penyimpangan_besar_ditandai(): void
    {
        $user = User::factory()->create();
        $this->buatTujuan($user, ['emas' => 10000000, 'custom' => []]);

        $emas = $this->baris($this->banding($user), 'Emas');

        $this->assertSame(100.0, $emas['actual_percentage']);
        $this->assertTrue($emas['off_track']);
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_alokasi_tidak_bocor_antar_pengguna(): void
    {
        $this->buatTujuan(User::factory()->create(), ['emas' => 99000000, 'custom' => []]);

        $saya = User::factory()->create();
        $this->buatTujuan($saya, ['deposito' => 1000000, 'custom' => []]);

        $banding = $this->banding($saya);

        $this->assertSame(1000000.0, $banding['total_actual']);
    }
}
