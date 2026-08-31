<?php

namespace App\Http\Controllers;

use App\Services\DashboardSummaryService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request, DashboardSummaryService $summary): Response
    {
        $request->validate([
            // Bulan yang sedang dilihat di kalender aktivitas, format YYYY-MM.
            // Divalidasi karena nilainya datang dari query string dan langsung
            // dipakai membentuk rentang tanggal.
            'bulan' => ['nullable', 'date_format:Y-m'],
        ]);

        // Tanda seru pada '!Y-m' mereset seluruh bagian tanggal yang tidak
        // disebutkan ke nilai awal (tanggal 1, pukul 00:00).
        //
        // Tanpa itu, PHP mengisi tanggalnya dari HARI INI — sehingga pada
        // tanggal 31, permintaan '2026-06' diurai sebagai "31 Juni", tanggal
        // yang tidak ada, lalu meluber menjadi 1 Juli. Akibatnya pengguna
        // meminta Juni tetapi mendapat Juli, dan kalender terlihat melompat
        // atau macet. Bug ini hanya muncul pada tanggal 29–31, jadi mudah
        // lolos dari pengujian yang dijalankan di awal bulan.
        $bulan = $request->filled('bulan')
            ? Carbon::createFromFormat('!Y-m', $request->string('bulan')->toString())
            : Carbon::now()->startOfMonth();

        return Inertia::render('Dashboard', [
            'summary' => $summary->forUser($request->user()),

            // Nilai biasa, BUKAN closure. Di Inertia, prop berupa closure
            // punya aturan evaluasi tersendiri yang mudah keliru dipakai —
            // dan kalender ini murah dihitung, jadi tidak ada yang dihemat
            // dengan menundanya.
            'calendar' => $summary->calendarForMonth($request->user(), $bulan),
        ]);
    }
}
