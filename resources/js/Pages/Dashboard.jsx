import ActivityCalendar from "@/Components/ActivityCalendar";
import AllocationBreakdownChart from "@/Components/AllocationBreakdownChart";
import AssetCompositionChart from "@/Components/AssetCompositionChart";
import AssetGrowthChart from "@/Components/AssetGrowthChart";
import DailyReminderBanner from "@/Components/DailyReminderBanner";
import GoalHeroCard from "@/Components/GoalHeroCard";
import GoalProgressList from "@/Components/GoalProgressList";
import RecentActivityList from "@/Components/RecentActivityList";
import SummaryCard from "@/Components/SummaryCard";
import TodayReminders from "@/Components/TodayReminders";
import { GoalIcon, WalletIcon, CalculatorIcon } from "@/Components/Icons";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, Link, router, usePage } from "@inertiajs/react";
import { useState } from "react";

/**
 * Dashboard menampilkan dua lapisan yang sengaja dipisah di backend:
 *
 * - `wealth` — UANG: kekayaan bersih, arus kas bulan ini, komposisi aset,
 *   transaksi terakhir.
 * - `summary` — TUJUAN: progres, alokasi, saran instrumen.
 *
 * Urutannya bukan kebetulan. Pertanyaan pertama yang dibawa orang saat membuka
 * aplikasi keuangan adalah "berapa uang saya sekarang", bukan "sejauh apa
 * target saya". Tujuan datang setelahnya, ketika pertanyaan pertama sudah
 * terjawab.
 *
 * Seluruh angka datang jadi dari backend (CLAUDE.md §6.9) — tidak ada
 * penjumlahan yang diulang di sini.
 */
export default function Dashboard() {
    const { auth, summary, calendar, todayReminders, wealth } = usePage().props;

    const hasGoals = summary.active_goals_count > 0;
    const hasAccounts = Boolean(wealth?.has_accounts);

    const [selectedGoalId, setSelectedGoalId] = useState(summary.primary_goal?.id ?? null);
    const selectedGoal =
        summary.goals?.find((g) => g.id == selectedGoalId) || summary.primary_goal;

    const [granularitas, setGranularitas] = useState("monthly");

    // Satu parameter `bulan` dipakai bersama oleh pemilih bulan di header dan
    // panah geser di dalam kalender. Keduanya menulis ke tempat yang sama,
    // jadi tidak mungkin berselisih menampilkan periode berbeda.
    const bulanAktif = calendar?.month ?? todayInJakarta().slice(0, 7);

    const gantiBulan = (nilai) => {
        router.get(route("dashboard"), { bulan: nilai }, { preserveScroll: true });
    };

    return (
        <AuthenticatedLayout>
            <Head title="Dashboard" />

            <div className="mx-auto max-w-7xl space-y-6 px-4 py-8 sm:px-6 lg:px-8">
                <Kepala
                    nama={auth.user.name.split(" ")[0]}
                    bulan={bulanAktif}
                    onGantiBulan={gantiBulan}
                />

                {hasAccounts ? (
                    <KartuKekayaan wealth={wealth} bulan={bulanAktif} />
                ) : (
                    <BelumAdaRekening />
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="rounded-card border border-border bg-bg-card p-5">
                        <div className="flex flex-wrap items-start justify-between gap-3">
                            <div>
                                <h2 className="text-base font-semibold text-text-primary">
                                    Pertumbuhan kekayaan
                                </h2>
                                <p className="mt-1 text-sm text-text-secondary">
                                    Total nilai aset Anda dari waktu ke waktu,
                                    dihitung dari riwayat transaksi.
                                </p>
                            </div>

                            <PilihRentang
                                nilai={granularitas}
                                onGanti={setGranularitas}
                            />
                        </div>

                        <div className="mt-4">
                            <AssetGrowthChart
                                series={summary.asset_growth_series?.[granularitas] ?? []}
                                granularity={granularitas}
                            />
                        </div>
                    </div>

                    <div className="rounded-card border border-border bg-bg-card p-5">
                        <AssetCompositionChart
                            composition={wealth?.composition ?? []}
                            totalAssets={wealth?.total_assets ?? 0}
                        />
                    </div>
                </div>

                {hasGoals ? (
                    <>
                        <DailyReminderBanner goal={selectedGoal} />

                        <GoalHeroCard
                            goals={summary.goals}
                            selectedGoal={selectedGoal}
                            onGoalChange={setSelectedGoalId}
                        />

                        <div className="grid grid-cols-1 gap-4 sm:grid-cols-3">
                            <SummaryCard
                                label="Target keseluruhan"
                                value={formatRupiah(summary.total_target)}
                                icon={GoalIcon}
                                hint="Gabungan nominal seluruh tujuan aktif"
                            />
                            <SummaryCard
                                label="Progres keseluruhan"
                                value={`${summary.overall_progress_percentage.toFixed(1)}%`}
                                icon={CalculatorIcon}
                                tone="lilac"
                                hint="Dana ditandai dibanding total target"
                            />
                            <SummaryCard
                                label="Tujuan aktif"
                                value={String(summary.active_goals_count)}
                                icon={GoalIcon}
                                tone="blue"
                                hint="Wujudkan satu per satu"
                            />
                        </div>
                    </>
                ) : (
                    <BelumAdaTujuan />
                )}

                <div className="grid grid-cols-1 gap-6 lg:grid-cols-[1fr_360px]">
                    <div className="space-y-6">
                        <div className="rounded-card border border-border bg-bg-card p-5">
                            <ActivityCalendar calendar={calendar} />
                        </div>

                        {hasGoals && (
                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <AllocationBreakdownChart
                                    allocation={selectedGoal.suggested_allocation}
                                    comparison={selectedGoal.allocation_comparison}
                                />
                            </div>
                        )}

                        {hasGoals && (
                            <div className="rounded-card border border-border bg-bg-card p-5">
                                <h2 className="text-base font-semibold text-text-primary">
                                    Ringkasan target aktif
                                </h2>
                                <GoalProgressList goals={summary.goals} />
                            </div>
                        )}
                    </div>

                    <div className="space-y-6">
                        <TransaksiTerbaru
                            transaksi={wealth?.recent_transactions ?? []}
                            adaRekening={hasAccounts}
                        />

                        {/* Di atas Aktivitas Terbaru: pengingat menuntut
                            tindakan hari ini, sedangkan aktivitas terbaru
                            hanya catatan apa yang sudah lewat. */}
                        <TodayReminders reminders={todayReminders} />

                        <div className="rounded-card border border-border bg-bg-card p-5">
                            <h2 className="text-sm font-semibold text-text-primary">
                                Aktivitas terbaru
                            </h2>
                            <RecentActivityList activities={summary.recent_activity} />
                        </div>
                    </div>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}

function Kepala({ nama, bulan, onGantiBulan }) {
    return (
        <div className="flex flex-wrap items-start justify-between gap-5">
            <div>
                <p className="text-xs font-semibold tracking-[.15em] text-text-muted">
                    DASHBOARD KEUANGAN
                </p>
                <h1 className="mt-2 text-3xl font-semibold leading-tight tracking-tight text-text-primary">
                    Halo, {nama}
                </h1>
                <p className="mt-2 text-sm text-text-secondary">
                    Lihat perkembangan dana dan langkah berikutnya untuk tujuanmu.
                </p>
            </div>

            <div className="flex flex-wrap items-center gap-2">
                {/*
                    Menulis ke parameter `bulan` yang sama dengan panah geser di
                    dalam kalender — sengaja satu sumber, supaya angka arus kas
                    di atas dan kalender di bawah tidak pernah bicara tentang
                    periode yang berbeda.
                */}
                <input
                    type="month"
                    value={bulan}
                    onChange={(e) => onGantiBulan(e.target.value)}
                    max={todayInJakarta().slice(0, 7)}
                    aria-label="Pilih bulan"
                    className="num-tabular rounded-lg border-border-strong bg-bg-base text-sm text-text-primary focus:border-lime-500 focus:ring-lime-500"
                />

                <Link
                    href={route("transactions.index")}
                    className="rounded-lg border border-border-strong px-4 py-2.5 text-sm font-semibold text-text-secondary transition hover:border-text-muted hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    Catat transaksi
                </Link>

                <Link
                    href={route("goals.create")}
                    className="rounded-lg bg-lime-500 px-4 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Tujuan baru
                </Link>
            </div>
        </div>
    );
}

/**
 * Empat angka teratas. Kekayaan bersih dibedakan dengan kartu gradien karena
 * ia satu-satunya yang menjawab "berapa uang saya sebenarnya" — tiga sisanya
 * menjelaskan pergerakan bulan ini.
 */
function KartuKekayaan({ wealth, bulan }) {
    const arus = wealth.cash_flow;

    return (
        <div className="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
            <div className="rounded-card border border-lime-600 bg-gradient-to-br from-lime-softBg to-bg-surface p-6">
                <div className="flex items-center justify-between gap-3 text-sm text-text-secondary">
                    <span>Total kekayaan bersih</span>
                    <WalletIcon className="h-5 w-5 text-lime-500" />
                </div>
                <p className="num-tabular mt-5 break-words text-3xl font-semibold tracking-tight text-text-primary">
                    {formatRupiah(wealth.net_worth)}
                </p>
                <p className="num-tabular mt-4 border-t border-lime-600 pt-3 text-xs leading-6 text-text-secondary">
                    Aset {formatRupiah(wealth.total_assets)} · Utang{" "}
                    {formatRupiah(wealth.total_debt)}
                </p>
            </div>

            <SummaryCard
                label={`Pemasukan ${labelBulan(bulan)}`}
                value={formatRupiah(arus.income)}
                icon={WalletIcon}
                hint="Gaji dan penghasilan lain yang tercatat"
            />
            <SummaryCard
                label={`Pengeluaran ${labelBulan(bulan)}`}
                value={formatRupiah(arus.expense)}
                icon={CalculatorIcon}
                tone="lilac"
                hint={`Pokok utang terpisah: ${formatRupiah(arus.principal)}`}
            />
            <SummaryCard
                label="Sisa arus kas"
                value={formatRupiah(arus.net)}
                icon={GoalIcon}
                tone="blue"
                hint="Setelah pengeluaran & pokok utang"
            />
        </div>
    );
}

/** "2026-09" → "September" — dipakai melabeli kartu arus kas. */
function labelBulan(bulan) {
    const [tahun, nomor] = bulan.split("-").map(Number);

    return new Date(tahun, nomor - 1, 1).toLocaleDateString("id-ID", { month: "long" });
}

function PilihRentang({ nilai, onGanti }) {
    return (
        <div className="flex gap-1 rounded-lg bg-bg-cardAlt p-1">
            {[
                { key: "monthly", label: "Bulanan" },
                { key: "daily", label: "Harian" },
            ].map((opsi) => (
                <button
                    key={opsi.key}
                    type="button"
                    onClick={() => onGanti(opsi.key)}
                    aria-pressed={nilai === opsi.key}
                    className={
                        "rounded-md px-3 py-1 text-xs font-medium transition " +
                        (nilai === opsi.key
                            ? "bg-lime-500 text-onPrimary"
                            : "text-text-secondary hover:text-text-primary")
                    }
                >
                    {opsi.label}
                </button>
            ))}
        </div>
    );
}

function TransaksiTerbaru({ transaksi, adaRekening }) {
    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-baseline justify-between gap-3">
                <h2 className="text-sm font-semibold text-text-primary">
                    Transaksi terbaru
                </h2>
                {adaRekening && (
                    <Link
                        href={route("transactions.index")}
                        className="text-xs font-medium text-lime-500 transition hover:text-lime-400"
                    >
                        Lihat semua →
                    </Link>
                )}
            </div>

            {transaksi.length === 0 ? (
                <p className="mt-3 text-sm leading-relaxed text-text-secondary">
                    Belum ada transaksi tercatat.
                </p>
            ) : (
                <ul className="mt-3 space-y-3">
                    {transaksi.map((t) => (
                        <li key={t.id} className="flex items-start justify-between gap-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-text-primary">
                                    {t.name}
                                </p>
                                <p className="num-tabular truncate text-xs text-text-muted">
                                    {[t.account, t.occurred_on].filter(Boolean).join(" · ")}
                                </p>
                            </div>
                            <span
                                className={
                                    "num-tabular shrink-0 text-sm font-semibold " +
                                    (t.increases_balance
                                        ? "text-state-success"
                                        : "text-text-primary")
                                }
                            >
                                {t.increases_balance ? "+" : "−"}
                                {formatRupiah(Math.abs(t.amount))}
                            </span>
                        </li>
                    ))}
                </ul>
            )}
        </div>
    );
}

function BelumAdaRekening() {
    return (
        <div className="rounded-card border border-border bg-bg-card px-6 py-10 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Mulai dari rekening Anda
            </h2>
            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                Kekayaan bersih, arus kas, dan komposisi aset dihitung dari
                rekening dan transaksi Anda. Tambahkan satu rekening untuk
                mengisi angka-angka di halaman ini.
            </p>
            <div className="mt-6">
                <Link
                    href={route("accounts.index")}
                    className="inline-block rounded-lg bg-lime-500 px-5 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Tambah rekening
                </Link>
            </div>
        </div>
    );
}

function BelumAdaTujuan() {
    return (
        <div className="rounded-card border border-border bg-bg-card px-6 py-10 text-center">
            <svg
                className="mx-auto h-10 w-10 text-text-muted"
                viewBox="0 0 32 32"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                aria-hidden="true"
            >
                <circle cx="16" cy="16" r="12" />
                <circle cx="16" cy="16" r="6.5" />
                <circle cx="16" cy="16" r="1.5" />
            </svg>

            <h2 className="mt-4 text-lg font-semibold text-text-primary">
                Belum ada tujuan finansial
            </h2>
            <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                Tentukan target Anda — dana darurat, DP rumah, atau pensiun —
                lalu Arus menghitung berapa yang perlu disisihkan tiap bulan,
                lengkap dengan imbal hasil dan inflasi.
            </p>
            <div className="mt-6">
                <Link
                    href={route("goals.create")}
                    className="inline-block rounded-lg bg-lime-500 px-5 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Buat tujuan pertama
                </Link>
            </div>
        </div>
    );
}
