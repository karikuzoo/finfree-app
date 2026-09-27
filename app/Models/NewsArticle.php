<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Satu artikel di cache berita. Diisi hanya oleh NewsIngestService — tidak
 * ada jalan tulis dari pengguna.
 */
class NewsArticle extends Model
{
    protected $table = 'news_article_cache';

    // Waktunya sendiri: `fetched_at` (kapan diambil) dan `published_at`.
    public $timestamps = false;

    protected $fillable = [
        'url',
        'title',
        'summary',
        'image_url',
        'source',
        'source_name',
        'category',
        'published_at',
        'fetched_at',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'fetched_at' => 'datetime',
    ];
}
