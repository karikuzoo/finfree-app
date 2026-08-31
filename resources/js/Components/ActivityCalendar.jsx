import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import DangerButton from "@/Components/DangerButton";
import SecondaryButton from "@/Components/SecondaryButton";
import InputError from "@/Components/InputError";
import { formatCompactRupiah, formatRupiah } from "@/utils/format";
import { router, useForm } from "@inertiajs/react";
import { useMemo, useState } from "react";

/**
 * Kalender aktivitas bulanan bergaya kalender ponsel: minggu dimulai hari
 * Minggu, tanggal dari bulan tetangga tetap tampil dalam warna redup, dan
 * penanda aktivitas berupa garis tebal di bawah angka tanggal.
 *
 * Kenapa singkatan hari tiga huruf, bukan satu huruf seperti kalender
 * berbahasa Inggris: dalam Bahasa Indonesia inisial satu huruf menghasilkan
 * M-S-S-R-K-J-S — tiga kolom berhuruf "S" yang mustahil dibedakan. "Min Sen
 * Sel Rab Kam Jum Sab" adalah bentuk yang lazim dipakai kalender Indonesia.
 *
 * Data bulan yang sedang dilihat datang dari prop `calendar` (backend), bukan
 * dihitung di sini. Menggeser bulan memuat ulang prop itu saja lewat
 * `only: ['calendar']`, sehingga agregasi dashboard lain tidak ikut dihitung
 * ulang.
 */

const HARI = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

const iso = (tahun, bulan, tanggal) =>
    `${tahun}-${String(bulan + 1).padStart(2, "0")}-${String(tanggal).padStart(2, "0")}`;

export default function ActivityCalendar({ calendar, placeholder = false }) {
    // Saat placeholder, `calendar` masih berbentuk array lama (data contoh).
    // Dinormalkan supaya sisa komponen hanya mengenal satu bentuk.
    const data = Array.isArray(calendar)
        ? { month: null, label: null, contributions: calendar, notes: [] }
        : (calendar ?? { contributions: [], notes: [] });

    const [tanggalTerpilih, setTanggalTerpilih] = useState(null);

    const setoranPerTanggal = useMemo(
        () =>
            Object.fromEntries(
                (data.contributions ?? []).map((i) => [
                    i.date,
                    { amount: i.amount, entries: i.entries ?? [] },
                ]),
            ),
        [data.contributions],
    );

    const catatanPerTanggal = useMemo(
        () => Object.fromEntries((data.notes ?? []).map((n) => [n.date, n])),
        [data.notes],
    );

    const hariIni = new Date();
    const hariIniStr = iso(
        hariIni.getFullYear(),
        hariIni.getMonth(),
        hariIni.getDate(),
    );

    // Bulan yang ditampilkan. Saat placeholder tidak ada prop dari backend,
    // jadi jatuh ke bulan berjalan.
    const [tahun, bulan] = data.month
        ? data.month.split("-").map(Number)
        : [hariIni.getFullYear(), hariIni.getMonth() + 1];
    const bulanIndex = bulan - 1;

    const sel = useMemo(() => {
        const pertama = new Date(tahun, bulanIndex, 1);
        const jumlahHari = new Date(tahun, bulanIndex + 1, 0).getDate();
        const jumlahHariBulanLalu = new Date(tahun, bulanIndex, 0).getDate();
        const kosongDepan = pertama.getDay(); // Minggu = 0

        const hasil = [];

        // Ekor bulan lalu — ditampilkan redup, seperti kalender ponsel.
        for (let i = kosongDepan; i > 0; i--) {
            hasil.push({
                tanggal: jumlahHariBulanLalu - i + 1,
                luarBulan: true,
                key: `lalu-${i}`,
            });
        }

        for (let d = 1; d <= jumlahHari; d++) {
            const tgl = iso(tahun, bulanIndex, d);
            const setoran = setoranPerTanggal[tgl];
            const entries = setoran?.entries ?? [];

            hasil.push({
                tanggal: d,
                tgl,
                luarBulan: false,
                hariMinggu: new Date(tahun, bulanIndex, d).getDay() === 0,
                nominal: setoran?.amount ?? 0,
                entries,
                catatan: catatanPerTanggal[tgl] ?? null,
                // Catatan bisa datang dari dua tempat: catatan bebas milik
                // tanggal, atau catatan yang menempel pada salah satu setoran.
                // Keduanya sama-sama layak diberi penanda.
                adaCatatan:
                    Boolean(catatanPerTanggal[tgl]) ||
                    entries.some((e) => e.note),
                key: tgl,
            });
        }

        // Awal bulan depan — digenapkan sampai barisnya penuh.
        let d = 1;
        while (hasil.length % 7 !== 0) {
            hasil.push({ tanggal: d++, luarBulan: true, key: `depan-${d}` });
        }

        return hasil;
    }, [tahun, bulanIndex, setoranPerTanggal, catatanPerTanggal]);

    const geserBulan = (arah) => {
        const target = new Date(tahun, bulanIndex + arah, 1);
        const param = `${target.getFullYear()}-${String(target.getMonth() + 1).padStart(2, "0")}`;

        // Kunjungan Inertia biasa, tanpa `only` maupun `preserveState`.
        //
        // Versi pertama memakai partial reload (`only: ['calendar']`) untuk
        // menghemat perhitungan di server. Itu ternyata tidak memperbarui
        // tampilan, dan menghemat beberapa milidetik tidak sepadan dengan
        // fitur yang tidak jalan. Kalender bukan tombol yang ditekan
        // berkali-kali dalam sedetik — kunjungan biasa sudah lebih dari cukup.
        router.get(
            route("dashboard"),
            { bulan: param },
            { preserveScroll: true },
        );
    };

    const label =
        data.label ??
        new Date(tahun, bulanIndex, 1).toLocaleDateString("id-ID", {
            month: "long",
            year: "numeric",
        });

    return (
        <div>
            <div className="flex items-center justify-between gap-2">
                <h2 className="text-base font-semibold text-text-primary">
                    Aktivitas Bulanan
                </h2>

                {placeholder ? (
                    <span className="flex items-center gap-2 text-xs uppercase tracking-wide text-text-muted">
                        {label}
                        <span className="rounded-full bg-bg-cardAlt px-2 py-0.5 text-[10px] normal-case tracking-normal">
                            Contoh
                        </span>
                    </span>
                ) : (
                    <div className="flex items-center gap-1">
                        <TombolGeser
                            arah="prev"
                            onClick={() => geserBulan(-1)}
                        />
                        <span className="min-w-[7.5rem] text-center text-sm font-medium text-text-primary">
                            {label}
                        </span>
                        <TombolGeser
                            arah="next"
                            onClick={() => geserBulan(1)}
                        />
                    </div>
                )}
            </div>

            <div className="mt-4 grid grid-cols-7 gap-px text-center">
                {HARI.map((nama, i) => (
                    <div
                        key={nama}
                        className={
                            "pb-2 text-[11px] font-semibold uppercase tracking-wide " +
                            (i === 0 ? "text-state-danger" : "text-text-muted")
                        }
                    >
                        {nama}
                    </div>
                ))}

                {sel.map((s) =>
                    s.luarBulan ? (
                        <div
                            key={s.key}
                            aria-hidden="true"
                            className="py-2 text-sm text-text-disabled"
                        >
                            {s.tanggal}
                        </div>
                    ) : (
                        <SelTanggal
                            key={s.key}
                            sel={s}
                            hariIni={s.tgl === hariIniStr}
                            nonaktif={placeholder}
                            onClick={() =>
                                !placeholder && setTanggalTerpilih(s)
                            }
                        />
                    ),
                )}
            </div>

            <Keterangan />

            {tanggalTerpilih && (
                <DialogCatatan
                    sel={tanggalTerpilih}
                    onClose={() => setTanggalTerpilih(null)}
                />
            )}
        </div>
    );
}

function TombolGeser({ arah, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-label={arah === "prev" ? "Bulan sebelumnya" : "Bulan berikutnya"}
            className="rounded-lg p-1.5 text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
        >
            <svg
                className="h-4 w-4"
                viewBox="0 0 20 20"
                fill="none"
                stroke="currentColor"
                strokeWidth="2"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                {arah === "prev" ? (
                    <path d="M12 4 L6 10 L12 16" />
                ) : (
                    <path d="M8 4 L14 10 L8 16" />
                )}
            </svg>
        </button>
    );
}

function SelTanggal({ sel, hariIni, nonaktif, onClick }) {
    const adaSetoran = sel.nominal > 0;
    const adaCatatan = Boolean(sel.adaCatatan);

    const keterangan = [
        `Tanggal ${sel.tanggal}`,
        adaSetoran ? `setoran ${formatRupiah(sel.nominal)}` : null,
        adaCatatan ? "ada catatan" : null,
    ]
        .filter(Boolean)
        .join(", ");

    return (
        <button
            type="button"
            onClick={onClick}
            disabled={nonaktif}
            title={sel.catatan?.body || sel.entries?.find((e) => e.note)?.note || undefined}
            aria-label={keterangan}
            className={
                "group flex flex-col items-center gap-1 rounded-lg px-1 py-2 transition focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500 " +
                (nonaktif
                    ? "cursor-default"
                    : "hover:bg-bg-cardAlt cursor-pointer")
            }
        >
            {/*
                Angka tanggal. Hari ini ditandai lingkaran terisi seperti
                kalender ponsel — bukan sekadar warna teks, supaya tetap
                terlihat oleh pengguna yang sulit membedakan warna.
            */}
            <span
                className={
                    "flex h-7 w-7 items-center justify-center rounded-full text-sm tabular-nums transition " +
                    (hariIni
                        ? "bg-lime-500 font-bold text-onPrimary"
                        : sel.hariMinggu
                          ? "font-medium text-state-danger"
                          : "font-medium text-text-primary")
                }
            >
                {sel.tanggal}
            </span>

            {/*
                Garis penanda di bawah angka, seperti di kalender ponsel.
                Tingginya tetap dipesan walau kosong supaya baris tidak
                bergeser naik-turun antar minggu.
            */}
            <span className="flex h-1.5 items-center gap-0.5">
                {adaSetoran && (
                    <span className="block h-1 w-5 rounded-full bg-lime-500" />
                )}
                {adaCatatan && (
                    <span className="block h-1 w-2.5 rounded-full bg-state-info" />
                )}
            </span>

            {adaSetoran ? (
                <span className="text-[10px] font-semibold text-lime-500">
                    {formatCompactRupiah(sel.nominal)}
                </span>
            ) : (
                <span className="text-[10px] text-transparent">.</span>
            )}
        </button>
    );
}

function Keterangan() {
    return (
        <div className="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-3 text-[11px] text-text-muted">
            <span className="flex items-center gap-1.5">
                <span className="block h-1 w-5 rounded-full bg-lime-500" />
                Ada setoran
            </span>
            <span className="flex items-center gap-1.5">
                <span className="block h-1 w-2.5 rounded-full bg-state-info" />
                Ada catatan
            </span>
            <span className="ml-auto">Klik tanggal untuk menulis catatan</span>
        </div>
    );
}

function DialogCatatan({ sel, onClose }) {
    const sudahAda = Boolean(sel.catatan);

    const form = useForm({
        note_date: sel.tgl,
        body: sel.catatan?.body ?? "",
    });

    const simpan = (e) => {
        e.preventDefault();
        form.post(route("calendar-notes.store"), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const hapus = () => {
        router.delete(route("calendar-notes.destroy", sel.catatan.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const tanggalPanjang = new Date(sel.tgl + "T00:00:00").toLocaleDateString(
        "id-ID",
        { weekday: "long", day: "numeric", month: "long", year: "numeric" },
    );

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="p-6">
                <h3 className="text-base font-semibold text-text-primary">
                    {tanggalPanjang}
                </h3>

                {sel.nominal > 0 && (
                    <div className="mt-3 rounded-lg border border-border bg-bg-cardAlt p-3">
                        <p className="text-xs font-medium uppercase tracking-wide text-text-secondary">
                            Setoran tercatat
                        </p>

                        <ul className="mt-2 space-y-2">
                            {sel.entries.map((e, i) => (
                                <li key={i} className="text-sm">
                                    <div className="flex items-baseline justify-between gap-3">
                                        <span className="text-text-secondary">
                                            {e.goal}
                                        </span>
                                        <span className="num-tabular font-semibold text-lime-500">
                                            {formatRupiah(e.amount)}
                                        </span>
                                    </div>
                                    {e.note && (
                                        <p className="mt-0.5 text-xs italic leading-relaxed text-text-muted">
                                            “{e.note}”
                                        </p>
                                    )}
                                </li>
                            ))}
                        </ul>

                        {sel.entries.length > 1 && (
                            <div className="mt-2 flex items-baseline justify-between border-t border-border pt-2 text-sm">
                                <span className="text-text-secondary">
                                    Total
                                </span>
                                <span className="num-tabular font-semibold text-text-primary">
                                    {formatRupiah(sel.nominal)}
                                </span>
                            </div>
                        )}
                    </div>
                )}

                <label
                    htmlFor="catatan"
                    className="mt-4 block text-sm font-medium text-text-secondary"
                >
                    Catatan tanggal ini
                </label>
                <p className="mt-0.5 text-xs text-text-muted">
                    Berdiri sendiri, terpisah dari catatan yang menempel pada
                    setoran di atas.
                </p>
                <textarea
                    id="catatan"
                    rows={3}
                    autoFocus
                    maxLength={500}
                    value={form.data.body}
                    onChange={(e) => form.setData("body", e.target.value)}
                    placeholder="Gajian, bayar pajak kendaraan, naikkan setoran bulan depan…"
                    className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-sm text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500"
                />

                <div className="mt-1.5 flex items-center justify-between">
                    <InputError message={form.errors.body} />
                    <span className="text-xs text-text-muted">
                        {form.data.body.length}/500
                    </span>
                </div>

                <div className="mt-5 flex items-center justify-end gap-3">
                    {sudahAda && (
                        <DangerButton
                            type="button"
                            onClick={hapus}
                            className="mr-auto"
                        >
                            Hapus
                        </DangerButton>
                    )}
                    <SecondaryButton type="button" onClick={onClose}>
                        Batal
                    </SecondaryButton>
                    <PrimaryButton disabled={form.processing}>
                        {form.processing ? "Menyimpan…" : "Simpan"}
                    </PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
