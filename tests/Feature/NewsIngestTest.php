<?php

namespace Tests\Feature;

use App\Jobs\FetchLatestNewsJob;
use App\Models\NewsArticle;
use App\Services\NewsIngestService;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * Pengambilan berita dari NewsData.io ke cache (PRD FR-16, FR-18,
 * FR-28..FR-30, keputusan D-16).
 *
 * Tidak ada satu pun test di sini yang memanggil NewsData.io sungguhan —
 * `preventStrayRequests()` menggagalkan test yang mencobanya. Kuota gratisnya
 * 200 kredit per hari; satu kali menjalankan test suite tidak boleh
 * menghabiskannya.
 */
class NewsIngestTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI = 'kunci-palsu-untuk-test-123';

    protected function setUp(): void
    {
        parent::setUp();

        Http::preventStrayRequests();
        config(['news.api_key' => self::KUNCI]);
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** @param  array<string, mixed>  $ubah */
    private function artikel(array $ubah = []): array
    {
        return array_merge([
            'article_id' => 'abc',
            'link' => 'https://www.kontan.co.id/news/ihsg-diprediksi-sideways',
            'title' => 'IHSG Diprediksi Sideways Cenderung Melemah pada Senin',
            'description' => 'Analis memperkirakan IHSG bergerak terbatas.',
            'content' => 'ONLY AVAILABLE IN PAID PLANS',
            'image_url' => 'https://img.kontan.co.id/gambar.jpg',
            'source_id' => 'kontan_co_id',
            'source_name' => 'Kontan Co Id',
            'pubDate' => '2026-09-27 01:55:30',
            'pubDateTZ' => 'UTC',
            'category' => ['business'],
        ], $ubah);
    }

    private function sukses(array $artikel): array
    {
        return ['status' => 'success', 'totalResults' => count($artikel), 'results' => $artikel];
    }

    /**
     * Semua kueri kategori menjawab sama, kecuali yang diisi `$perKueri`
     * (dicocokkan terhadap parameter `q`).
     *
     * @param  array<string, mixed>  $perKueri  potongan kata kunci => respons
     */
    private function palsukan(array $bawaan = [], array $perKueri = []): void
    {
        Http::fake(function (Request $req) use ($bawaan, $perKueri) {
            foreach ($perKueri as $potongan => $respons) {
                if (str_contains($req['q'] ?? '', $potongan)) {
                    return $respons;
                }
            }

            return Http::response($this->sukses($bawaan));
        });
    }

    private function jalankan(): array
    {
        return app(NewsIngestService::class)->run();
    }

    // ── Permintaan ──────────────────────────────────────────────────────

    /** Satu kueri per kategori — bukan satu kueri gabungan (D-16). */
    public function test_satu_permintaan_per_kategori_dengan_filter_indonesia(): void
    {
        $this->palsukan();

        $this->jalankan();

        Http::assertSentCount(count(config('news.categories')));
        Http::assertSent(fn (Request $req) => $req['country'] === 'id'
            && $req['language'] === 'id'
            && $req['domain'] === implode(',', config('news.domains'))
            && str_contains($req['q'], 'IHSG'));
    }

    /**
     * Kunci dikirim lewat header, TIDAK lewat URL. Pesan galat HTTP dan log
     * memuat URL lengkap; kunci di dalam URL akan ikut tercatat di sana.
     */
    public function test_kunci_dikirim_lewat_header_bukan_url(): void
    {
        $this->palsukan();

        $this->jalankan();

        Http::assertSent(fn (Request $req) => $req->hasHeader('X-ACCESS-KEY', self::KUNCI)
            && ! str_contains($req->url(), self::KUNCI));
    }

    public function test_tanpa_kunci_tidak_ada_permintaan_sama_sekali(): void
    {
        config(['news.api_key' => null]);
        $this->palsukan();

        $hasil = $this->jalankan();

        Http::assertNothingSent();
        $this->assertArrayHasKey('*', $hasil['failed']);
    }

    // ── Penyimpanan ─────────────────────────────────────────────────────

    public function test_artikel_tersimpan_hanya_metadatanya(): void
    {
        $this->palsukan([$this->artikel()]);

        $this->jalankan();

        $a = NewsArticle::sole();
        $this->assertSame('https://www.kontan.co.id/news/ihsg-diprediksi-sideways', $a->url);
        $this->assertSame('IHSG Diprediksi Sideways Cenderung Melemah pada Senin', $a->title);
        $this->assertSame('Analis memperkirakan IHSG bergerak terbatas.', $a->summary);
        $this->assertSame('kontan_co_id', $a->source);
        $this->assertSame('Kontan Co Id', $a->source_name);

        // Gambar dan isi penuh tidak disimpan — paket gratis tidak mengizinkan.
        $this->assertArrayNotHasKey('image_url', $a->getAttributes());
        $this->assertArrayNotHasKey('content', $a->getAttributes());
    }

    /** `pubDate` NewsData.io dalam UTC; disimpan menurut jam aplikasi (WIB). */
    public function test_waktu_terbit_dikonversi_dari_utc(): void
    {
        $this->palsukan([$this->artikel(['pubDate' => '2026-09-27 01:55:30', 'pubDateTZ' => 'UTC'])]);

        $this->jalankan();

        $this->assertSame('2026-09-27 08:55:30', NewsArticle::sole()->published_at->format('Y-m-d H:i:s'));
    }

    /**
     * FR-29: job berjalan tiap jam dan menerima artikel yang sama berulang
     * kali — termasuk artikel yang sama dari dua kueri kategori sekaligus.
     */
    public function test_artikel_yang_sama_tidak_pernah_tergandakan(): void
    {
        $this->palsukan([$this->artikel()]);

        $this->jalankan();
        $this->jalankan();

        // Lima kueri kategori, dua kali jalan, satu artikel yang sama.
        $this->assertSame(1, NewsArticle::count());
    }

    /** Artikel tidak berpindah-pindah tab setiap jam. */
    public function test_kategori_artikel_tidak_berubah_saat_diambil_ulang(): void
    {
        // Satu tiruan yang jawabannya bisa diganti — Http::fake() kedua tidak
        // menimpa yang pertama, callback-nya ditumpuk dan yang pertama menang.
        $jawaban = [$this->artikel()];
        Http::fake(function () use (&$jawaban) {
            return Http::response($this->sukses($jawaban));
        });

        $this->jalankan();
        $this->assertSame('pasar-saham', NewsArticle::sole()->category);

        // Kali kedua judulnya berubah dan kini cocok pola Kebijakan Moneter.
        $jawaban = [$this->artikel(['title' => 'BI Rate Ditahan, IHSG Bergerak Terbatas'])];
        $this->jalankan();

        $a = NewsArticle::sole();
        $this->assertSame('BI Rate Ditahan, IHSG Bergerak Terbatas', $a->title);
        $this->assertSame('pasar-saham', $a->category);
    }

    // ── Klasifikasi & penyaringan ───────────────────────────────────────

    /** FR-28: kategori pertama yang polanya muncul di judul atau ringkasan. */
    public function test_kategori_ditentukan_dari_pola_bukan_dari_kueri(): void
    {
        // Diambil lewat kueri Properti, tetapi judulnya tentang IHSG.
        $this->palsukan([], ['properti' => Http::response($this->sukses([$this->artikel()]))]);

        $this->jalankan();

        $this->assertSame('pasar-saham', NewsArticle::sole()->category);
    }

    /**
     * Kategori kueri diutamakan bila polanya ikut cocok. Tanpa ini, artikel
     * tips yang menyebut "inflasi" direbut Kebijakan Moneter — kategori itu
     * luas dan berada di urutan pertama.
     */
    public function test_kategori_kueri_diutamakan_bila_polanya_cocok(): void
    {
        $tips = $this->artikel([
            'link' => 'https://www.liputan6.com/gajian',
            'title' => 'Tips Agar Gen-Z Tak Bokek Sebelum Gajian',
            'description' => 'Di tengah inflasi, atur pengeluaran sejak awal bulan.',
        ]);
        $this->palsukan([], ['menabung' => Http::response($this->sukses([$tips]))]);

        $this->jalankan();

        $this->assertSame('tips-keuangan', NewsArticle::sole()->category);
    }

    /**
     * Pengambilan sungguhan pertama (27 Sep 2026): artikel yang kata kuncinya
     * hanya ada di ISI berita — tidak di judul maupun ringkasan — hampir
     * selalu tidak relevan. Dulu ia masuk kategori kuerinya; kini dibuang.
     *
     * @return array<string, array{0: string, 1: string, 2: string}>
     */
    public static function beritaTakRelevanDariDataSungguhan(): array
    {
        return [
            'medali Asian Games lewat "emas"' => [
                'Antam',
                'Update Perolehan Medali Asian Games 2026 Usai Indonesia Tambah Emas',
                'Indonesia menambah satu medali dari cabang angkat besi.',
            ],
            'Posyandu lewat "perumahan"' => [
                'properti',
                '225 Ribu Posyandu Belum Terintegrasi, Kemendagri Kebut Pendataan',
                'Pendataan ditargetkan rampung akhir tahun.',
            ],
            'koin Romawi lewat "menabung"' => [
                'menabung',
                'Arkeolog Temukan 934 Koin Perak Romawi di Jerman',
                'Koin itu diperkirakan berusia 1.800 tahun.',
            ],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('beritaTakRelevanDariDataSungguhan')]
    public function test_artikel_tanpa_pola_yang_cocok_dibuang(string $kueri, string $judul, string $ringkasan): void
    {
        $this->palsukan([], [$kueri => Http::response($this->sukses([
            $this->artikel(['link' => 'https://detik.com/'.md5($judul), 'title' => $judul, 'description' => $ringkasan]),
        ]))]);

        $hasil = $this->jalankan();

        $this->assertSame(0, NewsArticle::count());
        $this->assertSame(1, $hasil['excluded']);
    }

    /**
     * Ramalan zodiak "karier" diberi kategori `business` oleh NewsData.io —
     * ditemukan pada uji gerbang, dan dibuang di sini.
     */
    public function test_artikel_di_daftar_pengecualian_dibuang(): void
    {
        $this->palsukan([
            $this->artikel(['link' => 'https://jawapos.com/zodiak', 'title' => 'Ramalan Karier Zodiak Capricorn Minggu Ini']),
            $this->artikel(['link' => 'https://liputan6.com/loker', 'title' => 'Lowongan Kerja di Paramount Land']),
            $this->artikel(['link' => 'https://kompas.com/harta', 'title' => 'Intip Deretan Properti Wisnu Hani, Eks Kepala Lapas']),
            $this->artikel(),
        ]);

        $hasil = $this->jalankan();

        $this->assertSame(1, NewsArticle::count());
        $this->assertGreaterThan(0, $hasil['excluded']);
    }

    public function test_artikel_tanpa_judul_atau_tautan_dibuang(): void
    {
        $this->palsukan([
            $this->artikel(['title' => '   ']),
            $this->artikel(['link' => 'javascript:alert(1)', 'title' => 'IHSG naik']),
        ]);

        $this->jalankan();

        $this->assertSame(0, NewsArticle::count());
    }

    /**
     * Ditemukan pada uji gerbang: `&quot;` tiba sebagai `danquot;`, karena
     * sumbernya mengganti setiap `&` dengan kata "dan".
     */
    public function test_entitas_html_yang_rusak_dipulihkan(): void
    {
        $this->palsukan([$this->artikel([
            'title' => 'Buaya danquot;Cokidanquot; Dipindahkan, IHSG &amp; Rupiah <b>Stabil</b>',
        ])]);

        $this->jalankan();

        $this->assertSame('Buaya "Coki" Dipindahkan, IHSG & Rupiah Stabil', NewsArticle::sole()->title);
    }

    // ── Kegagalan ───────────────────────────────────────────────────────

    /** Satu kategori gagal tidak menggagalkan yang lain. */
    public function test_satu_kategori_gagal_yang_lain_tetap_tersimpan(): void
    {
        $this->palsukan([$this->artikel()], [
            'properti' => Http::response(['status' => 'error', 'results' => ['message' => 'Rate limit exceeded']], 429),
        ]);

        $hasil = $this->jalankan();

        $this->assertSame(1, NewsArticle::count());
        $this->assertSame(['properti'], array_keys($hasil['failed']));
        $this->assertStringContainsString('Rate limit exceeded', $hasil['failed']['properti']);
        $this->assertNotNull(Cache::get(NewsIngestService::LAST_SUCCESS_KEY));
    }

    /**
     * Kuota habis tidak pernah mengosongkan cache, dan tidak menandai
     * pengambilan sebagai berhasil — halaman harus tahu ia sedang basi.
     */
    public function test_semua_gagal_cache_lama_tetap_utuh(): void
    {
        NewsArticle::create([
            'url' => 'https://kontan.co.id/lama', 'title' => 'Berita lama', 'source' => 'kontan_co_id',
            'category' => 'pasar-saham', 'published_at' => now()->subDay(), 'fetched_at' => now()->subDay(),
        ]);
        Http::fake(['*' => Http::response(['status' => 'error', 'results' => ['message' => 'quota']], 429)]);

        $hasil = $this->jalankan();

        $this->assertSame(1, NewsArticle::count());
        $this->assertCount(count(config('news.categories')), $hasil['failed']);
        $this->assertNull(Cache::get(NewsIngestService::LAST_SUCCESS_KEY));
    }

    public function test_sumber_tidak_bisa_dihubungi_tidak_melempar_galat(): void
    {
        Http::fake(fn () => throw new \Illuminate\Http\Client\ConnectionException('timeout'));

        $hasil = $this->jalankan();

        $this->assertCount(count(config('news.categories')), $hasil['failed']);
        $this->assertStringNotContainsString(self::KUNCI, json_encode($hasil['failed']));
    }

    // ── Pemangkasan ─────────────────────────────────────────────────────

    /** FR-30: artikel lebih tua dari 30 hari dipangkas. */
    public function test_artikel_lama_dipangkas(): void
    {
        foreach ([31 => 'lama', 29 => 'baru'] as $hari => $nama) {
            NewsArticle::create([
                'url' => "https://kontan.co.id/{$nama}", 'title' => $nama, 'source' => 'kontan_co_id',
                'category' => 'pasar-saham', 'published_at' => now()->subDays($hari), 'fetched_at' => now(),
            ]);
        }

        $dipangkas = app(NewsIngestService::class)->prune();

        $this->assertSame(1, $dipangkas);
        $this->assertSame(['baru'], NewsArticle::pluck('title')->all());
    }

    // ── Penjadwalan ─────────────────────────────────────────────────────

    public function test_job_terjadwal_tiap_jam(): void
    {
        $jadwal = collect(app(Schedule::class)->events())
            ->first(fn ($e) => str_contains((string) $e->description, FetchLatestNewsJob::class));

        $this->assertNotNull($jadwal, 'FetchLatestNewsJob tidak terjadwal.');
        $this->assertSame('0 * * * *', $jadwal->expression);
    }

    public function test_job_menjalankan_pengambilan(): void
    {
        $this->palsukan([$this->artikel()]);

        FetchLatestNewsJob::dispatchSync();

        $this->assertSame(1, NewsArticle::count());
    }

    public function test_perintah_manual_melaporkan_hasilnya(): void
    {
        $this->palsukan([$this->artikel()]);

        $this->artisan('news:fetch')
            ->expectsOutputToContain('Tersimpan: 1')
            ->assertSuccessful();
    }

    public function test_perintah_manual_gagal_bila_ada_kategori_gagal(): void
    {
        Http::fake(['*' => Http::response(['status' => 'error', 'results' => ['message' => 'quota']], 429)]);

        $this->artisan('news:fetch')->assertFailed();
    }
}
