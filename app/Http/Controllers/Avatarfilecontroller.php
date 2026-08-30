<?php

namespace App\Http\Controllers;

use Illuminate\Http\Response;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Menyajikan file avatar langsung lewat kode Laravel — BUKAN lewat
 * symlink `public/storage` (Storage::disk('public')->url()).
 *
 * Alasan: `php artisan storage:link` di Windows membuat NTFS Junction
 * (bukan symbolic link asli, supaya tidak butuh hak admin — lihat
 * Illuminate\Filesystem\Filesystem::link()). File Explorer Windows bisa
 * menavigasi junction itu tanpa masalah, tapi PHP built-in dev server
 * (`php artisan serve`) punya bug dikenal luas: gagal menyajikan file
 * dengan benar lewat junction, walau filenya sendiri valid — hasilnya
 * avatar tampil "gambar rusak" di browser meski file aslinya sempurna
 * (dikonfirmasi terbuka normal di Windows Photos, di luar PHP sama
 * sekali).
 *
 * Route ini membaca isi file lewat Storage facade (PHP file I/O biasa,
 * bukan lewat static-file passthrough server), jadi tidak terpengaruh
 * bug junction tadi — berfungsi sama baiknya di php artisan serve,
 * Apache, maupun Nginx.
 */
class AvatarFileController extends Controller
{
    private const DISK = 'public';

    private const DIRECTORY = 'avatars';

    public function show(string $filename): StreamedResponse|Response
    {
        // Nama file avatar SELALU dibuat lewat Str::random(40).'.webp' di
        // AvatarService — pola ini menolak apa pun di luar itu, termasuk
        // percobaan path traversal semacam "../../.env".
        if (! preg_match('/^[A-Za-z0-9]{40}\.webp$/', $filename)) {
            abort(404);
        }

        $path = self::DIRECTORY.'/'.$filename;

        if (! Storage::disk(self::DISK)->exists($path)) {
            abort(404);
        }

        return Storage::disk(self::DISK)->response($path, null, [
            'Content-Type' => 'image/webp',
            // Nama filenya acak dan dibuat ulang tiap upload (lihat
            // AvatarService::store — file lama dihapus, path baru
            // dipakai), jadi aman di-cache lama tanpa risiko avatar
            // basi tersangkut di browser pengguna.
            'Cache-Control' => 'public, max-age=31536000, immutable',
        ]);
    }
}