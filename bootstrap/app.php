<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->append(\App\Http\Middleware\SecurityHeaders::class);

        $middleware->web(append: [
            // Sesi lain langsung keluar begitu kata sandi berganti: middleware
            // ini menyimpan sidik kata sandi di sesi dan mengeluarkan sesi
            // yang sidiknya tidak lagi cocok. Lihat PasswordController.
            \Illuminate\Session\Middleware\AuthenticateSession::class,
            \App\Http\Middleware\HandleInertiaRequests::class,
            \Illuminate\Http\Middleware\AddLinkHeadersForPreloadedAssets::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // 419 (token CSRF kedaluwarsa) pada kiriman dari halaman Inertia —
        // biasanya karena halaman dibiarkan terbuka melewati SESSION_LIFETIME.
        //
        // Tanpa ini, Inertia menampilkan halaman galat 419 di dalam MODAL
        // ber-iframe di atas halaman yang sedang dibuka: isian form hilang
        // dari pandangan, dan pengguna terjebak di layar galat. Sebagai
        // gantinya:
        //  - sesi sudah habis (tamu): ke halaman masuk dengan penjelasan,
        //    lalu kembali ke halaman tadi sesudah masuk;
        //  - masih masuk (token berganti, mis. masuk ulang di tab lain):
        //    kembali ke halaman tadi dengan pemberitahuan untuk mengirim ulang.
        // Kiriman yang bukan dari Inertia tetap mendapat halaman errors/419.
        $exceptions->respond(function (\Symfony\Component\HttpFoundation\Response $response, \Throwable $e, \Illuminate\Http\Request $request) {
            if ($response->getStatusCode() !== 419 || ! $request->header('X-Inertia')) {
                return $response;
            }

            // url()->previous() membaca header Referer apa adanya. Hanya alamat
            // Arus sendiri yang dipakai, supaya ini tidak jadi pengalih ke
            // situs lain.
            $sebelumnya = url()->previous();
            if (parse_url($sebelumnya, PHP_URL_HOST) !== $request->getHost()) {
                $sebelumnya = url('/');
            }

            // 303, bukan 302: galat ini terjadi SEBELUM HandleInertiaRequests,
            // yang biasanya mengubah 302 menjadi 303. Dengan 302, browser
            // mengulang PATCH/PUT/DELETE ke alamat tujuan alih-alih GET.
            if ($request->routeIs('logout')) {
                return redirect('/', 303);
            }

            // Ke halaman masuk hanya bila kirimannya memang menuntut login.
            // Tamu di halaman publik (cek kesehatan KPR di kalkulator) cukup
            // dikembalikan ke halamannya.
            $butuhLogin = in_array('auth', $request->route()?->gatherMiddleware() ?? [], true);

            if ($butuhLogin && ! $request->user()) {
                // Kembali ke halaman TADI, bukan ke alamat kiriman (POST) —
                // redirect()->guest() akan mencatat alamat kiriman.
                redirect()->setIntendedUrl($sebelumnya);

                return redirect()->route('login', [], 303)->with(
                    'status',
                    'Sesi Anda berakhir karena terlalu lama tidak aktif. Silakan masuk lagi, lalu kirim ulang isiannya.',
                );
            }

            return redirect()->to($sebelumnya, 303)->with(
                'notice',
                'Halaman ini terlalu lama terbuka, jadi kiriman tadi belum tersimpan. Periksa isiannya, lalu kirim ulang.',
            );
        });
    })->create();
