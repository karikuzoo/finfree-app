import { todayInJakarta } from "@/utils/timezone";
import { useEffect, useRef, useState } from "react";

/**
 * Pemilih BULAN bertema Arus — pasangan DateInput untuk nilai "YYYY-MM".
 *
 * Menggantikan <input type="month"> bawaan browser dengan alasan yang sama
 * seperti DateInput (DESIGN.md §5.8): panel bawaannya mengikuti bahasa
 * browser ("Jan… Clear, This month"), warnanya di luar tema, dan tak satu pun
 * bisa diubah lewat CSS.
 *
 * Tanpa "Kosongkan": dipakai untuk memilih PERIODE yang ditampilkan
 * (Dashboard, Transaksi), dan periode kosong tidak bermakna.
 */
const BULAN_PENDEK = ["Jan", "Feb", "Mar", "Apr", "Mei", "Jun", "Jul", "Agu", "Sep", "Okt", "Nov", "Des"];
const BULAN = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

const kunci = (tahun, bulan) => `${tahun}-${String(bulan + 1).padStart(2, "0")}`;

const tampilkan = (nilai) => {
    if (!nilai) return "";
    const [t, b] = nilai.split("-").map(Number);

    return `${BULAN[b - 1]} ${t}`;
};

export default function MonthInput({ value, onChange, min, max, id, label = "Pilih bulan", className = "" }) {
    const [buka, setBuka] = useState(false);
    const bungkus = useRef(null);

    const bulanIni = todayInJakarta().slice(0, 7);
    const [tahunLihat, setTahunLihat] = useState(() => Number((value || bulanIni).slice(0, 4)));

    // Dibuka ulang: kembali ke tahun nilai terpilih.
    useEffect(() => {
        if (buka) setTahunLihat(Number((value || bulanIni).slice(0, 4)));
    }, [buka, value, bulanIni]);

    useEffect(() => {
        if (!buka) return;

        const klikLuar = (e) => {
            if (!bungkus.current?.contains(e.target)) setBuka(false);
        };
        const tekanEsc = (e) => {
            if (e.key === "Escape") {
                e.stopPropagation();
                setBuka(false);
            }
        };

        document.addEventListener("mousedown", klikLuar);
        document.addEventListener("keydown", tekanEsc, true);

        return () => {
            document.removeEventListener("mousedown", klikLuar);
            document.removeEventListener("keydown", tekanEsc, true);
        };
    }, [buka]);

    // Perbandingan string aman: "YYYY-MM" berurutan sama secara leksikografis
    // maupun kronologis.
    const diLuar = (k) => (min && k < min) || (max && k > max);
    const bolehMundur = !min || `${tahunLihat - 1}-12` >= min;
    const bolehMaju = !max || `${tahunLihat + 1}-01` <= max;
    const bulanIniBoleh = !diLuar(bulanIni);

    const pilih = (k) => {
        onChange(k);
        setBuka(false);
    };

    return (
        <div ref={bungkus} className={"relative " + className}>
            <button
                id={id}
                type="button"
                onClick={() => setBuka((s) => !s)}
                aria-haspopup="dialog"
                aria-expanded={buka}
                aria-label={`${label}: ${tampilkan(value)}`}
                className={
                    "num-tabular flex items-center gap-2 rounded-lg border bg-bg-base px-3 py-2.5 text-sm font-medium text-text-primary transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                    (buka ? "border-lime-500" : "border-border-strong hover:border-text-muted")
                }
            >
                {tampilkan(value)}
                <svg
                    className="h-4 w-4 shrink-0 text-text-muted"
                    viewBox="0 0 16 16"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.4"
                    strokeLinecap="round"
                    aria-hidden="true"
                >
                    <rect x="2" y="3" width="12" height="11" rx="2" />
                    <path d="M2 6.5h12M5.5 2v2M10.5 2v2" />
                </svg>
            </button>

            {buka && (
                <div
                    role="dialog"
                    aria-label={label}
                    className="absolute right-0 top-full z-30 mt-2 w-[17rem] rounded-xl border border-border-strong bg-bg-card p-3 shadow-xl"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Panah arah="prev" disabled={!bolehMundur} onClick={() => setTahunLihat((t) => t - 1)} />
                        <span className="num-tabular text-sm font-semibold text-text-primary">{tahunLihat}</span>
                        <Panah arah="next" disabled={!bolehMaju} onClick={() => setTahunLihat((t) => t + 1)} />
                    </div>

                    {/* Sel bergaya sama dengan DateInput dan kalender Dashboard. */}
                    <div className="mt-3 grid grid-cols-4 gap-1.5">
                        {BULAN_PENDEK.map((nama, i) => {
                            const k = kunci(tahunLihat, i);
                            const terpilih = k === value;
                            const nonaktif = diLuar(k);

                            return (
                                <button
                                    key={nama}
                                    type="button"
                                    disabled={nonaktif}
                                    onClick={() => pilih(k)}
                                    aria-current={terpilih}
                                    aria-label={`${BULAN[i]} ${tahunLihat}`}
                                    className={
                                        "rounded-lg border py-2 text-sm transition focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 " +
                                        (terpilih
                                            ? "border-lime-500 bg-lime-500 font-bold text-onPrimary"
                                            : nonaktif
                                              ? "cursor-not-allowed border-transparent text-text-disabled"
                                              : k === bulanIni
                                                ? "border-lime-500/60 bg-lime-softBg font-bold text-lime-500"
                                                : "border-transparent font-medium text-text-primary hover:border-border-strong hover:bg-bg-cardAlt")
                                    }
                                >
                                    {nama}
                                </button>
                            );
                        })}
                    </div>

                    <div className="mt-3 flex justify-end border-t border-border pt-2.5">
                        <button
                            type="button"
                            disabled={!bulanIniBoleh}
                            onClick={() => pilih(bulanIni)}
                            className="rounded px-1 text-xs font-semibold text-lime-500 transition hover:text-lime-400 disabled:cursor-not-allowed disabled:text-text-disabled focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
                        >
                            Bulan ini
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function Panah({ arah, onClick, disabled = false }) {
    return (
        <button
            type="button"
            onClick={onClick}
            disabled={disabled}
            aria-label={arah === "prev" ? "Tahun sebelumnya" : "Tahun berikutnya"}
            className="rounded-lg p-1.5 text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary disabled:cursor-not-allowed disabled:text-text-disabled disabled:hover:bg-transparent focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
        >
            <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                <path d={arah === "prev" ? "M10 3 5 8l5 5" : "M6 3l5 5-5 5"} />
            </svg>
        </button>
    );
}
