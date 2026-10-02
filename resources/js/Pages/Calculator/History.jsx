import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { formatDuration, formatRupiah } from "@/utils/format";
import { Head, Link, router } from "@inertiajs/react";

/**
 * Riwayat kalkulasi cepat (PRD FR-45). Props dari
 * CalculationHistoryController::index.
 *
 * Tiap baris dibuka kembali lewat `url` — kalkulatornya sendiri dengan isian
 * tersimpan — dan di sana parameternya bebas diubah. Angka di daftar ini
 * hanya ringkasan saat dihitung; hasil lengkapnya selalu dihitung ulang.
 */
const JENIS = {
    goal: "Tujuan finansial",
    loan: "Pinjaman / KPR",
    investment: "Investasi",
};

/** Kalimat isian dan angka utama per kalkulator. */
function uraian({ calculator, input, summary }) {
    switch (calculator) {
        case "goal":
            return {
                isian: `Target ${formatRupiah(input.target_amount)} dalam ${formatDuration(input.months)}, imbal hasil ${input.annual_return_rate}%`,
                utama: `${formatRupiah(summary.monthly_contribution)} / bulan`,
            };
        case "loan": {
            const bunga =
                input.rate_type === "tiered"
                    ? `bunga berjenjang (${input.tiers?.length ?? 0} jenjang)`
                    : input.rate_type === "floating"
                      ? `bunga mengambang ${input.annual_interest_rate}%`
                      : `bunga ${input.annual_interest_rate}%`;

            const pinjaman =
                input.principal_mode === "price"
                    ? `Rumah ${formatRupiah(input.property_price)}, DP ${
                          input.down_payment_unit === "percent"
                              ? `${input.down_payment ?? 0}%`
                              : formatRupiah(input.down_payment ?? 0)
                      }`
                    : `Pinjaman ${formatRupiah(input.principal)}`;

            return {
                isian: `${pinjaman}, tenor ${formatDuration(input.months)}, ${bunga}`,
                utama: `Angsuran ${formatRupiah(summary.monthly_installment)} / bulan`,
            };
        }
        case "investment":
            return {
                isian: `Setoran ${formatRupiah(input.monthly_contribution)} / bulan selama ${formatDuration(input.months)}, imbal hasil ${input.annual_return_rate}%`,
                utama: `Nilai akhir ${formatRupiah(summary.final_value)}`,
            };
        default:
            return { isian: "", utama: "" };
    }
}

const waktu = (iso) =>
    new Date(iso).toLocaleString("id-ID", {
        timeZone: "Asia/Jakarta",
        day: "numeric",
        month: "short",
        year: "numeric",
        hour: "2-digit",
        minute: "2-digit",
    });

export default function CalculationHistory({ histories, limit }) {
    const hapus = (id) =>
        router.delete(route("calculator.history.destroy", id), { preserveScroll: true });

    const hapusSemua = () => {
        if (window.confirm("Hapus seluruh riwayat kalkulasi?")) {
            router.delete(route("calculator.history.clear"), { preserveScroll: true });
        }
    };

    return (
        <AuthenticatedLayout>
            <Head title="Riwayat kalkulasi" />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Riwayat kalkulasi
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Setiap hitungan di kalkulator tercatat di sini
                            selama Anda masuk — {limit} terakhir. Buka lagi
                            untuk mengubah parameternya. Data cek kesehatan
                            cicilan KPR tidak ikut disimpan.
                        </p>
                    </div>

                    <Link
                        href={route("calculator.index")}
                        className="text-sm font-semibold text-lime-500 hover:underline"
                    >
                        Buka kalkulator →
                    </Link>
                </div>

                {histories.length === 0 ? (
                    <div className="mt-8 rounded-card border border-border bg-bg-card p-8 text-center">
                        <p className="text-sm text-text-secondary">
                            Belum ada hitungan. Hasil dari kalkulator Tujuan,
                            Pinjaman, dan Investasi akan muncul di sini.
                        </p>
                    </div>
                ) : (
                    <>
                        <ul className="mt-8 space-y-3">
                            {histories.map((h) => {
                                const { isian, utama } = uraian(h);

                                return (
                                    <li
                                        key={h.id}
                                        className="flex flex-wrap items-start justify-between gap-3 rounded-card border border-border bg-bg-card p-4"
                                    >
                                        <div className="min-w-0 flex-1">
                                            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                                                {JENIS[h.calculator]} ·{" "}
                                                <time dateTime={h.calculated_at}>
                                                    {waktu(h.calculated_at)}
                                                </time>
                                            </p>
                                            <p className="num-tabular mt-1 text-base font-semibold text-text-primary">
                                                {utama}
                                            </p>
                                            <p className="mt-0.5 text-sm text-text-secondary">
                                                {isian}
                                            </p>
                                        </div>

                                        <div className="flex shrink-0 items-center gap-3">
                                            <Link
                                                href={h.url}
                                                className="rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold text-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-500"
                                            >
                                                Buka lagi
                                            </Link>
                                            <button
                                                type="button"
                                                onClick={() => hapus(h.id)}
                                                aria-label={`Hapus riwayat ${JENIS[h.calculator]} ${waktu(h.calculated_at)}`}
                                                className="text-xs font-medium text-text-muted hover:text-state-danger focus:outline-none focus:ring-2 focus:ring-lime-500"
                                            >
                                                Hapus
                                            </button>
                                        </div>
                                    </li>
                                );
                            })}
                        </ul>

                        <button
                            type="button"
                            onClick={hapusSemua}
                            className="mt-6 text-xs font-medium text-text-muted hover:text-state-danger"
                        >
                            Hapus semua riwayat
                        </button>
                    </>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
