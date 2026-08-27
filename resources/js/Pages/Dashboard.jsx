import PrimaryButton from '@/Components/PrimaryButton';
import SummaryCard from '@/Components/SummaryCard';
import GoalProgressList from '@/Components/GoalProgressList';
import RecentActivityList from '@/Components/RecentActivityList';
import AssetGrowthChart from '@/Components/AssetGrowthChart';
import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import { formatRupiah } from '@/utils/format';
import { Head, usePage } from '@inertiajs/react';

/**
 * Dashboard bercabang menjadi dua tampilan berdasarkan `summary.active_goals_count`
 * yang dikirim DashboardController lewat DashboardSummaryService (CLAUDE.md §6.9):
 *
 * - Kosong (0 goal): empty state — layar pertama pengguna baru, DESIGN.md §9.1
 *   menyebutnya layar terpenting untuk konversi. Markup ini TIDAK diubah dari
 *   versi sebelumnya, hanya dipindah ke cabang kondisional.
 * - Terisi: kartu ringkasan, grafik pertumbuhan aset, daftar progress tujuan,
 *   dan aktivitas terbaru — semuanya murni menampilkan apa yang sudah
 *   diagregasi backend, tidak menghitung ulang apa pun di sini.
 */
export default function Dashboard() {
    const { auth, summary } = usePage().props;
    const hasGoals = summary.active_goals_count > 0;

    return (
        <AuthenticatedLayout
            header={
                <div>
                    <h1 className="text-2xl font-bold leading-tight text-text-primary">
                        Halo, {auth.user.name.split(' ')[0]}
                    </h1>
                    <p className="mt-1 text-sm text-text-secondary">
                        Ringkasan perencanaan keuangan Anda.
                    </p>
                </div>
            }
        >
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {!hasGoals ? (
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
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-6">
                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-4">
                                <SummaryCard
                                    label="Total Aset Terkumpul"
                                    value={formatRupiah(summary.total_assets)}
                                />
                                <SummaryCard
                                    label="Target Keseluruhan"
                                    value={formatRupiah(summary.total_target)}
                                />
                                <SummaryCard
                                    label="Progress Keseluruhan"
                                    value={`${summary.overall_progress_percentage.toFixed(1)}%`}
                                />
                                <SummaryCard
                                    label="Tujuan Aktif"
                                    value={`${summary.active_goals_count}`}
                                />
                            </div>

                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <h2 className="text-base font-semibold text-text-primary">
                                    Pertumbuhan Aset (12 Bulan Terakhir)
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Nilai akumulasi aset bersih dari seluruh tujuan aktif.
                                </p>
                                <div className="mt-4">
                                    <AssetGrowthChart series={summary.asset_growth_series} />
                                </div>
                            </div>

                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <h2 className="text-base font-semibold text-text-primary">
                                    Ringkasan Target Aktif
                                </h2>
                                <GoalProgressList goals={summary.goals} />
                            </div>
                        </div>

                        <div className="space-y-6">
                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <h2 className="text-sm font-semibold text-text-primary">
                                    Aktivitas Terbaru
                                </h2>
                                <RecentActivityList activities={summary.recent_activity} />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
