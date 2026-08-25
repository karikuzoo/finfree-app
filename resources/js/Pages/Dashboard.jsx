import PrimaryButton from '@/Components/PrimaryButton';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { Head, usePage } from '@inertiajs/react';

/**
 * Dashboard masih menampilkan empty state karena fitur Tujuan belum ada.
 *
 * Ini disengaja: layar pertama pengguna baru adalah dashboard tanpa satu pun
 * tujuan, dan DESIGN.md §9.1 menyebutnya layar terpenting untuk konversi.
 * Menggarapnya lebih dulu berarti pola empty state sudah jadi sebelum ada
 * data yang menutupinya.
 *
 * Agregasi sungguhan akan masuk lewat props `summary` dari
 * DashboardSummaryService (CLAUDE.md §6.9).
 */
export default function Dashboard() {
    const user = usePage().props.auth.user;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold leading-tight text-text-primary">
                        Halo, {user.name.split(' ')[0]}
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        Ringkasan perencanaan keuangan Anda.
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <div className="rounded-card border border-border bg-bg-card px-6 py-16 text-center">
                    <svg
                        className="mx-auto h-16 w-16 text-text-muted"
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
                        <circle cx="16" cy="16" r="1.5" />
                    </svg>

                    <h2 className="mt-6 text-lg font-semibold text-text-primary">
                        Belum ada tujuan finansial
                    </h2>
                    <p className="mx-auto mt-2 max-w-sm text-sm text-text-secondary">
                        Tentukan target Anda — dana darurat, DP rumah, atau
                        pensiun — lalu FinGoal menghitung berapa yang perlu
                        disisihkan tiap bulan.
                    </p>

                    <div className="mt-7">
                        <PrimaryButton disabled>
                            Buat Tujuan Pertama
                        </PrimaryButton>
                        <p className="mt-3 text-xs text-text-muted">
                            Tersedia di Rilis 1 — fitur Tujuan sedang dibangun.
                        </p>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
