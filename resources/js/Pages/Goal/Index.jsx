import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import Modal from "@/Components/Modal";
import DangerButton from "@/Components/DangerButton";
import SecondaryButton from "@/Components/SecondaryButton";
import { formatRupiah } from "@/utils/format";
import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Daftar tujuan finansial.
 *
 * Beda dari Dompet (Wallet/Index.jsx): Dompet menjawab "uang saya ada di mana
 * saja sekarang", Goal menjawab "saya mau ke mana, dan sudah sejauh apa".
 * Dashboard tetap jadi ringkasan gabungan keduanya untuk tujuan utama.
 *
 * Seluruh angka di sini datang apa adanya dari DashboardSummaryService lewat
 * GoalController — halaman ini tidak menghitung ulang apa pun. Progres dan
 * status on-track punya definisi yang halus; menghitungnya lagi di frontend
 * dipastikan akan menyimpang dari Dashboard suatu hari.
 */
export default function GoalIndex({
    goals,
    totalTarget,
    totalAssets,
    overallProgress,
}) {
    return (
        <AuthenticatedLayout>
            <Head title="Tujuan" />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Tujuan
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Semua tujuan finansial Anda beserta progresnya.
                        </p>
                    </div>

                    {goals.length > 0 && (
                        <Link
                            href={route("goals.create")}
                            className="rounded-lg bg-lime-500 px-4 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                        >
                            Buat Tujuan
                        </Link>
                    )}
                </div>

                {goals.length === 0 ? (
                    <KosongTanpaTujuan />
                ) : (
                    <>
                        <RingkasanKeseluruhan
                            totalAssets={totalAssets}
                            totalTarget={totalTarget}
                            overallProgress={overallProgress}
                            jumlah={goals.length}
                        />

                        <div className="mt-6 space-y-4">
                            {goals.map((goal, urutan) => (
                                <KartuTujuan
                                    key={goal.id}
                                    goal={goal}
                                    /* Tujuan tertua otomatis jadi tujuan utama
                                       di Dashboard (DashboardSummaryService
                                       mengurutkan berdasar created_at). Ditandai
                                       di sini supaya pengguna paham kenapa
                                       justru tujuan itu yang tampil di sana. */
                                    utama={urutan === 0}
                                />
                            ))}
                        </div>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}

function RingkasanKeseluruhan({ totalAssets, totalTarget, overallProgress, jumlah }) {
    return (
        <div className="mt-8 rounded-card border border-border bg-bg-card p-5">
            <div className="flex flex-wrap items-end justify-between gap-4">
                <div>
                    <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                        Seluruh Tujuan
                    </p>
                    <p className="mt-1.5 text-2xl font-bold text-text-primary num-tabular">
                        {formatRupiah(totalAssets)}
                    </p>
                    <p className="mt-0.5 text-sm text-text-secondary num-tabular">
                        dari {formatRupiah(totalTarget)} · {jumlah} tujuan aktif
                    </p>
                </div>

                <p className="text-2xl font-bold text-text-primary num-tabular">
                    {overallProgress.toFixed(1)}%
                </p>
            </div>

            <BatangProgres persen={overallProgress} className="mt-4" />
        </div>
    );
}

function KartuTujuan({ goal, utama }) {
    const [confirmingDeletion, setConfirmingDeletion] = useState(false);
    const { delete: destroy, processing } = useForm();

    const hapusTujuan = () => {
        destroy(route("goals.destroy", goal.id), {
            preserveScroll: true,
            onSuccess: () => setConfirmingDeletion(false),
        });
    };

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="min-w-0">
                    <div className="flex flex-wrap items-center gap-2">
                        <h2 className="text-base font-semibold text-text-primary">
                            {goal.name}
                        </h2>

                        {utama && (
                            <span className="rounded-full bg-lime-softBg px-2.5 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-lime-500">
                                Tujuan Utama
                            </span>
                        )}
                    </div>

                    {/* Label jenis tujuan dihapus bersama pilihannya di form:
                        setiap tujuan kini bertipe "custom", jadi menampilkannya
                        hanya menuliskan "Kustom" di semua kartu. Tujuan tanpa
                        tenggat tidak punya sisa hari, dan barisnya ikut hilang
                        daripada menampilkan keterangan kosong. */}
                    {goal.days_remaining !== null && (
                        <p className="mt-1 text-xs text-text-muted">
                            {goal.days_remaining} hari lagi
                        </p>
                    )}
                </div>

                <div className="flex items-center gap-3">
                    <p className="text-lg font-bold text-text-primary num-tabular">
                        {goal.progress_percentage.toFixed(1)}%
                    </p>
                    <button
                        type="button"
                        onClick={() => setConfirmingDeletion(true)}
                        className="rounded-lg p-1.5 text-text-muted transition hover:bg-state-danger/10 hover:text-state-danger"
                        title="Hapus tujuan"
                    >
                        <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16" />
                        </svg>
                    </button>
                </div>
            </div>

            <BatangProgres persen={goal.progress_percentage} className="mt-4" />

            <div className="mt-3 flex flex-wrap items-center justify-between gap-3">
                <p className="text-sm text-text-secondary num-tabular">
                    {formatRupiah(goal.current_amount)} dari{" "}
                    {formatRupiah(goal.target_amount)}
                </p>

                <StatusOnTrack status={goal.on_track} />
            </div>

            <Modal show={confirmingDeletion} onClose={() => setConfirmingDeletion(false)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-lg font-semibold text-text-primary">
                        Hapus tujuan "{goal.name}"?
                    </h2>
                    <p className="mt-2 text-sm text-text-secondary">
                        Seluruh data setoran dan kalkulasi yang terkait dengan
                        tujuan ini akan ikut terhapus secara permanen dan tidak
                        bisa dikembalikan.
                    </p>

                    <div className="mt-6 flex justify-end gap-3">
                        <SecondaryButton onClick={() => setConfirmingDeletion(false)}>
                            Batal
                        </SecondaryButton>
                        <DangerButton onClick={hapusTujuan} disabled={processing}>
                            {processing ? "Menghapus..." : "Hapus Tujuan"}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

/**
 * `on_track` bernilai null untuk tujuan tanpa tanggal target (dana darurat)
 * atau yang baru dibuat hari ini — bukan kondisi yang perlu diberi lencana,
 * karena memang belum ada apa pun untuk dibandingkan.
 */
function StatusOnTrack({ status }) {
    if (!status) {
        return null;
    }

    if (status.status === "on_track") {
        return (
            <span className="rounded-full bg-bg-cardAlt px-2.5 py-1 text-xs font-semibold text-state-success">
                Sesuai rencana
            </span>
        );
    }

    return (
        <span className="rounded-full bg-bg-cardAlt px-2.5 py-1 text-xs font-semibold text-state-danger num-tabular">
            Tertinggal {formatRupiah(status.gap_amount)}
        </span>
    );
}

function BatangProgres({ persen, className = "" }) {
    const lebar = Math.min(100, Math.max(0, persen));

    return (
        <div
            className={"h-2 w-full overflow-hidden rounded-full bg-bg-cardAlt " + className}
            role="progressbar"
            aria-valuenow={Math.round(lebar)}
            aria-valuemin={0}
            aria-valuemax={100}
        >
            <div
                className="h-full rounded-full bg-lime-500 transition-all"
                style={{ width: `${lebar}%` }}
            />
        </div>
    );
}

function KosongTanpaTujuan() {
    return (
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
                <circle cx="16" cy="16" r="6.5" />
                <circle cx="16" cy="16" r="1.5" fill="currentColor" stroke="none" />
            </svg>

            <h2 className="mt-5 text-lg font-semibold text-text-primary">
                Belum ada tujuan finansial
            </h2>
            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                Tentukan apa yang ingin Anda capai — dana darurat, DP rumah, atau
                pensiun — lalu FinGoal menghitung berapa yang perlu disisihkan
                tiap bulan dan memantau progresnya di sini.
            </p>

            <div className="mt-7">
                <Link
                    href={route("goals.create")}
                    className="inline-block rounded-lg bg-lime-500 px-5 py-3 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Buat Tujuan Pertama
                </Link>
            </div>
        </div>
    );
}
