import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import AccountBadge from "@/Components/AccountBadge";
import CurrencyInput from "@/Components/CurrencyInput";
import { InstitutionField } from "@/Components/InstitutionPicker";
import { lembagaSetelahGantiJenis } from "@/utils/institutions";
import DangerButton from "@/Components/DangerButton";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import UnitsInput, { KeteranganSatuan } from "@/Components/UnitsInput";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { formatRupiah } from "@/utils/format";
import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Rekening & aset — "uang saya ada di mana saja".
 *
 * Beda dari Transaksi yang menjawab "ke mana uang saya pergi", dan dari
 * Tujuan yang menjawab "saya mau ke mana". Halaman ini murni daftar tempat.
 *
 * Seluruh saldo datang jadi dari AccountBalanceService lewat
 * AccountController; tidak ada penjumlahan yang diulang di sini
 * (CLAUDE.md §6.9). Saldo menyentuh transaksi keluar MAUPUN transfer masuk —
 * menghitungnya lagi di frontend hampir pasti melewatkan sisi kedua.
 */
export default function AccountIndex({ accounts, totalAssets, composition, kinds, currencies = [] }) {
    const [menyunting, setMenyunting] = useState(null);
    const [menambah, setMenambah] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title="Rekening & aset" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Rekening &amp; aset
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Semua tempat uang Anda berada, dalam satu pandangan.
                        </p>
                    </div>

                    <PrimaryButton onClick={() => setMenambah(true)}>
                        Tambah rekening
                    </PrimaryButton>
                </div>

                <RingkasanAset total={totalAssets} composition={composition} />

                {accounts.length === 0 ? (
                    <Kosong onTambah={() => setMenambah(true)} />
                ) : (
                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {accounts.map((rekening) => (
                            <KartuRekening
                                key={rekening.id}
                                rekening={rekening}
                                onSunting={() => setMenyunting(rekening)}
                            />
                        ))}
                    </div>
                )}
            </div>

            {/*
                `key` berganti setiap kali form dibuka ATAU ditutup, supaya ia
                dipasang ulang dari nol. Tanpa ini useForm menyimpan isian
                terakhir: mengubah saham menjadi emas yang ditolak, lalu
                menutup dan membuka lagi, menampilkan "emas" beserta galatnya
                — bukan data rekening yang sebenarnya.
            */}
            <FormRekening
                key={menambah ? "baru" : (menyunting?.id ?? "tutup")}
                show={menambah || menyunting !== null}
                rekening={menyunting}
                kinds={kinds}
                currencies={currencies}
                onClose={() => {
                    setMenambah(false);
                    setMenyunting(null);
                }}
            />
        </AuthenticatedLayout>
    );
}

function RingkasanAset({ total, composition }) {
    return (
        <div className="mt-8 rounded-card border border-border bg-bg-card p-5">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                Total seluruh aset
            </p>
            <p className="num-tabular mt-1.5 text-3xl font-bold text-text-primary">
                {formatRupiah(total)}
            </p>

            {composition.length > 0 && (
                <div className="mt-5 space-y-2 border-t border-border pt-4">
                    {composition.map((baris) => (
                        <div key={baris.kind} className="flex items-center gap-3">
                            <span className="w-24 shrink-0 text-sm text-text-secondary">
                                {baris.label}
                            </span>
                            <div className="h-1.5 flex-1 overflow-hidden rounded-full bg-bg-cardAlt">
                                <div
                                    className="h-full rounded-full bg-lime-500"
                                    style={{ width: `${baris.percentage}%` }}
                                />
                            </div>
                            <span className="num-tabular w-14 shrink-0 text-right text-xs font-semibold text-text-primary">
                                {baris.percentage.toFixed(1)}%
                            </span>
                        </div>
                    ))}
                </div>
            )}
        </div>
    );
}

/**
 * Berapa dari saldo ini yang sudah punya tujuan, dan berapa yang bebas.
 *
 * Saldo penuh tetap angka utama kartu — uangnya memang masih di rekening
 * ini; dana tujuan hanya MENANDAI, tidak memindahkan. Rincian ini menjelaskan
 * kenapa pengeluaran bisa ditolak padahal saldonya tampak cukup: batasnya
 * adalah "bebas dipakai", bukan saldo.
 *
 * Tidak tampil sama sekali pada rekening yang tidak dipakai tujuan mana pun.
 */
function RincianDanaTujuan({ rekening }) {
    if (rekening.allocated <= 0) {
        return null;
    }

    const persen =
        rekening.balance > 0
            ? Math.min(100, (rekening.allocated / rekening.balance) * 100)
            : 100;

    return (
        <div className="mt-3">
            <div
                className="h-1.5 overflow-hidden rounded-full bg-border"
                role="img"
                aria-label={`${persen.toFixed(0)}% saldo untuk tujuan`}
            >
                <div
                    className="h-full rounded-full bg-lime-500"
                    style={{ width: `${Math.max(1.5, persen)}%` }}
                />
            </div>

            <dl className="mt-2.5 space-y-1 text-xs">
                <div className="flex justify-between gap-2">
                    <dt className="min-w-0 text-text-muted">
                        Untuk tujuan
                        <span className="block truncate text-text-secondary">
                            {rekening.allocated_goals.map((g) => g.name).join(", ")}
                        </span>
                    </dt>
                    <dd className="num-tabular shrink-0 text-text-secondary">
                        {formatRupiah(rekening.allocated)}
                    </dd>
                </div>
                <div className="flex justify-between gap-2">
                    <dt className="text-text-muted">Bebas dipakai</dt>
                    <dd className="num-tabular shrink-0 font-semibold text-text-primary">
                        {formatRupiah(rekening.free)}
                    </dd>
                </div>
            </dl>
        </div>
    );
}

function KartuRekening({ rekening, onSunting }) {
    const [konfirmasiHapus, setKonfirmasiHapus] = useState(false);
    const form = useForm({});

    const hapus = () => {
        form.delete(route("accounts.destroy", rekening.id), {
            preserveScroll: true,
            onSuccess: () => setKonfirmasiHapus(false),
        });
    };

    return (
        <div className="rounded-card border border-border bg-bg-card p-5 transition hover:border-border-strong">
            {/*
                Bagian atas kartu adalah tautan ke detail (mutasi dan rincian
                dana tujuan). Tombol Ubah/Hapus sengaja di luar tautan supaya
                tidak ada tombol di dalam tautan.
            */}
            <Link
                href={route("accounts.show", rekening.id)}
                aria-label={`Lihat detail ${rekening.name}`}
                className="group -m-2 block rounded-lg p-2 focus:outline-none focus:ring-2 focus:ring-lime-500"
            >
                <div className="flex items-start justify-between gap-3">
                    <div className="flex min-w-0 items-center gap-3">
                        <AccountBadge rekening={rekening} />
                        <div className="min-w-0">
                            <p className="truncate font-semibold text-text-primary group-hover:text-lime-500">
                                {rekening.name}
                            </p>
                            <p className="truncate text-xs text-text-muted">
                                {rekening.institution || "—"}
                            </p>
                        </div>
                    </div>
                    <span className="shrink-0 rounded-full bg-bg-cardAlt px-2.5 py-1 text-xs font-semibold text-text-secondary">
                        {rekening.kind_label}
                    </span>
                </div>

                <div className="mt-4 flex items-baseline justify-between gap-2">
                    <p className="num-tabular text-xl font-bold text-text-primary">
                        {formatRupiah(rekening.balance)}
                    </p>
                    <span className="shrink-0 text-xs font-semibold text-text-muted group-hover:text-lime-500">
                        Detail →
                    </span>
                </div>
            </Link>

            <KeteranganSatuan rekening={rekening} nilai={rekening.balance} />
            <RincianDanaTujuan rekening={rekening} />

            <div className="mt-4 space-y-1.5 border-t border-border pt-3 text-xs">
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Saldo awal</span>
                    <span className="num-tabular text-text-secondary">
                        {formatRupiah(rekening.opening_balance)}
                    </span>
                </div>
                {rekening.needs_valuation && (
                    <p className="leading-relaxed text-text-muted">
                        Nilainya tidak berubah sendiri — catat penyesuaian nilai
                        saat {rekening.kind === "valas" ? "kursnya" : "harganya"} bergerak.
                    </p>
                )}
            </div>

            <div className="mt-4 flex gap-2">
                <SecondaryButton onClick={onSunting} className="flex-1 justify-center">
                    Ubah
                </SecondaryButton>
                <SecondaryButton
                    onClick={() => setKonfirmasiHapus(true)}
                    className="justify-center"
                >
                    Hapus
                </SecondaryButton>
            </div>

            {form.errors.account && (
                <InputError message={form.errors.account} className="mt-3" />
            )}

            <Modal show={konfirmasiHapus} onClose={() => setKonfirmasiHapus(false)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-base font-semibold text-text-primary">
                        Hapus {rekening.name}?
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Rekening yang masih punya riwayat transaksi tidak bisa
                        dihapus — riwayatnya harus dibereskan lebih dulu.
                    </p>
                    <div className="mt-5 flex justify-end gap-2">
                        <SecondaryButton onClick={() => setKonfirmasiHapus(false)}>
                            Batal
                        </SecondaryButton>
                        <DangerButton onClick={hapus} disabled={form.processing}>
                            Hapus
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </div>
    );
}

function Kosong({ onTambah }) {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Belum ada rekening
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Tambahkan rekening bank, uang tunai, atau aset investasi Anda.
                Isi saldo pada saat Anda mulai mencatat — bukan sejak rekening
                itu dibuka.
            </p>
            <div className="mt-6">
                <PrimaryButton onClick={onTambah}>Tambah rekening</PrimaryButton>
            </div>
        </div>
    );
}

/**
 * Satu form untuk menambah dan mengubah — bidangnya identik.
 *
 * `key` pada Modal memaksa React membuat ulang formnya setiap kali rekening
 * yang disunting berganti. Tanpa itu, nilai rekening sebelumnya tertinggal di
 * state saat pengguna menutup lalu membuka kartu lain.
 */
export function FormRekening({ show, rekening, kinds, currencies = [], onClose }) {
    const menyunting = rekening !== null;

    const form = useForm({
        name: rekening?.name ?? "",
        kind: rekening?.kind ?? "bank",
        currency: rekening?.currency ?? "",
        institution: rekening?.institution ?? "",
        opening_balance: rekening?.opening_balance ?? 0,
        units: rekening?.units ?? "",
    });

    const valas = form.data.kind === "valas";

    const simpan = (e) => {
        e.preventDefault();

        const opsi = { preserveScroll: true, onSuccess: onClose };

        if (menyunting) {
            form.patch(route("accounts.update", rekening.id), opsi);
        } else {
            form.post(route("accounts.store"), opsi);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <h2 className="text-base font-semibold text-text-primary">
                    {menyunting ? `Ubah ${rekening.name}` : "Tambah rekening"}
                </h2>

                <div>
                    <InputLabel htmlFor="name" value="Nama rekening" />
                    <TextInput
                        id="name"
                        className="mt-1.5 block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        maxLength={100}
                        autoFocus
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="kind" value="Jenis" />
                    <select
                        id="kind"
                        className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500 disabled:cursor-not-allowed disabled:opacity-60"
                        value={form.data.kind}
                        onChange={(e) =>
                            form.setData((data) => ({
                                ...data,
                                kind: e.target.value,
                                institution: lembagaSetelahGantiJenis(data.institution, data.kind, e.target.value),
                            }))
                        }
                        disabled={Boolean(rekening?.kind_locked)}
                        aria-describedby={rekening?.kind_locked ? "kind-terkunci" : undefined}
                    >
                        {kinds.map((jenis) => (
                            <option key={jenis.value} value={jenis.value}>
                                {jenis.label}
                            </option>
                        ))}
                    </select>
                    {rekening?.kind_locked ? (
                        <p id="kind-terkunci" className="mt-1.5 text-xs leading-relaxed text-text-muted">
                            Jenis tidak bisa diubah karena rekening ini sudah
                            punya riwayat transaksi. Untuk memindahkan asetnya,
                            buat rekening baru lalu catat transfer.
                        </p>
                    ) : (
                        <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                            Hanya bank, tunai, dan valas yang boleh menyimpan
                            dana tujuan — nilai saham dan emas bergerak sendiri,
                            sehingga tujuan yang dananya di sana bisa meleset
                            diam-diam.
                        </p>
                    )}
                    <InputError message={form.errors.kind} className="mt-2" />
                </div>

                {valas && (
                    <div>
                        <InputLabel htmlFor="currency" value="Mata uang" />
                        <select
                            id="currency"
                            className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500 disabled:cursor-not-allowed disabled:opacity-60"
                            value={form.data.currency}
                            onChange={(e) => form.setData("currency", e.target.value)}
                            disabled={Boolean(rekening?.kind_locked)}
                        >
                            <option value="">Pilih mata uang…</option>
                            {currencies.map((m) => (
                                <option key={m.code} value={m.code}>
                                    {m.code} — {m.label}
                                </option>
                            ))}
                        </select>
                        <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                            {rekening?.kind_locked
                                ? "Mata uang tidak bisa diubah karena rekening ini sudah punya riwayat transaksi."
                                : "Semua angka Arus tetap dalam rupiah. Saat kurs bergerak, perbarui nilainya dari halaman detail rekening."}
                        </p>
                        <InputError message={form.errors.currency} className="mt-2" />
                    </div>
                )}

                <InstitutionField
                    kind={form.data.kind}
                    value={form.data.institution}
                    error={form.errors.institution}
                    onChange={(nama) =>
                        form.setData((data) => ({
                            ...data,
                            institution: nama,
                            // Nama kosong diisi nama lembaganya — "BCA" lebih
                            // baik daripada rekening tanpa nama, dan tetap
                            // bisa diubah.
                            name: data.name.trim() === "" ? nama : data.name,
                        }))
                    }
                />

                <div>
                    <InputLabel
                        htmlFor="opening_balance"
                        value={valas ? "Nilai dalam rupiah saat mulai mencatat" : "Saldo saat mulai mencatat"}
                    />
                    <CurrencyInput
                        id="opening_balance"
                        className="mt-1.5"
                        value={form.data.opening_balance}
                        onChange={(v) => form.setData("opening_balance", v)}
                    />
                    <p className="mt-1.5 text-xs text-text-muted">
                        {valas
                            ? "Jumlah valas dikali kurs hari itu. Isi 0 bila Anda mulai dari kosong."
                            : "Bukan saldo saat rekening dibuka. Isi 0 bila Anda mulai dari kosong."}
                    </p>
                    <InputError message={form.errors.opening_balance} className="mt-2" />
                </div>

                {/* key: ganti jenis atau mata uang berarti satuan lain (gram → lot, USD → SGD). */}
                <UnitsInput
                    key={`${form.data.kind}-${form.data.currency}`}
                    unit={valas ? form.data.currency : kinds.find((k) => k.value === form.data.kind)?.unit}
                    value={form.data.units}
                    onChange={(v) => form.setData("units", v)}
                    error={form.errors.units}
                />

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
