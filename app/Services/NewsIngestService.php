<?php

namespace App\Services;

use App\Models\NewsArticle;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

/**
 * Mengambil berita dari NewsData.io ke cache `news_article_cache`
 * (PRD FR-16, FR-18, FR-28..FR-30, keputusan D-16).
 *
 * Satu-satunya tempat kunci API disentuh. Kuncinya dikirim lewat header
 * `X-ACCESS-KEY`, bukan parameter URL: pesan galat HTTP dan log memuat URL
 * lengkap, dan kunci di dalam URL akan ikut tercatat di sana.
 *
 * Kegagalan satu kategori TIDAK menggagalkan yang lain, dan tidak pernah
 * mengosongkan cache: halaman Berita tetap menampilkan hasil terakhir, dengan
 * banner "basi" bila terlalu lama (lihat NewsController).
 */
class NewsIngestService
{
    /** Kunci cache: waktu pengambilan terakhir yang berhasil. */
    public const LAST_SUCCESS_KEY = 'news.last_success_at';

    /**
     * @return array{stored: int, excluded: int, failed: array<string, string>, pruned: int}
     */
    public function run(): array
    {
        $hasil = ['stored' => 0, 'excluded' => 0, 'failed' => [], 'pruned' => 0];

        if (blank(config('news.api_key'))) {
            $hasil['failed']['*'] = 'NEWSDATA_IO_API_KEY belum diisi di .env.';

            return $hasil;
        }

        $baris = [];
        $adaYangBerhasil = false;

        foreach (config('news.categories') as $slug => $kategori) {
            try {
                $artikel = $this->fetch($kategori['query']);
                $adaYangBerhasil = true;
            } catch (\RuntimeException $e) {
                $hasil['failed'][$slug] = $e->getMessage();
                Log::warning('Pengambilan berita gagal', ['kategori' => $slug, 'alasan' => $e->getMessage()]);

                continue;
            }

            foreach ($artikel as $mentah) {
                $bersih = $this->normalize($mentah, $slug);

                if ($bersih === null) {
                    $hasil['excluded']++;

                    continue;
                }

                // Artikel yang sama bisa muncul di dua kueri kategori. Yang
                // pertama menang — urutan kategori di config adalah urutan
                // prioritas, sama seperti urutan pencocokan pola.
                $baris[$bersih['url']] ??= $bersih;
            }
        }

        if ($baris !== []) {
            // `category` sengaja tidak ikut diperbarui: artikel yang sudah
            // tersimpan mempertahankan kategori pertamanya, supaya ia tidak
            // berpindah-pindah tab setiap jam.
            NewsArticle::upsert(
                array_values($baris),
                ['url'],
                // `image_url` ikut diperbarui: artikel yang tersimpan sebelum
                // kolomnya ada mendapat fotonya pada pengambilan berikutnya.
                ['title', 'summary', 'image_url', 'source', 'source_name', 'fetched_at'],
            );
            $hasil['stored'] = count($baris);
        }

        if ($adaYangBerhasil) {
            Cache::forever(self::LAST_SUCCESS_KEY, now()->toIso8601String());
        }

        $hasil['pruned'] = $this->prune();

        return $hasil;
    }

    /**
     * FR-30: pangkas artikel yang lebih tua dari `retention_days`.
     */
    public function prune(): int
    {
        return NewsArticle::query()
            ->where('published_at', '<', now()->subDays((int) config('news.retention_days')))
            ->delete();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function fetch(string $kueri): array
    {
        try {
            $respons = Http::timeout(20)
                ->withHeaders(['X-ACCESS-KEY' => config('news.api_key')])
                ->get(config('news.endpoint'), [
                    'country' => config('news.country'),
                    'language' => config('news.language'),
                    'domain' => implode(',', config('news.domains')),
                    'q' => $kueri,
                ]);
        } catch (ConnectionException) {
            throw new \RuntimeException('Tidak bisa terhubung ke NewsData.io.');
        }

        $data = $respons->json();

        if (! $respons->successful() || ($data['status'] ?? null) !== 'success') {
            // `results.message` adalah pesan galat dari NewsData.io (mis. kuota
            // habis). Aman dicatat: isinya tidak memuat kunci.
            $pesan = is_array($data['results'] ?? null) ? ($data['results']['message'] ?? null) : null;

            throw new \RuntimeException('HTTP '.$respons->status().($pesan ? ": {$pesan}" : ''));
        }

        return is_array($data['results'] ?? null) ? $data['results'] : [];
    }

    /**
     * Satu artikel mentah → satu baris tabel, atau null bila harus dibuang.
     *
     * @param  array<string, mixed>  $a
     * @return array<string, mixed>|null
     */
    private function normalize(array $a, string $kategoriKueri): ?array
    {
        $url = trim((string) ($a['link'] ?? ''));
        $judul = $this->cleanText($a['title'] ?? '');

        if ($url === '' || $judul === '' || ! str_starts_with($url, 'http')) {
            return null;
        }

        if (preg_match('/'.config('news.exclude').'/iu', $judul)) {
            return null;
        }

        $ringkasan = $this->cleanText($a['description'] ?? '');
        $kategori = $this->classify($judul.' '.$ringkasan, $kategoriKueri);

        if ($kategori === null) {
            return null;
        }

        return [
            'url' => mb_substr($url, 0, 2048),
            'title' => mb_substr($judul, 0, 500),
            'summary' => $ringkasan === '' ? null : mb_strimwidth($ringkasan, 0, 600, '…'),
            'image_url' => $this->imageUrl($a['image_url'] ?? null),
            'source' => mb_substr((string) ($a['source_id'] ?? 'tidak-diketahui'), 0, 100),
            'source_name' => isset($a['source_name']) ? mb_substr((string) $a['source_name'], 0, 150) : null,
            'category' => $kategori,
            'published_at' => $this->publishedAt($a),
            'fetched_at' => now(),
        ];
    }

    /**
     * Tautan foto artikel — hanya HTTPS. Halaman Arus sendiri dilayani lewat
     * HTTPS di produksi, dan browser menolak memuat gambar HTTP di halaman
     * HTTPS (mixed content); tautan seperti itu hanya akan jadi kotak kosong.
     */
    private function imageUrl(mixed $url): ?string
    {
        $url = trim((string) $url);

        if ($url === '' || ! str_starts_with($url, 'https://') || strlen($url) > 2048) {
            return null;
        }

        return filter_var($url, FILTER_VALIDATE_URL) ? $url : null;
    }

    /**
     * FR-28, dengan perubahan D-16.
     *
     * 1. Kategori kueri yang mengambilnya, bila polanya muncul di judul atau
     *    ringkasan. Diutamakan karena urutan config adalah urutan TAMPIL, dan
     *    kategori yang luas (Kebijakan Moneter menangkap "inflasi") tidak
     *    boleh merebut artikel tips yang kebetulan menyebut inflasi.
     * 2. Bila tidak, kategori PERTAMA lain yang polanya cocok.
     * 3. Bila tidak ada sama sekali: null, artikelnya dibuang. Kata kuncinya
     *    hanya ada di isi artikel — pada pengambilan sungguhan pertama, itu
     *    Posyandu, menteri yang marah di sawah, dan koin perak Romawi.
     */
    private function classify(string $teks, string $kategoriKueri): ?string
    {
        $semua = config('news.categories');

        if (preg_match('/'.$semua[$kategoriKueri]['pattern'].'/iu', $teks)) {
            return $kategoriKueri;
        }

        foreach ($semua as $slug => $kategori) {
            if (preg_match('/'.$kategori['pattern'].'/iu', $teks)) {
                return $slug;
            }
        }

        return null;
    }

    private function publishedAt(array $a): Carbon
    {
        try {
            return Carbon::parse((string) ($a['pubDate'] ?? ''), (string) ($a['pubDateTZ'] ?? 'UTC'))
                ->timezone(config('app.timezone'));
        } catch (\Throwable) {
            return now();
        }
    }

    /**
     * Judul dan ringkasan dari sumber kadang membawa entitas HTML — dan
     * sebagian sudah rusak di hulu: `&quot;` tiba sebagai `danquot;`, karena
     * sumbernya mengganti setiap `&` dengan kata "dan". Keduanya dipulihkan
     * di sini, lalu tag HTML dan spasi berlebih dibuang.
     */
    private function cleanText(mixed $teks): string
    {
        $teks = (string) $teks;
        $teks = preg_replace('/dan(quot|amp|apos|lt|gt|nbsp|#\d+|#x[0-9a-f]+);/i', '&$1;', $teks);
        $teks = html_entity_decode(strip_tags($teks), ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim(preg_replace('/\s+/u', ' ', $teks));
    }
}
