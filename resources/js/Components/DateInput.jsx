import { useEffect, useMemo, useRef, useState } from "react";

/**
 * Pemilih tanggal bertema Malam.
 *
 * Menggantikan <input type="date"> bawaan browser, yang punya tiga masalah di
 * aplikasi ini: tampilannya selalu mengikuti tema sistem sehingga muncul putih
 * terang di tengah antarmuka gelap, nama harinya mengikuti bahasa browser
 * ("Su Mo Tu…") meski seluruh aplikasi berbahasa Indonesia, dan tak satu pun
 * dari keduanya bisa diubah lewat CSS.
 *
 * Nilai yang dipertukarkan tetap "YYYY-MM-DD" persis seperti input bawaan,
 * jadi seluruh aturan validasi di server tidak berubah.
 *
 * Inisial hari memakai tiga huruf mengikuti ActivityCalendar: dalam Bahasa
 * Indonesia, inisial satu huruf menghasilkan M-S-S-R-K-J-S — tiga kolom "S"
 * yang mustahil dibedakan.
 */
const HARI = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

const BULAN = [
    "Januari", "Februari", "Maret", "April", "Mei", "Juni",
    "Juli", "Agustus", "September", "Oktober", "November", "Desember",
];

/** "2026-09-01" tanpa pergeseran zona waktu. */
const iso = (t, b, tgl) =>
    `${t}-${String(b + 1).padStart(2, "0")}-${String(tgl).padStart(2, "0")}`;

const hariIniIso = () => {
    const d = new Date();

    return iso(d.getFullYear(), d.getMonth(), d.getDate());
};

/**
 * Tanggal ditulis panjang ("1 September 2026") supaya tidak ada keraguan
 * antara format hari-bulan dan bulan-hari.
 */
const tampilkan = (nilai) => {
    if (!nilai) return "";

    const [t, b, tgl] = nilai.split("-").map(Number);

    return `${tgl} ${BULAN[b - 1]} ${t}`;
};

export default function DateInput({
    value,
    onChange,
    min,
    max,
    id,
    placeholder = "Pilih tanggal",
    className = "",
    bolehKosong = true,
}) {
    const [buka, setBuka] = useState(false);
    const bungkus = useRef(null);

    // Bulan yang sedang ditampilkan panel — terpisah dari nilai terpilih,
    // supaya pengguna bisa menjelajah bulan lain tanpa mengubah pilihannya.
    const awal = value || hariIniIso();
    const [lihat, setLihat] = useState(() => {
        const [t, b] = awal.split("-").map(Number);

        return { tahun: t, bulan: b - 1 };
    });

    // Saat panel dibuka ulang, kembalikan tampilan ke bulan nilai terpilih.
    // Tanpa ini, pengguna yang menjelajah ke bulan lain lalu menutup panel
    // akan menemukan bulan asing ketika membukanya lagi.
    useEffect(() => {
        if (!buka) return;

        const [t, b] = (value || hariIniIso()).split("-").map(Number);
        setLihat({ tahun: t, bulan: b - 1 });
    }, [buka, value]);

    useEffect(() => {
        if (!buka) return;

        const klikLuar = (e) => {
            if (!bungkus.current?.contains(e.target)) setBuka(false);
        };
        const tekanEsc = (e) => {
            if (e.key === "Escape") {
                // Jangan sampai ikut menutup dialog yang memuat pemilih ini.
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

    const sel = useMemo(() => {
        const { tahun, bulan } = lihat;
        const jumlahHari = new Date(tahun, bulan + 1, 0).getDate();
        const jumlahHariLalu = new Date(tahun, bulan, 0).getDate();
        const kosongDepan = new Date(tahun, bulan, 1).getDay(); // Minggu = 0

        const hasil = [];

        for (let i = kosongDepan; i > 0; i--) {
            hasil.push({ key: `l-${i}`, tanggal: jumlahHariLalu - i + 1, luar: true });
        }

        for (let d = 1; d <= jumlahHari; d++) {
            const tgl = iso(tahun, bulan, d);

            hasil.push({
                key: tgl,
                tanggal: d,
                tgl,
                luar: false,
                minggu: new Date(tahun, bulan, d).getDay() === 0,
                // Perbandingan string aman karena format YYYY-MM-DD berurutan
                // secara leksikografis sama seperti secara kronologis.
                nonaktif: (min && tgl < min) || (max && tgl > max),
            });
        }

        let d = 1;
        while (hasil.length % 7 !== 0) {
            hasil.push({ key: `d-${d}`, tanggal: d++, luar: true });
        }

        return hasil;
    }, [lihat, min, max]);

    const geser = (arah) =>
        setLihat(({ tahun, bulan }) => {
            const d = new Date(tahun, bulan + arah, 1);

            return { tahun: d.getFullYear(), bulan: d.getMonth() };
        });

    const hariIni = hariIniIso();
    const hariIniBoleh = !((min && hariIni < min) || (max && hariIni > max));

    return (
        <div ref={bungkus} className={"relative " + className}>
            <button
                id={id}
                type="button"
                onClick={() => setBuka((s) => !s)}
                aria-haspopup="dialog"
                aria-expanded={buka}
                className={
                    "flex w-full items-center justify-between gap-2 rounded-lg border px-3 py-2 text-left text-sm transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                    (buka ? "border-lime-500" : "border-border-strong hover:border-text-muted") +
                    " bg-bg-base " +
                    (value ? "text-text-primary" : "text-text-muted")
                }
            >
                {value ? tampilkan(value) : placeholder}

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
                    aria-label="Pilih tanggal"
                    className="absolute left-0 top-full z-30 mt-2 w-[17.5rem] rounded-xl border border-border-strong bg-bg-card p-3 shadow-xl"
                >
                    <div className="flex items-center justify-between gap-2">
                        <Panah arah="prev" onClick={() => geser(-1)} />

                        <span className="text-sm font-semibold text-text-primary">
                            {BULAN[lihat.bulan]} {lihat.tahun}
                        </span>

                        <Panah arah="next" onClick={() => geser(1)} />
                    </div>

                    <div className="mt-3 grid grid-cols-7 gap-px text-center">
                        {HARI.map((nama, i) => (
                            <div
                                key={nama}
                                className={
                                    "pb-1.5 text-[10px] font-semibold uppercase tracking-wide " +
                                    (i === 0 ? "text-state-danger" : "text-text-muted")
                                }
                            >
                                {nama}
                            </div>
                        ))}

                        {sel.map((s) =>
                            s.luar ? (
                                <div
                                    key={s.key}
                                    aria-hidden="true"
                                    className="py-1.5 text-sm text-text-disabled"
                                >
                                    {s.tanggal}
                                </div>
                            ) : (
                                <button
                                    key={s.key}
                                    type="button"
                                    disabled={s.nonaktif}
                                    onClick={() => {
                                        onChange(s.tgl);
                                        setBuka(false);
                                    }}
                                    aria-current={s.tgl === value}
                                    className={
                                        "num-tabular rounded-md py-1.5 text-sm transition " +
                                        (s.tgl === value
                                            ? "bg-lime-500 font-bold text-onPrimary"
                                            : s.nonaktif
                                              ? "cursor-not-allowed text-text-disabled"
                                              : s.tgl === hariIni
                                                ? "font-bold text-lime-500 hover:bg-bg-cardAlt"
                                                : s.minggu
                                                  ? "text-state-danger hover:bg-bg-cardAlt"
                                                  : "text-text-primary hover:bg-bg-cardAlt")
                                    }
                                >
                                    {s.tanggal}
                                </button>
                            ),
                        )}
                    </div>

                    <div className="mt-3 flex items-center justify-between border-t border-border pt-2.5">
                        {bolehKosong ? (
                            <button
                                type="button"
                                onClick={() => {
                                    onChange("");
                                    setBuka(false);
                                }}
                                className="rounded px-1 text-xs font-medium text-text-muted transition hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
                            >
                                Kosongkan
                            </button>
                        ) : (
                            <span />
                        )}

                        <button
                            type="button"
                            disabled={!hariIniBoleh}
                            onClick={() => {
                                onChange(hariIni);
                                setBuka(false);
                            }}
                            className="rounded px-1 text-xs font-semibold text-lime-500 transition hover:text-lime-400 disabled:cursor-not-allowed disabled:text-text-disabled focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
                        >
                            Hari ini
                        </button>
                    </div>
                </div>
            )}
        </div>
    );
}

function Panah({ arah, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={arah === "prev" ? "Bulan sebelumnya" : "Bulan berikutnya"}
            className="rounded-lg p-1.5 text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
        >
            <svg
                className="h-4 w-4"
                viewBox="0 0 16 16"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.6"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <path d={arah === "prev" ? "M10 3 5 8l5 5" : "M6 3l5 5-5 5"} />
            </svg>
        </button>
    );
}
