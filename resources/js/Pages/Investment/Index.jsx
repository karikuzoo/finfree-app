import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import CurrencyInput from "@/Components/CurrencyInput";
import DateInput from "@/Components/DateInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Investasi — saham, reksa dana, emas.
 *
 * Daftarnya adalah rekening yang jenisnya bukan bank atau tunai; tidak ada
 * tabel investasi tersendiri (lihat InvestmentController). Yang menonjol di
 * sini adalah "Perbarui nilai", karena aset inilah yang nilainya berubah
 * tanpa pengguna mencatat apa pun.
 */
export default function InvestmentIndex({ investments, totalValue, kinds }) {
    const [menilai, setMenilai] = useState(null);
    const [menambah, setMenambah] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title="Investasi" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Investasi
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Pantau nilai saham, reksa dana, dan emas milik Anda.
                        </p>
                    </div>

                    <PrimaryButton onClick={() => setMenambah(true)}>
                        Tambah investasi
                    </PrimaryButton>
                </div>

                <div className="mt-8 rounded-card border border-border bg-bg-card p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                                Nilai investasi saat ini
                            </p>
                            <p className="num-tabular mt-1.5 text-3xl font-bold text-text-primary">
                                {formatRupiah(totalValue)}
                            </p>
                        </div>
                        <p className="max-w-sm text-xs leading-relaxed text-text-muted">
                            Nilai diisi manual — Arus tidak mengambil harga pasar
                            secara otomatis. Gunakan <strong>transfer</strong> untuk
                            pembelian dan penjualan, dan <strong>perbarui nilai</strong>{" "}
                            saat harganya bergerak.
                        </p>
                    </div>
                </div>

                {investments.length === 0 ? (
                    <Kosong onTambah={() => setMenambah(true)} />
                ) : (
                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {investments.map((aset) => (
                            <KartuInvestasi
                                key={aset.id}
                                aset={aset}
                                onNilai={() => setMenilai(aset)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <FormInvestasi
                key={`tambah-${menambah}`}
                show={menambah}
                kinds={kinds}
                onClose={() => setMenambah(false)}
            />

            {menilai && (
                <FormPenilaian
                    key={menilai.id}
                    aset={menilai}
                    onClose={() => setMenilai(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}

function KartuInvestasi({ aset, onNilai }) {
    const selisih = aset.value - aset.opening_balance;

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate font-semibold text-text-primary">{aset.name}</p>
                    <p className="truncate text-xs text-text-muted">
                        {aset.institution || "—"}
                    </p>
                </div>
                <span className="shrink-0 rounded-full bg-bg-cardAlt px-2.5 py-1 text-xs font-semibold text-text-secondary">
                    {aset.kind_label}
                </span>
            </div>

            <p className="num-tabular mt-4 text-xl font-bold text-text-primary">
                {formatRupiah(aset.value)}
            </p>

            <div className="mt-4 space-y-1.5 border-t border-border pt-3 text-xs">
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Saldo awal</span>
                    <span className="num-tabular text-text-secondary">
                        {formatRupiah(aset.opening_balance)}
                    </span>
                </div>
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Selisih</span>
                    <span
                        className={
                            "num-tabular font-semibold " +
                            (selisih > 0
                                ? "text-state-success"
                                : selisih < 0
                                  ? "text-state-danger"
                                  : "text-text-secondary")
                        }
                    >
                        {selisih > 0 ? "+" : selisih < 0 ? "−" : ""}
                        {formatRupiah(Math.abs(selisih))}
                    </span>
                </div>
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Penilaian terakhir</span>
                    <span className="num-tabular text-text-secondary">
                        {aset.last_valued_on ?? "Belum pernah"}
                    </span>
                </div>
            </div>

            {/*
                Aset yang nilainya tidak pernah diperbarui tetap tampil sebagai
                angka pasti di layar, padahal ia hanya tebakan yang sudah basi.
                Diberi tahu, bukan disembunyikan.
            */}
            {aset.last_valued_on === null && (
                <p className="mt-3 text-xs leading-relaxed text-text-muted">
                    Nilainya masih sama dengan saat dicatat. Perbarui bila
                    harganya sudah bergerak.
                </p>
            )}

            <SecondaryButton onClick={onNilai} className="mt-4 w-full justify-center">
                Perbarui nilai
            </SecondaryButton>
        </div>
    );
}

function Kosong({ onTambah }) {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Belum ada investasi
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Catat saham, reksa dana, atau emas yang Anda pegang beserta
                nilainya saat ini. Semuanya ikut terhitung dalam total aset dan
                komposisi portofolio Anda.
            </p>
            <div className="mt-6">
                <PrimaryButton onClick={onTambah}>Tambah investasi</PrimaryButton>
            </div>
        </div>
    );
}

/**
 * Menambah investasi TANPA meninggalkan halaman ini.
 *
 * Mengirim ke `accounts.store`, endpoint yang sama dengan Rekening & aset —
 * investasi memang rekening, hanya jenisnya yang berbeda (lihat
 * InvestmentController). Yang dibatasi cuma pilihan jenisnya: daftar `kinds`
 * dari backend sudah menyaring bank dan tunai, sehingga form ini tidak bisa
 * dipakai membuat rekening bank secara tidak sengaja.
 *
 * Sebelumnya tombol ini hanyalah tautan ke halaman Rekening & aset. Itu
 * memindahkan pengguna ke tempat lain untuk mengerjakan sesuatu yang judulnya
 * ada di sini — dan begitu sampai, ia masih harus menebak jenis mana yang
 * dihitung sebagai investasi.
 */
function FormInvestasi({ show, kinds, onClose }) {
    const form = useForm({
        name: "",
        kind: kinds[0]?.value ?? "stock",
        institution: "",
        opening_balance: 0,
    });

    const simpan = (e) => {
        e.preventDefault();
        form.post(route("accounts.store"), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <h2 className="text-base font-semibold text-text-primary">
                    Tambah investasi
                </h2>

                <div>
                    <InputLabel htmlFor="inv_name" value="Nama" />
                    <TextInput
                        id="inv_name"
                        className="mt-1.5 block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        maxLength={100}
                        placeholder="Portofolio saham, Emas batangan, ..."
                        autoFocus
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="inv_kind" value="Jenis" />
                    <select
                        id="inv_kind"
                        className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                        value={form.data.kind}
                        onChange={(e) => form.setData("kind", e.target.value)}
                    >
                        {kinds.map((jenis) => (
                            <option key={jenis.value} value={jenis.value}>
                                {jenis.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.kind} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="inv_institution" value="Lembaga (opsional)" />
                    <TextInput
                        id="inv_institution"
                        className="mt-1.5 block w-full"
                        value={form.data.institution ?? ""}
                        onChange={(e) => form.setData("institution", e.target.value)}
                        maxLength={100}
                        placeholder="Sekuritas, Manajer investasi, ..."
                    />
                    <InputError message={form.errors.institution} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="inv_value" value="Nilainya saat ini" />
                    <CurrencyInput
                        id="inv_value"
                        className="mt-1.5"
                        value={form.data.opening_balance}
                        onChange={(v) => form.setData("opening_balance", v)}
                    />
                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                        Nilai pada saat Anda mulai mencatat, bukan harga belinya
                        dulu. Selanjutnya perbarui lewat "Perbarui nilai" saat
                        harganya bergerak.
                    </p>
                    <InputError message={form.errors.opening_balance} className="mt-2" />
                </div>

                <div className="flex justify-end gap-2 pt-1">
                    <SecondaryButton type="button" onClick={onClose}>
                        Batal
                    </SecondaryButton>
                    <PrimaryButton disabled={form.processing}>Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}

/**
 * Yang diminta adalah NILAI TOTAL terkini, bukan selisihnya — orang tahu
 * portofolionya bernilai 19 juta, bukan bahwa ia "naik 1.000.000". Backend
 * yang menghitung selisihnya lalu menyimpannya sebagai transaksi penyesuaian.
 */
function FormPenilaian({ aset, onClose }) {
    const form = useForm({
        value: aset.value,
        occurred_on: todayInJakarta(),
    });

    const simpan = (e) => {
        e.preventDefault();
        form.post(route("accounts.valuation.store", aset.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const selisih = Number(form.data.value || 0) - aset.value;

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <div>
                    <h2 className="text-base font-semibold text-text-primary">
                        Perbarui nilai {aset.name}
                    </h2>
                    <p className="mt-1.5 text-sm text-text-secondary">
                        Tercatat sekarang:{" "}
                        <span className="num-tabular">{formatRupiah(aset.value)}</span>
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="value" value="Nilai totalnya sekarang" />
                    <CurrencyInput
                        id="value"
                        className="mt-1.5"
                        value={form.data.value}
                        onChange={(v) => form.setData("value", v)}
                        autoFocus
                    />
                    {selisih !== 0 && (
                        <p className="mt-1.5 text-xs text-text-muted">
                            Akan dicatat sebagai penyesuaian{" "}
                            <span
                                className={
                                    "num-tabular font-semibold " +
                                    (selisih > 0 ? "text-state-success" : "text-state-danger")
                                }
                            >
                                {selisih > 0 ? "+" : "−"}
                                {formatRupiah(Math.abs(selisih))}
                            </span>
                            .
                        </p>
                    )}
                    <InputError message={form.errors.value} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="occurred_on" value="Tanggal penilaian" />
                    <DateInput
                        id="occurred_on"
                        className="mt-1.5"
                        max={todayInJakarta()}
                        value={form.data.occurred_on}
                        onChange={(v) => form.setData("occurred_on", v)}
                    />
                    <InputError message={form.errors.occurred_on} className="mt-2" />
                </div>

                <div className="flex justify-end gap-2 pt-1">
                    <SecondaryButton type="button" onClick={onClose}>
                        Batal
                    </SecondaryButton>
                    <PrimaryButton disabled={form.processing}>Simpan</PrimaryButton>
                </div>
            </form>
        </Modal>
    );
}
