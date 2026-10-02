<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Header keamanan untuk setiap respons, termasuk halaman error dan unduhan.
 *
 * Dipasang global, bukan di grup `web`: halaman 404/500 dan berkas ekspor
 * juga respons yang bisa dibingkai atau ditebak jenisnya oleh browser.
 *
 * CSP-nya sengaja hanya `frame-ancestors`. CSP lengkap (script-src dan
 * kawan-kawan) memutus server Vite saat pengembangan dan menuntut nonce di
 * setiap skrip Inertia — itu pekerjaan tersendiri, bukan tambalan kecil.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        $headers = [
            // Arus tidak pernah ditampilkan di dalam bingkai situs lain. Tanpa
            // ini, halaman lain bisa memuat Arus di iframe transparan lalu
            // memancing klik ke tombol hapus (clickjacking). X-Frame-Options
            // untuk browser lama, frame-ancestors untuk yang baru.
            'X-Frame-Options' => 'DENY',
            'Content-Security-Policy' => "frame-ancestors 'none'",
            // Browser tidak boleh menebak jenis berkas dari isinya — misalnya
            // menjalankan unggahan sebagai skrip.
            'X-Content-Type-Options' => 'nosniff',
            // Alamat lengkap (beserta kueri) tidak ikut terkirim ke situs lain
            // saat pengguna mengklik tautan berita keluar.
            'Referrer-Policy' => 'strict-origin-when-cross-origin',
        ];

        // HSTS hanya di produksi lewat HTTPS. Di mesin pengembangan, header
        // ini membuat browser menolak http://127.0.0.1 berbulan-bulan.
        if ($request->isSecure() && app()->environment('production')) {
            $headers['Strict-Transport-Security'] = 'max-age=31536000; includeSubDomains';
        }

        foreach ($headers as $name => $value) {
            // Jangan menimpa header yang sengaja dipasang respons tertentu.
            if (! $response->headers->has($name)) {
                $response->headers->set($name, $value);
            }
        }

        return $response;
    }
}
