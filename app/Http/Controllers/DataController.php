<?php

namespace App\Http\Controllers;

use App\Services\BackupService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Data & pengaturan (PRD FR-83, FR-84).
 */
class DataController extends Controller
{
    public function __construct(private BackupService $cadangan) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Data/Index', [
            // Dipakai halamannya untuk menyebut ukuran yang akan diunduh,
            // supaya pengguna tahu cadangannya benar-benar berisi sesuatu.
            'counts' => [
                'accounts' => $user->accounts()->count(),
                'transactions' => $user->transactions()->count(),
                'goals' => $user->goals()->count(),
                'debts' => $user->debts()->count(),
            ],
        ]);
    }

    /**
     * Berkas cadangan JSON.
     *
     * Dikirim sebagai unduhan, bukan ditampilkan di browser: `Content-
     * Disposition: attachment` beserta nama berkas bertanggal, supaya
     * beberapa cadangan tidak saling menimpa di folder Downloads.
     */
    public function download(Request $request): JsonResponse
    {
        $nama = 'arus-cadangan-'.now(config('app.timezone'))->format('Y-m-d').'.json';

        return response()
            ->json(
                $this->cadangan->export($request->user()),
                200,
                [],
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            )
            ->withHeaders([
                'Content-Disposition' => 'attachment; filename="'.$nama.'"',
                // Berisi seluruh catatan keuangan — jangan sampai tersimpan di
                // cache proxy atau riwayat browser bersama.
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ]);
    }

    /**
     * Memulihkan dari berkas — MENGGANTI seluruh data keuangan pengguna.
     *
     * Batas 8 MB sengaja jauh di atas kebutuhan wajar (20 ribu transaksi
     * kira-kira 4 MB) tetapi tetap ada, karena berkas tanpa batas ukuran akan
     * diurai seluruhnya ke memori sebelum sempat divalidasi.
     */
    public function restore(Request $request): RedirectResponse
    {
        $request->validate([
            'berkas' => ['required', 'file', 'mimetypes:application/json,text/plain', 'max:8192'],
        ], [
            'berkas.required' => 'Pilih berkas cadangan terlebih dahulu.',
            'berkas.mimetypes' => 'Berkas harus berformat JSON.',
            'berkas.max' => 'Berkas terlalu besar (maksimal 8 MB).',
        ]);

        $isi = json_decode($request->file('berkas')->get(), true);

        if (! is_array($isi)) {
            throw ValidationException::withMessages([
                'berkas' => 'Berkas tidak bisa dibaca — isinya bukan JSON yang sah.',
            ]);
        }

        $this->cadangan->import($request->user(), $isi);

        return back();
    }
}
