import { PieChart, Pie, Cell, ResponsiveContainer } from "recharts";
import { Link } from "@inertiajs/react";
import { formatCompactRupiah, formatRupiah } from "@/utils/format";

/**
 * Palet yang sama dengan AllocationBreakdownChart — sengaja disalin, bukan
 * diimpor. Kedua chart ini menjawab pertanyaan berbeda ("di instrumen apa
 * uang saya berada" vs "apakah alokasi saya sesuai saran") dan suatu saat
 * bisa berpisah paletnya. Menyatukannya lewat satu berkas bersama menjadikan
 * setiap perubahan warna di satu chart diam-diam mengubah yang lain.
 */
const COLORS = ["#98EDCE", "#83B8F4", "#F1CC80", "#B29BFA", "#A8DAC3", "#F29DA5"];

/**
 * Komposisi aset — di mana saja uang pengguna berada, per jenis rekening.
 *
 * Angkanya datang jadi dari AccountBalanceService::assetComposition(); tidak
 * ada persentase yang dihitung ulang di sini (CLAUDE.md §6.9).
 *
 * Donat, bukan pai penuh: lubang tengahnya dipakai memuat total aset,
 * sehingga angka yang paling dicari tidak perlu dibaca dari legenda.
 */
export default function AssetCompositionChart({ composition = [], totalAssets = 0 }) {
    const baris = composition.filter((b) => b.amount > 0);

    if (baris.length === 0) {
        return (
            <div className="flex h-full flex-col">
                <h2 className="text-base font-semibold text-text-primary">
                    Komposisi aset
                </h2>
                <div className="flex flex-1 flex-col items-center justify-center py-10 text-center">
                    <p className="max-w-xs text-sm leading-relaxed text-text-secondary">
                        Belum ada aset yang tercatat. Tambahkan rekening bank,
                        tunai, atau investasi untuk melihat sebarannya.
                    </p>
                    <Link
                        href={route("accounts.index")}
                        className="mt-4 text-sm font-medium text-lime-500 transition hover:text-lime-400"
                    >
                        Buka Rekening &amp; aset →
                    </Link>
                </div>
            </div>
        );
    }

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-start justify-between gap-3">
                <h2 className="text-base font-semibold text-text-primary">
                    Komposisi aset
                </h2>
                <Link
                    href={route("accounts.index")}
                    aria-label="Buka Rekening & aset"
                    className="shrink-0 rounded-md p-1 text-text-muted transition hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                        <path d="M6 3h7v7M13 3 4 12" />
                    </svg>
                </Link>
            </div>

            <div className="mt-4 flex flex-wrap items-center gap-6">
                <div className="relative h-[168px] w-[168px] shrink-0">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={baris}
                                dataKey="amount"
                                nameKey="label"
                                cx="50%"
                                cy="50%"
                                innerRadius={54}
                                outerRadius={82}
                                paddingAngle={2}
                                stroke="none"
                                isAnimationActive={false}
                            >
                                {baris.map((b, i) => (
                                    <Cell key={b.kind} fill={COLORS[i % COLORS.length]} />
                                ))}
                            </Pie>
                        </PieChart>
                    </ResponsiveContainer>

                    {/*
                        Total diletakkan di lubang donat, bukan di legenda.
                        Ini angka yang paling dicari, dan menaruhnya di tengah
                        membuatnya terbaca tanpa perlu menelusuri daftar.
                        `pointer-events-none` supaya tidak menghalangi chart.
                    */}
                    <div className="pointer-events-none absolute inset-0 flex flex-col items-center justify-center">
                        <span className="text-[11px] text-text-muted">Total aset</span>
                        <span className="num-tabular text-lg font-bold text-text-primary">
                            {formatCompactRupiah(totalAssets)}
                        </span>
                    </div>
                </div>

                <ul className="min-w-[10rem] flex-1 space-y-2">
                    {baris.map((b, i) => (
                        <li key={b.kind} className="flex items-center gap-2.5 text-sm">
                            <span
                                className="block h-2.5 w-2.5 shrink-0 rounded-full"
                                style={{ backgroundColor: COLORS[i % COLORS.length] }}
                                aria-hidden="true"
                            />
                            <span className="min-w-0 flex-1 truncate text-text-secondary">
                                {b.label}
                            </span>
                            <span
                                className="num-tabular shrink-0 font-semibold text-text-primary"
                                title={formatRupiah(b.amount)}
                            >
                                {b.percentage.toFixed(1)}%
                            </span>
                        </li>
                    ))}
                </ul>
            </div>
        </div>
    );
}
