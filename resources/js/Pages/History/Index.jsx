import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { describeActivity } from "@/utils/activity";
import { formatJakartaDateLong, formatJakartaTime } from "@/utils/timezone";
import { formatRupiah } from "@/utils/format";

/**
 * Mengelompokkan daftar aktivitas (sudah terurut terbaru dulu dari
 * backend) jadi { tanggal: [aktivitas, ...] } — dipakai langsung sebagai
 * urutan render karena Object.entries mempertahankan urutan penyisipan
 * untuk key string, dan key-nya (potongan tanggal dari `occurred_at`,
 * "2026-09-03") disisipkan sesuai urutan kemunculan aktivitasnya.
 *
 * Ambil tanggalnya lewat SLICE STRING langsung dari `occurred_at`
 * (bukan lewat `new Date(...)` lalu baca getFullYear()/getMonth()/dst),
 * karena backend sudah mengirim ISO8601 dengan offset WIB yang benar
 * (Carbon::toIso8601String() di HistoryController) — memotong 10
 * karakter pertama string itu sudah pasti tanggal WIB yang tepat, tanpa
 * perlu Date sama sekali, jadi tidak mungkin salah zona waktu (lihat
 * resources/js/utils/timezone.js untuk kelas bug yang dihindari ini).
 */
function kelompokkanPerHari(aktivitas) {
    const kelompok = {};

    for (const item of aktivitas) {
        const tanggal = item.occurred_at.slice(0, 10);
        (kelompok[tanggal] ??= []).push(item);
    }

    return kelompok;
}

function ItemAktivitas({ activity }) {
    const isSetoran = activity.type === "contribution_recorded";

    return (
        <div className="flex items-start justify-between gap-4 py-3">
            <div className="min-w-0">
                <p className="text-sm text-text-primary">
                    {describeActivity(activity)}
                </p>
                <p className="mt-0.5 text-xs text-text-muted">
                    {formatJakartaTime(activity.occurred_at)} WIB
                </p>
            </div>

            {isSetoran && (
                <p className="shrink-0 num-tabular text-sm font-semibold text-lime-500">
                    +{formatRupiah(activity.amount)}
                </p>
            )}
        </div>
    );
}

/**
 * Riwayat — daftar PENUH aktivitas pengguna, dikelompokkan per hari.
 * Datanya sendiri (`activities`, sebuah paginator Laravel) sudah
 * dipaginasi & diurutkan oleh HistoryController — halaman ini murni
 * mengelompokkan untuk tampilan, tidak mengurutkan ulang atau memangkas
 * apa pun.
 */
export default function HistoryIndex({ activities }) {
    const kelompok = kelompokkanPerHari(activities.data);
    const tanggalTerurut = Object.keys(kelompok);

    return (
        <AuthenticatedLayout>
            <Head title="Riwayat" />

            <div className="mx-auto max-w-3xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                    Riwayat
                </h1>
                <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                    Semua aktivitas Anda di FinGoal, dikelompokkan per hari —
                    tiap setoran yang dicatat dan tiap tujuan yang dibuat atau
                    dihapus.
                </p>

                {activities.data.length === 0 ? (
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
                            Belum ada aktivitas
                        </h2>
                        <p className="mx-auto mt-2 max-w-sm text-sm text-text-secondary">
                            Setoran yang Anda catat dan tujuan yang Anda buat
                            akan muncul di sini.
                        </p>
                    </div>
                ) : (
                    <div className="mt-8 space-y-6">
                        {tanggalTerurut.map((tanggal) => (
                            <div
                                key={tanggal}
                                className="rounded-card border border-border bg-bg-card px-5 py-4"
                            >
                                <h2 className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                                    {formatJakartaDateLong(
                                        kelompok[tanggal][0].occurred_at,
                                    )}
                                </h2>

                                <div className="mt-1 divide-y divide-border">
                                    {kelompok[tanggal].map((activity, index) => (
                                        <ItemAktivitas
                                            key={index}
                                            activity={activity}
                                        />
                                    ))}
                                </div>
                            </div>
                        ))}
                    </div>
                )}

                {(activities.prev_page_url || activities.next_page_url) && (
                    <div className="mt-8 flex items-center justify-between">
                        {activities.prev_page_url ? (
                            <Link
                                href={activities.prev_page_url}
                                className="rounded-lg border border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition hover:bg-bg-cardAlt"
                            >
                                ← Lebih baru
                            </Link>
                        ) : (
                            <span />
                        )}

                        <p className="text-xs text-text-muted">
                            Halaman {activities.current_page} dari{" "}
                            {activities.last_page}
                        </p>

                        {activities.next_page_url ? (
                            <Link
                                href={activities.next_page_url}
                                className="rounded-lg border border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition hover:bg-bg-cardAlt"
                            >
                                Lebih lama →
                            </Link>
                        ) : (
                            <span />
                        )}
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
