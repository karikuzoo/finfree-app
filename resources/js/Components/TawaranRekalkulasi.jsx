import InputError from "@/Components/InputError";
import { formatDuration, formatRupiah } from "@/utils/format";
import { useForm } from "@inertiajs/react";
import { useState } from "react";

const tanggalPanjang = (iso) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });

/**
 * FR-36 — tawaran rekalkulasi untuk tujuan yang tertinggal dari rencananya.
 *
 * `tawaran` adalah `recalculation` dari DashboardSummaryService, apa adanya.
 * Seluruh angkanya dihitung di server (GoalRecalculationService); yang
 * dikirim balik saat memilih hanya NAMA pilihannya, dan server menghitung
 * ulang sebelum menyimpan — tawaran di halaman bisa sudah basi.
 *
 * Tertutup secara bawaan. Tujuan yang tertinggal sudah diberi lencana merah;
 * tiga pilihan yang terbuka sendiri di setiap kartu yang tertinggal akan
 * membuat halaman terasa menegur, padahal pengguna mungkin memang berencana
 * mengejarnya bulan depan.
 */
export default function TawaranRekalkulasi({ goalId, tawaran }) {
    const [terbuka, setTerbuka] = useState(false);
    const form = useForm({ option: "" });

    if (!tawaran) {
        return null;
    }

    const pilih = (option) => {
        // transform() terpisah, tidak dirantai — lihat TombolSisihkan.jsx.
        form.transform(() => ({ option }));
        form.post(route("goals.recalculate", goalId), {
            preserveScroll: true,
            onSuccess: () => setTerbuka(false),
        });
    };

    const { contribution, date, target } = tawaran.options;

    const pilihan = [
        {
            kunci: "contribution",
            judul: "Naikkan setoran",
            nilai: `${formatRupiah(contribution.monthly_contribution)} / bulan`,
            keterangan: "Tanggal dan nominal target tetap.",
        },
        date && {
            kunci: "date",
            judul: "Mundurkan tanggal target",
            nilai: tanggalPanjang(date.target_date),
            keterangan: `${formatDuration(date.months_added)} lebih lama, setoran tetap ${formatRupiah(tawaran.planned_monthly_contribution)} / bulan.`,
        },
        target && {
            kunci: "target",
            judul: "Turunkan nominal target",
            nilai: formatRupiah(target.target_amount),
            keterangan: "Tanggal dan setoran rencana tetap.",
        },
    ].filter(Boolean);

    return (
        <div className="mt-3 rounded-lg border border-border bg-bg-cardAlt p-3">
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs leading-relaxed text-text-secondary">
                    Dengan dana sekarang, setoran rencana tidak lagi cukup.
                    Dibutuhkan{" "}
                    <span className="num-tabular font-semibold text-text-primary">
                        {formatRupiah(tawaran.required_monthly_contribution)}
                    </span>{" "}
                    / bulan.
                </p>

                <button
                    type="button"
                    onClick={() => setTerbuka((t) => !t)}
                    aria-expanded={terbuka}
                    className="rounded-md text-xs font-semibold text-lime-500 transition hover:text-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    {terbuka ? "Tutup" : "Hitung ulang rencana"}
                </button>
            </div>

            {terbuka && (
                <ul className="mt-3 space-y-2">
                    {pilihan.map((p) => (
                        <li
                            key={p.kunci}
                            className="flex flex-wrap items-center justify-between gap-3 rounded-lg border border-border bg-bg-card px-3 py-2.5"
                        >
                            <div className="min-w-0">
                                <p className="text-xs text-text-muted">{p.judul}</p>
                                <p className="num-tabular text-sm font-semibold text-text-primary">
                                    {p.nilai}
                                </p>
                                <p className="mt-0.5 text-xs text-text-muted">
                                    {p.keterangan}
                                </p>
                            </div>

                            <button
                                type="button"
                                onClick={() => pilih(p.kunci)}
                                disabled={form.processing}
                                aria-label={`${p.judul}: ${p.nilai}`}
                                className="rounded-lg bg-lime-500 px-3 py-1.5 text-xs font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                            >
                                Pakai ini
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <InputError message={form.errors.option} className="mt-2" />
        </div>
    );
}
