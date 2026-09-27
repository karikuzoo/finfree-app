<?php

namespace Tests\Feature;

use App\Models\NewsArticle;
use App\Models\User;
use App\Services\NewsIngestService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Halaman Berita (PRD FR-16, FR-17, FR-44). Hanya MEMBACA cache.
 */
class NewsPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Halaman ini tidak boleh memanggil NewsData.io, sekali pun.
        Http::preventStrayRequests();
    }

    private function berita(string $judul, string $kategori, int $jamLalu = 1): NewsArticle
    {
        return NewsArticle::create([
            'url' => 'https://www.kontan.co.id/'.md5($judul),
            'title' => $judul,
            'summary' => "Ringkasan {$judul}",
            'source' => 'kontan_co_id',
            'source_name' => 'Kontan Co Id',
            'category' => $kategori,
            'published_at' => now()->subHours($jamLalu),
            'fetched_at' => now(),
        ]);
    }

    public function test_tamu_bisa_membuka_berita(): void
    {
        $this->berita('IHSG naik', 'pasar-saham');

        $this->get(route('news.index'))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->component('News/Index')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'IHSG naik')
                ->where('articles.data.0.source', 'Kontan Co Id')
                ->where('articles.data.0.category_label', 'Pasar Saham'));
    }

    public function test_pengguna_login_juga_bisa_membuka_berita(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('news.index'))
            ->assertOk();
    }

    public function test_berita_terbaru_di_atas(): void
    {
        $this->berita('Lama', 'pasar-saham', 5);
        $this->berita('Terbaru', 'pasar-saham', 1);

        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('articles.data.0.title', 'Terbaru')
                ->where('articles.data.1.title', 'Lama'));
    }

    /** FR-17: filter per kategori. */
    public function test_filter_kategori(): void
    {
        $this->berita('IHSG naik', 'pasar-saham');
        $this->berita('BI Rate ditahan', 'kebijakan-moneter');

        $this->get(route('news.index', ['kategori' => 'kebijakan-moneter']))
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeCategory', 'kebijakan-moneter')
                ->has('articles.data', 1)
                ->where('articles.data.0.title', 'BI Rate ditahan'));
    }

    /** Tautan lama ke kategori yang sudah tidak ada membuka "Semua", bukan galat. */
    public function test_kategori_tak_dikenal_menampilkan_semua(): void
    {
        $this->berita('IHSG naik', 'pasar-saham');

        $this->get(route('news.index', ['kategori' => 'kripto']))
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('activeCategory', null)
                ->has('articles.data', 1));
    }

    public function test_jumlah_per_kategori_dan_urutan_kategori_mengikuti_config(): void
    {
        $this->berita('IHSG naik', 'pasar-saham');
        $this->berita('IHSG turun', 'pasar-saham');
        $this->berita('BI Rate ditahan', 'kebijakan-moneter');

        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('categories.0.slug', 'kebijakan-moneter')
                ->where('categories.0.count', 1)
                ->where('categories.1.slug', 'pasar-saham')
                ->where('categories.1.count', 2)
                ->where('categories.2.count', 0));
    }

    public function test_berita_dipaginasi_dua_belas(): void
    {
        foreach (range(1, 25) as $i) {
            $this->berita("Berita {$i}", 'pasar-saham', $i);
        }

        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->has('articles.data', 12)
                ->where('articles.last_page', 3));
    }

    // ── Basi ────────────────────────────────────────────────────────────

    public function test_belum_pernah_berhasil_diambil_dianggap_basi(): void
    {
        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stale', true)
                ->where('lastUpdated', null));
    }

    public function test_baru_diambil_tidak_basi(): void
    {
        Cache::forever(NewsIngestService::LAST_SUCCESS_KEY, now()->subMinutes(30)->toIso8601String());

        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page->where('stale', false));
    }

    /** Job tiap jam; dua kali gagal berturut-turut sudah layak diberi tahu. */
    public function test_lama_tidak_diperbarui_dianggap_basi(): void
    {
        Cache::forever(NewsIngestService::LAST_SUCCESS_KEY, now()->subHours(3)->toIso8601String());
        $this->berita('IHSG naik', 'pasar-saham', 4);

        $this->get(route('news.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('stale', true)
                ->has('articles.data', 1));
    }

    /** Kunci API tidak pernah sampai ke browser, dalam bentuk apa pun. */
    public function test_kunci_api_tidak_pernah_terkirim_ke_halaman(): void
    {
        config(['news.api_key' => 'kunci-rahasia-jangan-bocor']);
        $this->berita('IHSG naik', 'pasar-saham');

        $this->get(route('news.index'))
            ->assertDontSee('kunci-rahasia-jangan-bocor', escape: false);
    }
}
