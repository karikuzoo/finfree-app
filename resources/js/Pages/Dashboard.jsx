import SummaryCard from "@/Components/SummaryCard";
import GoalHeroCard from "@/Components/GoalHeroCard";
import ActivityCalendar from "@/Components/ActivityCalendar";
import DailyReminderBanner from "@/Components/DailyReminderBanner";
import AllocationBreakdownChart from "@/Components/AllocationBreakdownChart";
import GoalProgressList from "@/Components/GoalProgressList";
import RecentActivityList from "@/Components/RecentActivityList";
import TodayReminders from "@/Components/TodayReminders";
import AssetGrowthChart from "@/Components/AssetGrowthChart";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import GoalContributionForm from "@/Components/GoalContributionForm";

/**
 * Dashboard bercabang menjadi dua tampilan berdasarkan `summary.active_goals_count`
 * yang dikirim DashboardController lewat DashboardSummaryService (CLAUDE.md §6.9):
 *
 * - Kosong (0 goal): GoalHeroCard & ActivityCalendar tetap dirender, tapi
 *   dengan data contoh/dummy (placeholderGoal, placeholderCalendar) —
 *   bukan dari backend — supaya pengguna baru langsung melihat bentuk
 *   tampilannya sebelum membuat goal sungguhan. Diberi label "Contoh" di
 *   tiap kartu (lihat prop `placeholder`) agar tidak disangka data asli.
 *   Tombol "Buat Tujuan Pertama" masih non-fungsional (fitur CRUD Goal
 *   belum ada — DESIGN.md §9.1), tapi sengaja diberi warna solid
 *   (sama seperti CTA "Coba kalkulatornya" di Welcome) supaya lebih
 *   terlihat, bukan warna redup seperti versi sebelumnya.
 * - Terisi: kartu utama (target goal tertua + progres, badge on-track,
 *   hitung mundur, streak, dan form catat setoran — semuanya di
 *   GoalHeroCard), banner pengingat harian (DailyReminderBanner, murni
 *   in-app, hitung dari data yang sama, bukan notifikasi push/email),
 *   kalender aktivitas bulan berjalan dan pie chart breakdown alokasi
 *   instrumen (ActivityCalendar & AllocationBreakdownChart) — dua kartu
 *   terpisah tapi sebaris lewat grid, grafik pertumbuhan aset, daftar
 *   progress tujuan, dan aktivitas terbaru — semuanya murni menampilkan
 *   apa yang sudah diagregasi backend, tidak menghitung ulang apa pun
 *   di sini.
 */
export default function Dashboard() {
    const { auth, summary, calendar, todayReminders } = usePage().props;
    const hasGoals = summary.active_goals_count > 0;

    const [selectedGoalId, setSelectedGoalId] = useState(summary.primary_goal?.id ?? null);
    const selectedGoal = summary.goals?.find(g => g.id == selectedGoalId) || summary.primary_goal;

    const todayIso = todayInJakarta();
    const todayContributionAmount = hasGoals
        ? summary.contribution_calendar.find(item => item.date === todayIso)?.amount || 0
        : 0;

    // Data contoh untuk GoalHeroCard, ActivityCalendar, & donut alokasi
    // saat pengguna belum punya goal sama sekali — angka target 500 juta
    // dipakai supaya konsisten dengan ilustrasi "Contoh perhitungan" di
    // halaman Welcome (docs/fixtures/calculator-cases.json, kasus
    // "dasar-tanpa-inflasi"), bukan angka karangan baru.
    const placeholderGoal = {
        id: null,
        name: "DP Rumah Pertama",
        target_amount: 500000000,
        current_amount: 150000000,
        daily_savings_target: 50000,
        progress_percentage: 30,
        days_remaining: 320,
        on_track: { status: "on_track", gap_amount: 0 },
        suggested_allocation: [
            { instrument: "Saham", percentage: 30 },
            { instrument: "Obligasi/SBN", percentage: 30 },
            { instrument: "Deposito", percentage: 25 },
            { instrument: "Emas", percentage: 15 },
        ],
    };

    const [todayYear, todayMonth, todayDay] = todayIso.split("-").map(Number);
    const pad = (n) => String(n).padStart(2, "0");
    const dateStr = (day) => `${todayYear}-${pad(todayMonth)}-${pad(day)}`;
    const placeholderCalendar = [3, 8, 12, 17, 21]
        .filter((day) => day <= todayDay)
        .map((day, index) => ({
            date: dateStr(day),
            amount: [50000, 75000, 50000, 100000, 50000][index],
        }));

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                <h1 className="text-2xl font-bold leading-tight text-text-primary">
                    Halo, {auth.user.name.split(" ")[0]}
                </h1>
                <p className="mt-1 text-sm text-text-secondary">
                    Ringkasan perencanaan keuangan Anda.
                </p>
            </div>

            <div className="mx-auto max-w-7xl px-4 py-8 sm:px-6 lg:px-8">
                {!hasGoals ? (
                    <div className="space-y-6">
                        <GoalHeroCard 
                            goals={[placeholderGoal]} 
                            selectedGoal={placeholderGoal} 
                            placeholder 
                        />
                        
                        <div className="rounded-card border border-border bg-bg-card p-5" id="catat-setoran">
                            <h2 className="text-sm font-semibold text-text-primary mb-4">Catat Setoran</h2>
                            <p className="text-xs text-text-muted">Form pencatatan setoran akan muncul di sini setelah Anda membuat tujuan finansial.</p>
                        </div>

                        <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <ActivityCalendar
                                    calendar={placeholderCalendar}
                                    placeholder
                                />
                            </div>

                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <AllocationBreakdownChart
                                    allocation={
                                        placeholderGoal.suggested_allocation
                                    }
                                    placeholder
                                />
                            </div>
                        </div>

                        <div className="rounded-card border border-border bg-bg-card px-6 py-12 text-center">
                            <svg
                                className="mx-auto h-12 w-12 text-text-muted"
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

                            <h2 className="mt-5 text-lg font-semibold text-text-primary">
                                Belum ada tujuan finansial
                            </h2>
                            <p className="mx-auto mt-2 max-w-sm text-sm text-text-secondary">
                                Angka di atas hanyalah contoh. Tentukan target
                                Anda sendiri — dana darurat, DP rumah, atau
                                pensiun — lalu FinGoal menghitung berapa yang
                                perlu disisihkan tiap bulan.
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
                    </div>
                ) : (
                    <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_320px]">
                        <div className="space-y-6">
                            <DailyReminderBanner
                                goal={selectedGoal}
                                streakDays={summary.streak_days}
                                contributedToday={summary.contribution_calendar.some(
                                    (item) =>
                                        item.date === todayInJakarta() &&
                                        item.amount > 0,
                                )}
                            />

                            <GoalHeroCard
                                goals={summary.goals}
                                selectedGoal={selectedGoal}
                                onGoalChange={setSelectedGoalId}
                                streakDays={summary.streak_days}
                                todayContributionAmount={todayContributionAmount}
                            />

                            <div className="rounded-card border border-border bg-bg-card p-5" id="catat-setoran">
                                <h2 className="text-sm font-semibold text-text-primary mb-4">Catat Setoran</h2>
                                <GoalContributionForm key={selectedGoal.id} goalId={selectedGoal.id} />
                            </div>

                            <div className="grid grid-cols-1 gap-6 lg:grid-cols-2">
                                <div className="rounded-card border border-border bg-bg-card p-5">
                                    <ActivityCalendar
                                        calendar={calendar}
                                        goals={summary.goals ?? []}
                                    />
                                </div>

                                <div className="rounded-card border border-border bg-bg-card p-5">
                                    <AllocationBreakdownChart
                                        allocation={
                                            selectedGoal.suggested_allocation
                                        }
                                    />
                                </div>
                            </div>

                            <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 xl:grid-cols-3">
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
                                    Pertumbuhan Aset — {selectedGoal?.name ?? 'Pilih Tujuan'}
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Nilai akumulasi aset untuk tujuan yang dipilih.
                                </p>
                                <div className="mt-4">
                                    <AssetGrowthChart
                                        series={selectedGoal?.asset_growth_series ?? []}
                                    />
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
                            {/* Di atas Aktivitas Terbaru: pengingat menuntut
                                tindakan hari ini, sedangkan aktivitas terbaru
                                hanya catatan apa yang sudah lewat. */}
                            <TodayReminders reminders={todayReminders} />

                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <h2 className="text-sm font-semibold text-text-primary">
                                    Aktivitas Terbaru
                                </h2>
                                <RecentActivityList
                                    activities={summary.recent_activity}
                                />
                            </div>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
