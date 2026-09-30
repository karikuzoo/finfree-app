<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Kalkulator Pinjaman/KPR (FR-41) dan Investasi (FR-42). Yang diuji lapisan
 * HTTP-nya — akses tanpa login, validasi, bentuk props. Rumusnya dijaga
 * GoalCalculatorServiceTest; satu kasus dipakai ulang sebagai pemeriksaan
 * silang bahwa controller benar-benar memanggil service.
 */
class UtilityCalculatorPageTest extends TestCase
{
    use RefreshDatabase;

    public function test_kalkulator_pinjaman_bisa_dibuka_tanpa_login(): void
    {
        $this->get(route('calculator.loan'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculator/Loan')
                ->where('input', null)
                ->where('result', null));
    }

    public function test_kalkulator_investasi_bisa_dibuka_tanpa_login(): void
    {
        $this->get(route('calculator.investment'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('Calculator/Investment')
                ->where('input', null)
                ->where('result', null));
    }

    public function test_pinjaman_dihitung_seperti_bank(): void
    {
        $this->get(route('calculator.loan', [
            'principal' => 500000000,
            'annual_interest_rate' => 10,
            'months' => 240,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.monthly_installment', 4825109)
                ->where('result.months', 240)
                ->has('result.yearly', 20)
                ->where('result.yearly.19.balance', 0));
    }

    public function test_pinjaman_menolak_tenor_di_atas_tiga_puluh_tahun(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', ['principal' => 1000000, 'annual_interest_rate' => 10, 'months' => 361]))
            ->assertRedirect(route('calculator.loan'))
            ->assertSessionHasErrors(['months' => 'Tenor maksimal 360 bulan (30 tahun).']);
    }

    public function test_pinjaman_tanpa_bunga_diterima(): void
    {
        $this->get(route('calculator.loan', ['principal' => 12000000, 'annual_interest_rate' => 0, 'months' => 12]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.monthly_installment', 1000000)
                ->where('result.total_interest', 0));
    }

    // ── Jenis bunga & cek kesehatan cicilan ─────────────────────────────

    private function kpr(array $ubah = []): array
    {
        return array_merge([
            'principal' => 500000000,
            'annual_interest_rate' => 7,
            'months' => 240,
        ], $ubah);
    }

    public function test_tanpa_pendapatan_tidak_ada_cek_kesehatan(): void
    {
        $this->get(route('calculator.loan', $this->kpr()))
            ->assertInertia(fn (Assert $page) => $page
                ->where('health', null)
                ->where('stress', null)
                ->has('result.tiers', 1));
    }

    /** Contoh pengguna (30 Sep 2026): empat jenjang, 20 tahun. */
    private function berjenjang(array $ubah = []): array
    {
        return $this->kpr(array_merge([
            'rate_type' => 'tiered',
            'tiers' => [
                ['until_year' => 1, 'rate' => 3.75],
                ['until_year' => 4, 'rate' => 6.75],
                ['until_year' => 10, 'rate' => 9.75],
                ['rate' => 10.75, 'floating' => 1],
            ],
        ], $ubah));
    }

    public function test_bunga_berjenjang_dihitung_per_jenjang(): void
    {
        $this->get(route('calculator.loan', $this->berjenjang()))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('result.tiers', 4)
                ->where('result.tiers.0.installment', 2964442)
                ->where('result.tiers.3.installment', 4739763)
                ->where('result.tiers.3.from_month', 121)
                ->where('result.total_interest', 567168740)
                // Bunga baris pertama menjadi bunga awal; annual_interest_rate tidak dipakai.
                ->missing('input.annual_interest_rate'));
    }

    public function test_bunga_berjenjang_dinilai_dari_jenjang_terberat(): void
    {
        $halaman = $this->get(route('calculator.loan', $this->berjenjang([
            'monthly_income' => 15000000,
            'monthly_expenses' => 6000000,
        ])))->assertOk();

        $health = $halaman->viewData('page')['props']['health'];
        $this->assertSame('mulai tahun ke-11', $health['worst']['label']);
        $this->assertSame(4739763, $health['worst']['installments']);
        // 3,76 jt / 15 jt = 25,1% masih sehat; 4,55 jt = 30,3% mulai melewati.
        $this->assertSame('Mulai tahun ke-5, rasionya sudah 30,3% — melewati batas sehat 30%.', $health['reasons'][1]);
    }

    /**
     * "Tetap lalu mengambang" kini dua jenjang — tanpa pilihan tersendiri.
     */
    public function test_tetap_lalu_mengambang_sebagai_dua_jenjang(): void
    {
        $this->get(route('calculator.loan', $this->kpr([
            'rate_type' => 'tiered',
            'tiers' => [['until_year' => 3, 'rate' => 7], ['rate' => 11, 'floating' => 1]],
        ])))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->has('result.tiers', 2)
                ->where('result.tiers.1.from_month', 37)
                ->where('result.tiers.1.rate', 11));
    }

    public function test_bunga_berjenjang_satu_baris_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'tiered', 'tiers' => [['rate' => 7]]])))
            ->assertSessionHasErrors(['tiers' => 'Bunga berjenjang butuh minimal dua jenjang. Bila bunganya sama sepanjang tenor, pilih "Tetap".']);
    }

    public function test_jenjang_tanpa_batas_tahun_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'tiered', 'tiers' => [['rate' => 5], ['rate' => 9]]])))
            ->assertSessionHasErrors(['tiers.0.until_year' => 'Isi sampai tahun ke berapa bunga ini berlaku.']);
    }

    public function test_jenjang_yang_mundur_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'tiered', 'tiers' => [
                ['until_year' => 5, 'rate' => 5],
                ['until_year' => 3, 'rate' => 7],
                ['rate' => 9],
            ]])))
            ->assertSessionHasErrors(['tiers.1.until_year' => 'Jenjang ini mulai tahun ke-6, jadi batasnya paling cepat tahun ke-6.']);
    }

    public function test_jenjang_melewati_tenor_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'tiered', 'tiers' => [['until_year' => 20, 'rate' => 5], ['rate' => 9]]])))
            ->assertSessionHasErrors(['tiers.0.until_year' => 'Jenjang ini sudah mencapai akhir tenor — jadikan jenjang terakhir, atau perpanjang tenornya.']);
    }

    public function test_bunga_mengambang_diuji_dengan_bunga_naik(): void
    {
        $halaman = $this->get(route('calculator.loan', $this->kpr([
            'rate_type' => 'floating',
            'floating_rate' => 10,
            'monthly_income' => 20000000,
        ])))->assertOk();

        $props = $halaman->viewData('page')['props'];
        // Tabel utama tetap bunga sekarang; skenario naik terpisah.
        $this->assertSame(0.07 / 12, $props['result']['monthly_rate']);
        $this->assertSame(4825109, $props['stress']['monthly_installment']);
        $this->assertSame('bila bunga naik ke 10%', $props['health']['worst']['label']);
    }

    public function test_bunga_tetap_mengabaikan_isian_jenjang_dan_mengambang(): void
    {
        $this->get(route('calculator.loan', $this->kpr(['rate_type' => 'fixed', 'floating_rate' => 99, 'tiers' => [['rate' => 99]]])))
            ->assertOk()
            ->assertSessionHasNoErrors()
            ->assertInertia(fn (Assert $page) => $page->has('result.tiers', 1)->where('stress', null));
    }

    public function test_bunga_mengambang_tanpa_bunga_naik_ditolak(): void
    {
        $this->from(route('calculator.loan'))
            ->get(route('calculator.loan', $this->kpr(['rate_type' => 'floating'])))
            ->assertSessionHasErrors('floating_rate');
    }

    public function test_cek_kesehatan_memakai_pajak_tahunan_dan_cicilan_lain(): void
    {
        $this->get(route('calculator.loan', $this->kpr([
            'annual_interest_rate' => 0,
            'months' => 100,
            'monthly_income' => 20000000,
            'other_installments' => 1000000,
            'monthly_expenses' => 10000000,
            'annual_taxes' => 1200000,
        ])))
            ->assertInertia(fn (Assert $page) => $page
                // Angsuran 5 jt + cicilan lain 1 jt = 30% → sehat; sisa 3,9 jt.
                ->where('health.now.dsr', 30)
                ->where('health.monthly_taxes', 100000)
                ->where('health.now.residual', 3900000)
                ->where('health.status', 'healthy'));
    }

    public function test_investasi_menghitung_nilai_akhir(): void
    {
        $this->get(route('calculator.investment', [
            'initial_amount' => 10000000,
            'monthly_contribution' => 1000000,
            'months' => 24,
            'annual_return_rate' => 0,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('result.final_value', 34000000)
                ->where('result.total_contribution', 24000000)
                ->where('result.investment_growth', 0));
    }

    public function test_investasi_dana_awal_saja_diterima(): void
    {
        $this->get(route('calculator.investment', [
            'initial_amount' => 10000000,
            'monthly_contribution' => 0,
            'months' => 12,
            'annual_return_rate' => 10,
        ]))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('result.final_value', 11000000));
    }

    /** Dana awal dan setoran sama-sama nol: tidak ada yang bisa ditampilkan. */
    public function test_investasi_tanpa_dana_sama_sekali_ditolak(): void
    {
        $this->from(route('calculator.investment'))
            ->get(route('calculator.investment', ['monthly_contribution' => 0, 'months' => 12, 'annual_return_rate' => 8]))
            ->assertRedirect(route('calculator.investment'))
            ->assertSessionHasErrors(['monthly_contribution' => 'Isi dana awal, setoran bulanan, atau keduanya.']);
    }

    /**
     * Bug 30 Sep 2026: memuat ulang tautan yang isiannya tidak sah membuat
     * Laravel menyimpannya sebagai "halaman sebelumnya", lalu pemuatan
     * berikutnya diarahkan ke alamat itu lagi — berputar sampai batas 60
     * permintaan/menit habis dan pengguna melihat 429. Galat validasi di
     * kalkulator GET harus selalu mendarat di alamat bersih.
     */
    public function test_isian_tidak_sah_tidak_membuat_redirect_berputar(): void
    {
        $kasus = [
            'calculator.loan' => ['principal' => 500000000, 'annual_interest_rate' => 5, 'months' => 240, 'rate_type' => 'fix_float'],
            'calculator.investment' => ['monthly_contribution' => 1000000, 'months' => 0, 'annual_return_rate' => 8],
            'calculator.goal' => ['target_amount' => 1000000, 'months' => 999, 'annual_return_rate' => 8],
        ];

        foreach ($kasus as $nama => $query) {
            $buruk = route($nama, $query);

            // Tiga kali berturut-turut dalam sesi yang sama — pada kali kedua
            // perilaku lama sudah mengarah ke dirinya sendiri.
            foreach ([1, 2, 3] as $kali) {
                $this->get($buruk)
                    ->assertRedirect(route($nama))
                    ->assertSessionHasErrors();
            }

            // Alamat bersihnya sendiri selalu bisa dibuka.
            $this->get(route($nama))->assertOk();
        }

        // Tautan lama `fix_float` diberi tahu dengan bahasa manusia.
        $this->get(route('calculator.loan', $kasus['calculator.loan']))
            ->assertSessionHasErrors(['rate_type' => 'Jenis bunga di tautan ini tidak dikenal — mungkin tautan dari versi lama. Pilih jenis bunganya lagi.']);
    }

    public function test_daftar_kalkulator_menautkan_ketiganya(): void
    {
        $this->get(route('calculator.index'))->assertOk();
        // Isi kartunya diuji di sisi React; di sini cukup memastikan ketiga
        // route-nya terdaftar dan bisa dibuka tamu.
        foreach (['calculator.goal', 'calculator.loan', 'calculator.investment'] as $nama) {
            $this->get(route($nama))->assertOk();
        }
    }
}
