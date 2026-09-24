import Modal from "@/Components/Modal";
import CurrencyInput from "@/Components/CurrencyInput";
import PrimaryButton from "@/Components/PrimaryButton";
import DangerButton from "@/Components/DangerButton";
import SecondaryButton from "@/Components/SecondaryButton";
import InputError from "@/Components/InputError";
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

export default function ActivityCalendar({ calendar, placeholder = false }) {
    // Saat placeholder, `calendar` masih berbentuk array lama (data contoh).
    // Dinormalkan supaya sisa komponen hanya mengenal satu bentuk.
    const data = Array.isArray(calendar)
        ? { month: null, label: null, notes: [] }
        : (calendar ?? { notes: [] });

    // Menyimpan TANGGALNYA saja (string), bukan salinan objek sel.
    //
    // Versi sebelumnya menyimpan objeknya, dan itu membeku: setelah menyimpan
    // catatan atau pengingat, Inertia memuat ulang prop `calendar` dan `sel`
    // dihitung ulang — tetapi dialog masih memegang objek lama, sehingga
    // isinya tidak berubah sampai halaman dimuat ulang manual. Dengan
    // menyimpan tanggalnya lalu mencari selnya tiap render, dialog selalu
    // membaca data terbaru.
    const [tanggalTerpilih, setTanggalTerpilih] = useState(null);

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

            hasil.push({
                tanggal: d,
                tgl,
                luarBulan: false,
                hariMinggu: new Date(tahun, bulanIndex, d).getDay() === 0,
                catatan: catatanPerTanggal[tgl] ?? null,
                adaCatatan: Boolean(catatanPerTanggal[tgl]),
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
    }, [tahun, bulanIndex, catatanPerTanggal, pengingatPerTanggal]);

    // Dicari ulang tiap render dari `sel` yang baru dihitung, sehingga isi
    // dialog ikut segar begitu prop dari server diperbarui.
    const selTerpilih = tanggalTerpilih
        ? (sel.find((s) => s.tgl === tanggalTerpilih) ?? null)
        : null;

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

                        Kalender sengaja global: catatan tanggal dan pengingat
                        menempel pada TANGGAL, bukan pada tujuan mana pun.
                        Menyaringnya per tujuan akan membuat keduanya kehilangan
                        pijakan.
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
                                !placeholder && setTanggalTerpilih(s.tgl)
                            }
                        />
                    ),
                )}
            </div>

            <Keterangan />

            {selTerpilih && (
                <DialogCatatan
                    sel={selTerpilih}
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
    const adaCatatan = Boolean(sel.adaCatatan);
    const pengingat = sel.pengingat ?? [];
    const belumSelesai = pengingat.filter((p) => !p.completed).length;

    const keterangan = [
        `Tanggal ${sel.tanggal}`,
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
            title={sel.catatan?.body || undefined}
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

        </button>
    );
}

function Keterangan() {
    return (
        <div className="mt-4 flex flex-wrap items-center gap-4 border-t border-border pt-3 text-[11px] text-text-muted">
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
            <div className="p-6">
                {/*
                    Tombol tutup di pojok.

                    Dialog ini memuat dua form sekaligus — catatan dan
                    pengingat — masing-masing dengan tombolnya sendiri. Tanpa satu jalan keluar yang jelas, pengguna yang
                    selesai mengisi tidak tahu tombol mana yang menutupnya, dan
                    "Batal" milik form catatan mudah disangka membatalkan
                    seluruh isian.
                */}
                <div className="flex items-start justify-between gap-3">
                    <h3 className="text-base font-semibold text-text-primary">
                        {tanggalPanjang}
                    </h3>

                    <button
                        type="button"
                        onClick={onClose}
                        aria-label="Tutup"
                        className="-mr-1 -mt-1 shrink-0 rounded-lg p-1.5 text-text-muted transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
                    >
                        <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true">
                            <path d="M4 4l8 8M12 4l-8 8" />
                        </svg>
                    </button>
                </div>

                <form onSubmit={simpan}>
                <label
                    htmlFor="catatan"
                    className="mt-4 block text-sm font-medium text-text-secondary"
                >
                    Catatan tanggal ini
                </label>
                <textarea
                    id="catatan"
                    rows={3}
                    autoFocus
                    maxLength={500}
                    value={form.data.body}
                    onChange={(e) => form.setData("body", e.target.value)}
                    placeholder="Gajian, bayar pajak kendaraan, cek tagihan listrik…"
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
                    {/* Menutup dialog, bukan membatalkan catatan saja —
                        diberi nama "Tutup" supaya tidak rancu dengan tombol
                        Batal pada form sunting pengingat di bawahnya. */}
                    <SecondaryButton type="button" onClick={onClose}>
                        Tutup
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
 * Arus, dan tidak mengirim notifikasi ke perangkat.
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

    return (
        <div className="mt-6 border-t border-border pt-5">
            <h4 className="text-sm font-medium text-text-secondary">
                Pengingat
            </h4>
            <p className="mt-0.5 text-xs text-text-muted">
                Muncul di Dashboard saat Anda membuka Arus pada hari itu.
            </p>

            {pengingat.length > 0 && (
                <ul className="mt-3 space-y-1.5">
                    {pengingat.map((p) => (
                        <BarisPengingat key={p.id} pengingat={p} />
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
    // Arah bukaan ditentukan saat panel dibuka, bukan dipatok.
    //
    // Dipatok ke atas, panel terpotong bila tombolnya berada di bagian atas
    // dialog; dipatok ke bawah, terpotong bila di bagian bawah. Diukur dulu,
    // keduanya terhindar.
    const [keAtas, setKeAtas] = useState(true);
    const bungkus = useRef(null);
    const tombol = useRef(null);

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
                ref={tombol}
                type="button"
                onClick={() => {
                    if (!buka) {
                        const r = tombol.current?.getBoundingClientRect();
                        // ~210px: tinggi panel (kepala + max-h-40 + padding).
                        const cukupDiAtas = (r?.top ?? 0) > 210;
                        setKeAtas(cukupDiAtas);
                    }
                    setBuka((s) => !s);
                }}
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
                    className={
                        "absolute right-0 z-30 flex overflow-hidden rounded-xl border border-border-strong bg-bg-card shadow-xl " +
                        (keAtas ? "bottom-full mb-2" : "top-full mt-2")
                    }
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
 * Satu pengingat — bisa ditandai selesai, disunting, dan dihapus.
 *
 * Judul dan jam saja yang bisa diubah, TIDAK tanggalnya: pengingat disunting
 * dari dialog tanggal, dan memindahkannya ke hari lain membuat barisnya lenyap
 * dari dialog yang sedang terbuka.
 */
function BarisPengingat({ pengingat }) {
    const [sunting, setSunting] = useState(false);

    const form = useForm({
        title: pengingat.title,
        remind_time: pengingat.time,
    });

    const simpan = (e) => {
        e.preventDefault();
        form.patch(route("reminders.update", pengingat.id), {
            preserveScroll: true,
            onSuccess: () => setSunting(false),
        });
    };

    const toggle = () =>
        router.patch(
            route("reminders.toggle", pengingat.id),
            {},
            { preserveScroll: true },
        );

    const hapus = () =>
        router.delete(route("reminders.destroy", pengingat.id), {
            preserveScroll: true,
        });

    if (sunting) {
        return (
            <li>
                <form
                    onSubmit={simpan}
                    className="space-y-2 rounded-lg border border-border-strong bg-bg-base p-2.5"
                >
                    <input
                        type="text"
                        maxLength={200}
                        autoFocus
                        value={form.data.title}
                        onChange={(e) => form.setData("title", e.target.value)}
                        aria-label="Judul pengingat"
                        className="block w-full rounded-lg border-border-strong bg-bg-base py-1.5 text-sm text-text-primary focus:border-lime-500 focus:ring-lime-500"
                    />
                    <InputError message={form.errors.title} />

                    <div className="flex items-center justify-between gap-2">
                        <PilihJam
                            value={form.data.remind_time}
                            onChange={(v) => form.setData("remind_time", v)}
                        />

                        <div className="flex gap-2">
                            <SecondaryButton
                                type="button"
                                onClick={() => setSunting(false)}
                            >
                                Batal
                            </SecondaryButton>
                            <PrimaryButton disabled={form.processing}>
                                {form.processing ? "Menyimpan…" : "Simpan"}
                            </PrimaryButton>
                        </div>
                    </div>
                    <InputError message={form.errors.remind_time} />
                </form>
            </li>
        );
    }

    return (
        <li className="flex items-center gap-2.5 rounded-lg border border-border bg-bg-cardAlt px-3 py-2">
            <input
                type="checkbox"
                checked={pengingat.completed}
                onChange={toggle}
                aria-label={`Tandai selesai: ${pengingat.title}`}
                className="h-4 w-4 shrink-0 rounded border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-cardAlt"
            />

            <span className="num-tabular shrink-0 text-xs font-semibold text-state-warning">
                {pengingat.time}
            </span>

            <span
                className={
                    "min-w-0 flex-1 truncate text-sm " +
                    (pengingat.completed
                        ? "text-text-muted line-through"
                        : "text-text-primary")
                }
            >
                {pengingat.title}
            </span>

            <button
                type="button"
                onClick={() => setSunting(true)}
                aria-label={`Ubah pengingat: ${pengingat.title}`}
                className="shrink-0 rounded p-1 text-text-muted transition hover:text-text-primary focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
            >
                <svg className="h-3.5 w-3.5" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                    <path d="M11.5 2.5a1.4 1.4 0 0 1 2 2L7 11l-2.5.5.5-2.5 6.5-6.5Z" />
                </svg>
            </button>

            <button
                type="button"
                onClick={hapus}
                aria-label={`Hapus pengingat: ${pengingat.title}`}
                className="shrink-0 rounded p-1 text-text-muted transition hover:text-state-danger focus:outline-none focus-visible:ring-2 focus-visible:ring-lime-500"
            >
                <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.6" strokeLinecap="round" aria-hidden="true">
                    <path d="M4 4l8 8M12 4l-8 8" />
                </svg>
            </button>
        </li>
    );
}