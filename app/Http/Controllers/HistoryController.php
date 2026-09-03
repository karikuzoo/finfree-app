<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Riwayat — daftar PENUH aktivitas pengguna (setoran dicatat, tujuan
 * dibuat, tujuan dihapus — lihat migrasi create_user_activities_table),
 * dipaginasi. Beda dari "Aktivitas Terbaru" di Dashboard yang dibatasi
 * 10 item tanpa halaman berikutnya (DashboardSummaryService::
 * RECENT_ACTIVITY_LIMIT) — di sini semuanya bisa ditelusuri.
 *
 * Pengelompokan per HARI ("aktivitas harian") dilakukan di FRONTEND
 * (History/Index.jsx), bukan di sini — mengelompokkan dulu baru
 * memaginasi supaya batas halaman selalu jatuh tepat di antara dua hari
 * itu rumit tanpa manfaat nyata; aplikasi lain (Gmail, linimasa media
 * sosial) juga membiarkan satu hari terpotong di batas halaman.
 */
class HistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $activities = $request->user()->activities()
            ->latest()
            ->paginate(self::PER_PAGE)
            ->through(fn ($row) => [
                'type' => $row->type,
                'goal_name' => $row->goal_name,
                'amount' => $row->amount !== null ? (float) $row->amount : null,
                'occurred_at' => $row->created_at->toIso8601String(),
            ]);

        return Inertia::render('History/Index', [
            'activities' => $activities,
        ]);
    }
}
