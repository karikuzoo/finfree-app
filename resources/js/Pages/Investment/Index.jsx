import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import CurrencyInput from "@/Components/CurrencyInput";
import DateInput from "@/Components/DateInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Investasi — saham, reksa dana, emas.
 *
 * Daftarnya adalah rekening yang jenisnya bukan bank atau tunai; tidak ada
 * tabel investasi tersendiri (lihat InvestmentController). Yang menonjol di
 * sini adalah "Perbarui nilai", karena aset inilah yang nilainya berubah
 * tanpa pengguna mencatat apa pun.
 */
export default function InvestmentIndex({ investments, totalValue }) {
    const [menilai, setMenilai] = useState(null);

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

                    <Link
                        href={route("accounts.index")}
                        className="rounded-lg border border-border-strong px-4 py-2.5 text-sm font-semibold text-text-secondary transition hover:border-text-muted hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        Tambah investasi
                    </Link>
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
                    <Kosong />
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

function Kosong() {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Belum ada investasi
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Tambahkan saham, reksa dana, atau emas lewat Rekening &amp; aset —
                pilih jenisnya di sana, dan aset itu otomatis muncul di halaman ini.
            </p>
            <div className="mt-6">
                <Link
                    href={route("accounts.index")}
                    className="inline-block rounded-lg bg-lime-500 px-5 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Buka Rekening &amp; aset
                </Link>
            </div>
        </div>
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
