<?php

namespace App\Http\Controllers;

use App\Models\NewsArticle;
use App\Services\NewsIngestService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Halaman Berita (PRD FR-16, FR-17). Publik — tamu juga bisa membukanya
 * (FR-44).
 *
 * Hanya MEMBACA dari `news_article_cache`. Tidak pernah memanggil NewsData.io:
 * satu kunjungan tidak boleh memakan kuota, dan halamannya harus tetap berisi
 * saat sumbernya tidak bisa dihubungi. Pengisiannya urusan FetchLatestNewsJob.
 */
class NewsController extends Controller
{
    public function index(Request $request): Response
    {
        $kategori = config('news.categories');

        // Kategori yang tidak dikenal diperlakukan sebagai "Semua", bukan
        // galat — tautan lama ke kategori yang sudah dihapus dari config tetap
        // membuka halaman yang berisi.
        $aktif = array_key_exists($request->query('kategori'), $kategori)
            ? $request->query('kategori')
            : null;

        $artikel = NewsArticle::query()
            ->when($aktif, fn ($q) => $q->where('category', $aktif))
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            // 12, bukan 20: dengan 20 kartu halamannya memanjang empat layar,
            // dan berita di bawah jarang terbaca. 12 genap untuk dua kolom.
            ->paginate(12)
            ->withQueryString()
            ->through(fn (NewsArticle $a) => [
                'id' => $a->id,
                'title' => $a->title,
                'summary' => $a->summary,
                'url' => $a->url,
                'source' => $a->source_name ?: $a->source,
                'category' => $a->category,
                'category_label' => $kategori[$a->category]['label'] ?? null,
                'published_at' => $a->published_at->toIso8601String(),
            ]);

        $jumlah = NewsArticle::query()
            ->selectRaw('category, COUNT(*) AS n')
            ->groupBy('category')
            ->pluck('n', 'category');

        $terakhir = Cache::get(NewsIngestService::LAST_SUCCESS_KEY);
        $terakhir = $terakhir ? Carbon::parse($terakhir) : null;

        return Inertia::render('News/Index', [
            'articles' => $artikel,
            'categories' => collect($kategori)->map(fn ($k, $slug) => [
                'slug' => $slug,
                'label' => $k['label'],
                'count' => (int) ($jumlah[$slug] ?? 0),
            ])->values(),
            'activeCategory' => $aktif,
            'lastUpdated' => $terakhir?->toIso8601String(),
            // Basi = belum pernah berhasil, atau berhasil terakhir terlalu
            // lama. Halaman tetap menampilkan cache-nya, dengan banner.
            'stale' => $terakhir === null
                || $terakhir->lt(now()->subMinutes((int) config('news.stale_after_minutes'))),
        ]);
    }
}
