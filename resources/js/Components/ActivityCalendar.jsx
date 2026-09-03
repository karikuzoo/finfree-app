import Modal from "@/Components/Modal";
import CurrencyInput from "@/Components/CurrencyInput";
import PrimaryButton from "@/Components/PrimaryButton";
import DangerButton from "@/Components/DangerButton";
import SecondaryButton from "@/Components/SecondaryButton";
import InputError from "@/Components/InputError";
import { formatCompactRupiah, formatRupiah } from "@/utils/format";
import { nowInJakartaParts } from "@/utils/timezone";
import { router, useForm } from "@inertiajs/react";
import { useEffect, useMemo, useRef, useState } from "react";

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

export default function ActivityCalendar({ calendar, goals = [], placeholder = false }) {
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

    // Berbeda dari catatan yang satu per tanggal, satu tanggal boleh punya
    // banyak pengingat — jadi dikelompokkan, bukan dipetakan satu-satu.
    const pengingatPerTanggal = useMemo(() => {
        const peta = {};

        for (const p of data.reminders ?? []) {
            (peta[p.date] ??= []).push(p);
        }

        return peta;
    }, [data.reminders]);

    const { tahun: tahunIni, bulan: bulanIniIdx, tanggal: tanggalIni } = nowInJakartaParts();
    const hariIniStr = iso(tahunIni, bulanIniIdx, tanggalIni);

    // Bulan yang ditampilkan. Saat placeholder tidak ada prop dari backend,
    // jadi jatuh ke bulan berjalan.
    const [tahun, bulan] = data.month
        ? data.month.split("-").map(Number)
        : [tahunIni, bulanIniIdx + 1];
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
                pengingat: pengingatPerTanggal[tgl] ?? [],
                key: tgl,
            });
        }

        // Awal bulan depan — digenapkan sampai barisnya penuh.
        let d = 1;
        while (hasil.length % 7 !== 0) {
            hasil.push({ tanggal: d++, luarBulan: true, key: `depan-${d}` });
        }

        return hasil;
    }, [tahun, bulanIndex, setoranPerTanggal, catatanPerTanggal, pengingatPerTanggal]);

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
            <div className="flex items-start justify-between gap-2">
                <div>
                    <h2 className="text-base font-semibold text-text-primary">
                        Aktivitas Bulanan
                    </h2>
                    {/*
                        Keterangan cakupan, bukan hiasan.

                        Kartu-kartu lain di Dashboard (target utama, alokasi
                        instrumen, grafik pertumbuhan) mengikuti tujuan yang
                        dipilih; kalender ini TIDAK. Tanpa keterangan, bedanya
                        terbaca sebagai ketidakkonsistenan.

                        Kalender sengaja global karena isinya bercampur: setoran
                        memang milik tujuan, tetapi catatan tanggal dan pengingat
                        menempel pada TANGGAL, bukan pada tujuan mana pun.
                        Menyaringnya per tujuan akan membuat dua dari tiga isinya
                        kehilangan pijakan. Hitungan hari beruntun juga global,
                        dan akan bertentangan dengan kalender yang tersaring.
                    */}
                    {!placeholder && (
                        <p className="mt-0.5 text-xs text-text-muted">
                            Seluruh tujuan
                        </p>
                    )}
                </div>

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
                    goals={goals}
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
    const pengingat = sel.pengingat ?? [];
    const belumSelesai = pengingat.filter((p) => !p.completed).length;

    const keterangan = [
        `Tanggal ${sel.tanggal}`,
        adaSetoran ? `setoran ${formatRupiah(sel.nominal)}` : null,
        adaCatatan ? "ada catatan" : null,
        pengingat.length ? `${pengingat.length} pengingat` : null,
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
                {/*
                    Pengingat yang SUDAH selesai tetap diberi penanda, hanya
                    lebih redup — menghilangkannya membuat kalender bulan lalu
                    tampak kosong padahal ada yang dikerjakan.
                */}
                {pengingat.length > 0 && (
                    <span
                        className={
                            "block h-1 w-2.5 rounded-full " +
                            (belumSelesai > 0
                                ? "bg-state-warning"
                                : "bg-text-disabled")
                        }
                    />
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
            <span className="flex items-center gap-1.5">
                <span className="block h-1 w-2.5 rounded-full bg-state-warning" />
                Ada pengingat
            </span>
            <span className="ml-auto">Klik tanggal untuk catatan &amp; pengingat</span>
        </div>
    );
}

function DialogCatatan({ sel, goals = [], onClose }) {
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
            <div className="p-6">
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

                <SeksiCatatSetoran tgl={sel.tgl} goals={goals} />

                <form onSubmit={simpan}>
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

                <SeksiPengingat tgl={sel.tgl} pengingat={sel.pengingat ?? []} />
            </div>
        </Modal>
    );
}

/**
 * Pengingat pada satu tanggal — daftar yang sudah ada, plus form menambah.
 *
 * Berdiri sebagai saudara dari form catatan, bukan anaknya: HTML melarang
 * form bersarang, dan menempatkannya di dalam akan membuat tombol Enter di
 * kolom judul justru menyimpan catatan.
 *
 * Pengingat ini murni di dalam aplikasi — ia tampil saat pengguna membuka
 * FinGoal, dan tidak mengirim notifikasi ke perangkat.
 */
function SeksiPengingat({ tgl, pengingat }) {
    const form = useForm({
        title: "",
        remind_date: tgl,
        remind_time: "09:00",
    });

    const tambah = (e) => {
        e.preventDefault();
        form.post(route("reminders.store"), {
            preserveScroll: true,
            onSuccess: () => form.reset("title"),
        });
    };

    const toggle = (id) =>
        router.patch(route("reminders.toggle", id), {}, { preserveScroll: true });

    const hapus = (id) =>
        router.delete(route("reminders.destroy", id), { preserveScroll: true });

    return (
        <div className="mt-6 border-t border-border pt-5">
            <h4 className="text-sm font-medium text-text-secondary">
                Pengingat
            </h4>
            <p className="mt-0.5 text-xs text-text-muted">
                Muncul di Dashboard saat Anda membuka FinGoal pada hari itu.
            </p>

            {pengingat.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {pengingat.map((p) => (
                        <li
                            key={p.id}
                            className="flex items-center gap-2.5 rounded-lg border border-border bg-bg-cardAlt px-3 py-2"
                        >
                            <input
                                type="checkbox"
                                checked={p.completed}
                                onChange={() => toggle(p.id)}
                                aria-label={`Tandai selesai: ${p.title}`}
                                className="h-4 w-4 shrink-0 rounded border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-cardAlt"
                            />

                            <span className="num-tabular shrink-0 text-xs font-semibold text-state-warning">
                                {p.time}
                            </span>

                            <span
                                className={
                                    "min-w-0 flex-1 truncate text-sm " +
                                    (p.completed
                                        ? "text-text-muted line-through"
                                        : "text-text-primary")
                                }
                            >
                                {p.title}
                            </span>

                            <button
                                type="button"
                                onClick={() => hapus(p.id)}
                                aria-label={`Hapus pengingat: ${p.title}`}
                                className="shrink-0 rounded p-1 text-text-muted transition hover:text-state-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
                            >
                                <svg
                                    className="h-4 w-4"
                                    viewBox="0 0 16 16"
                                    fill="none"
                                    stroke="currentColor"
                                    strokeWidth="1.6"
                                    strokeLinecap="round"
                                    aria-hidden="true"
                                >
                                    <path d="M4 4l8 8M12 4l-8 8" />
                                </svg>
                            </button>
                        </li>
                    ))}
                </ul>
            )}

            <form onSubmit={tambah} className="mt-3 flex flex-wrap items-start gap-2">
                <div className="min-w-0 flex-1">
                    <input
                        type="text"
                        maxLength={200}
                        value={form.data.title}
                        onChange={(e) => form.setData("title", e.target.value)}
                        placeholder="Setor rutin Dana Darurat"
                        aria-label="Judul pengingat"
                        className="block w-full rounded-lg border-border-strong bg-bg-base text-sm text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500"
                    />
                    <InputError message={form.errors.title} className="mt-1" />
                </div>

                <div className="shrink-0">
                    <PilihJam
                        value={form.data.remind_time}
                        onChange={(v) => form.setData("remind_time", v)}
                    />
                    <InputError message={form.errors.remind_time} className="mt-1" />
                </div>

                <SecondaryButton
                    type="submit"
                    disabled={form.processing || form.data.title.trim() === ""}
                >
                    Tambah
                </SecondaryButton>
            </form>
        </div>
    );
}


const JAM = Array.from({ length: 24 }, (_, i) => String(i).padStart(2, "0"));

// Kelipatan lima menit. Enam puluh pilihan membuat kolomnya melelahkan digulir,
// sementara pengingat hampir selalu disetel pada menit bulat. Bila suatu saat
// perlu setiap menit, ubah panjangnya jadi 60 dan pengalinya jadi 1.
const MENIT = Array.from({ length: 12 }, (_, i) => String(i * 5).padStart(2, "0"));

/**
 * Pemilih jam ringkas: satu tombol yang membuka panel dua kolom.
 *
 * Sengaja BUKAN <input type="time">: input bawaan menampilkan format mengikuti
 * bahasa BROWSER, bukan bahasa halaman — pengguna dengan browser berbahasa
 * Inggris melihat "09:00 AM" meski seluruh aplikasi berbahasa Indonesia, dan
 * Chromium mengabaikan lang="id" untuk hal itu.
 *
 * Sengaja juga BUKAN dua <select>: tinggi dropdown bawaan ditentukan browser
 * dan tidak bisa dibatasi lewat CSS, sehingga 24 pilihan jam membuka panel
 * setinggi hampir seluruh dialog.
 *
 * Panel ini membuka ke ATAS karena tempatnya di dasar dialog — dibuka ke bawah,
 * ia akan terpotong tepi layar.
 *
 * Nilai yang dipertukarkan tetap "HH:MM" 24 jam, jadi validasi di server tidak
 * berubah.
 */
function PilihJam({ value, onChange }) {
    const [buka, setBuka] = useState(false);
    const bungkus = useRef(null);

    // Nilai kosong TIDAK boleh diandalkan diselamatkan nilai bawaan
    // destructuring: "".split(":") menghasilkan [""], dan string kosong itu
    // dianggap "ada" sehingga bawaannya justru tidak dipakai.
    const [jamMentah, menitMentah] = (value || "").split(":");
    const jam = jamMentah || "09";
    const menit = menitMentah || "00";

    // Menit di luar kelipatan lima bisa datang dari data lama. Ditampilkan apa
    // adanya supaya nilainya tidak diam-diam bergeser saat panel dibuka.
    const daftarMenit = MENIT.includes(menit) ? MENIT : [...MENIT, menit].sort();

    useEffect(() => {
        if (!buka) return;

        const klikLuar = (e) => {
            if (!bungkus.current?.contains(e.target)) setBuka(false);
        };
        const tekanEsc = (e) => {
            if (e.key === "Escape") {
                e.stopPropagation(); // Jangan sampai ikut menutup dialog tanggal.
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

    return (
        <div ref={bungkus} className="relative">
            <button
                type="button"
                onClick={() => setBuka((s) => !s)}
                aria-haspopup="dialog"
                aria-expanded={buka}
                aria-label={`Jam pengingat: ${jam}:${menit}`}
                className={
                    "num-tabular flex w-[5.5rem] items-center justify-between gap-1 rounded-lg border px-2.5 py-2 text-sm transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                    (buka
                        ? "border-lime-500 bg-bg-base text-text-primary"
                        : "border-border-strong bg-bg-base text-text-primary hover:border-text-muted")
                }
            >
                {jam}:{menit}
                <svg
                    className="h-3.5 w-3.5 shrink-0 text-text-muted"
                    viewBox="0 0 12 12"
                    fill="none"
                    stroke="currentColor"
                    strokeWidth="1.5"
                    strokeLinecap="round"
                    strokeLinejoin="round"
                    aria-hidden="true"
                >
                    <path d="M3 7.5 6 4.5l3 3" />
                </svg>
            </button>

            {buka && (
                <div
                    role="dialog"
                    aria-label="Pilih jam"
                    className="absolute bottom-full right-0 z-20 mb-2 flex overflow-hidden rounded-xl border border-border-strong bg-bg-card shadow-xl"
                >
                    <KolomWaktu
                        judul="Jam"
                        pilihan={JAM}
                        terpilih={jam}
                        onPilih={(v) => onChange(`${v}:${menit}`)}
                    />
                    <div className="w-px bg-border" />
                    <KolomWaktu
                        judul="Menit"
                        pilihan={daftarMenit}
                        terpilih={menit}
                        onPilih={(v) => {
                            onChange(`${jam}:${v}`);
                            setBuka(false); // Menit adalah pilihan terakhir.
                        }}
                    />
                </div>
            )}
        </div>
    );
}

function KolomWaktu({ judul, pilihan, terpilih, onPilih }) {
    const aktif = useRef(null);

    // Gulirkan ke nilai yang sedang terpilih saat panel dibuka — tanpa ini,
    // pukul 21.00 mengharuskan pengguna menggulir dari 00 setiap kali.
    useEffect(() => {
        aktif.current?.scrollIntoView({ block: "center" });
    }, []);

    return (
        <div className="w-16">
            <p className="border-b border-border px-2 py-1.5 text-center text-[10px] font-semibold uppercase tracking-wide text-text-muted">
                {judul}
            </p>

            <div className="max-h-40 overflow-y-auto py-1">
                {pilihan.map((v) => {
                    const dipilih = v === terpilih;

                    return (
                        <button
                            key={v}
                            ref={dipilih ? aktif : null}
                            type="button"
                            onClick={() => onPilih(v)}
                            aria-current={dipilih}
                            className={
                                "num-tabular block w-full px-2 py-1.5 text-center text-sm transition " +
                                (dipilih
                                    ? "bg-lime-500 font-bold text-onPrimary"
                                    : "text-text-secondary hover:bg-bg-cardAlt hover:text-text-primary")
                            }
                        >
                            {v}
                        </button>
                    );
                })}
            </div>
        </div>
    );
}

/**
 * Catat setoran pada tanggal yang sedang dibuka.
 *
 * Inilah cara pengguna memilih tanggal setoran (PRD FR-32) tanpa perlu mengetik
 * tanggal sama sekali — tanggalnya sudah ditentukan oleh sel kalender yang
 * diklik. Form ringkas di Dashboard hanya bisa mencatat untuk hari berjalan;
 * lewat kalender, setoran yang baru sempat dicatat beberapa hari kemudian tetap
 * jatuh di tanggal yang benar.
 *
 * Tanggal MASA DEPAN tidak menampilkan form ini: uang yang belum disetor bukan
 * setoran, dan server menolaknya lewat aturan `before_or_equal:today`. Yang
 * ditampilkan sebagai gantinya adalah penjelasan singkat — bukan form yang
 * dipastikan gagal saat ditekan.
 */
function SeksiCatatSetoran({ tgl, goals }) {
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const masaDepan = tgl > iso(tahun, bulan, tanggal);

    const form = useForm({
        amount: "",
        contributed_on: tgl,
        note: "",
        financial_goal_id: goals[0]?.id ?? "",
    });

    if (goals.length === 0) {
        return null;
    }

    if (masaDepan) {
        return (
            <p className="mt-4 rounded-lg border border-border bg-bg-cardAlt px-3 py-2.5 text-xs leading-relaxed text-text-secondary">
                Setoran hanya bisa dicatat untuk tanggal yang sudah lewat atau
                hari ini. Untuk merencanakan setoran di tanggal ini, buat
                pengingat di bawah.
            </p>
        );
    }

    const simpan = (e) => {
        e.preventDefault();

        form.post(route("goals.contributions.store", form.data.financial_goal_id), {
            preserveScroll: true,
            onSuccess: () => form.reset("amount", "note"),
        });
    };

    return (
        <form onSubmit={simpan} className="mt-4 rounded-lg border border-border bg-bg-cardAlt p-3">
            <p className="text-xs font-medium uppercase tracking-wide text-text-secondary">
                Catat setoran di tanggal ini
            </p>

            <div className="mt-2.5 space-y-2.5">
                {/*
                    Pemilih tujuan hanya muncul bila tujuannya lebih dari satu.
                    Menampilkan menu berisi satu pilihan hanya menambah langkah
                    tanpa memberi pilihan apa pun.
                */}
                {goals.length > 1 && (
                    <div>
                        <select
                            value={form.data.financial_goal_id}
                            onChange={(e) => form.setData("financial_goal_id", e.target.value)}
                            aria-label="Tujuan"
                            className="block w-full rounded-lg border-border-strong bg-bg-base py-2 text-sm text-text-primary focus:border-lime-500 focus:ring-lime-500"
                        >
                            {goals.map((g) => (
                                <option key={g.id} value={g.id}>
                                    {g.name}
                                </option>
                            ))}
                        </select>
                    </div>
                )}

                <div className="flex flex-wrap items-start gap-2">
                    <div className="w-36">
                        <CurrencyInput
                            className="py-2 text-sm"
                            placeholder="50.000"
                            value={form.data.amount}
                            onChange={(v) => form.setData("amount", v)}
                        />
                        <InputError message={form.errors.amount} className="mt-1" />
                    </div>

                    <input
                        type="text"
                        maxLength={500}
                        value={form.data.note}
                        onChange={(e) => form.setData("note", e.target.value)}
                        placeholder="Catatan (opsional)"
                        aria-label="Catatan setoran"
                        className="min-w-0 flex-1 rounded-lg border-border-strong bg-bg-base py-2 text-sm text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500"
                    />

                    <SecondaryButton
                        type="submit"
                        disabled={form.processing || form.data.amount === ""}
                    >
                        Simpan
                    </SecondaryButton>
                </div>

                <InputError message={form.errors.contributed_on} />
            </div>

            {goals.length === 1 && (
                <p className="mt-2 text-[11px] text-text-muted">
                    Masuk ke tujuan {goals[0].name}.
                </p>
            )}
        </form>
    );
}
