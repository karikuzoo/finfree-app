import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

/**
 * Riwayat — daftar PENUH aktivitas pengguna (semua setoran + kalkulasi),
 * beda dari "Aktivitas Terbaru" di Dashboard yang dibatasi 10 item
 * (DashboardSummaryService::RECENT_ACTIVITY_LIMIT) dan tanpa filter.
 *
 * Masih kerangka (page dummy), sama seperti News/Index.jsx & Wallet/
 * Index.jsx — butuh endpoint list-lengkap-dengan-paginasi tersendiri
 * (recentActivity() di service saat ini memang sengaja dibatasi untuk
 * Dashboard, bukan untuk ditarik penuh dari sana).
 */
export default function HistoryIndex() {
    const plannedFilters = [
        "Filter berdasarkan tujuan (goal) tertentu",
        "Filter berdasarkan rentang tanggal",
        "Filter jenis aktivitas: setoran atau hasil kalkulasi",
        "Ekspor riwayat ke CSV",
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Riwayat" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold tracking-tight text-text-primary">
                    Riwayat
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Semua aktivitas Anda di FinGoal — tiap setoran yang dicatat
                    dan tiap kalkulasi yang dijalankan — dalam satu daftar yang
                    bisa ditelusuri, tidak dibatasi 10 item terakhir seperti di
                    Dashboard.
                </p>

                <div className="mt-10 rounded-card border border-border bg-bg-card px-6 py-14 text-center">
                    <svg
                        className="mx-auto h-14 w-14 text-text-muted"
                        viewBox="0 0 32 32"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <circle cx="16" cy="16" r="12" />
                        <path d="M16 9v7l5 3" />
                    </svg>

                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Belum ada riwayat lengkap di sini
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Halaman ini masih kerangka — daftar penuh dengan
                        paginasi & filter belum dibangun. Untuk sekarang, 10
                        aktivitas terakhir tetap bisa dilihat dari kartu
                        "Aktivitas Terbaru" di Dashboard.
                    </p>

                    <ul className="mx-auto mt-6 max-w-sm space-y-2 text-left text-xs text-text-muted">
                        {plannedFilters.map((filter) => (
                            <li key={filter} className="flex gap-2">
                                <span aria-hidden="true">·</span>
                                <span>{filter}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
