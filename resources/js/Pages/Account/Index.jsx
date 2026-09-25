import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import CurrencyInput from "@/Components/CurrencyInput";
import DangerButton from "@/Components/DangerButton";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { formatRupiah } from "@/utils/format";
import { Head, useForm } from "@inertiajs/react";
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
export default function AccountIndex({ accounts, totalAssets, composition, kinds }) {
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

            <FormRekening
                show={menambah || menyunting !== null}
                rekening={menyunting}
                kinds={kinds}
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
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <div className="min-w-0">
                    <p className="truncate font-semibold text-text-primary">
                        {rekening.name}
                    </p>
                    <p className="truncate text-xs text-text-muted">
                        {rekening.institution || "—"}
                    </p>
                </div>
                <span className="shrink-0 rounded-full bg-bg-cardAlt px-2.5 py-1 text-xs font-semibold text-text-secondary">
                    {rekening.kind_label}
                </span>
            </div>

            <p className="num-tabular mt-4 text-xl font-bold text-text-primary">
                {formatRupiah(rekening.balance)}
            </p>

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
                        saat harganya bergerak.
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
function FormRekening({ show, rekening, kinds, onClose }) {
    const menyunting = rekening !== null;

    const form = useForm({
        name: rekening?.name ?? "",
        kind: rekening?.kind ?? "bank",
        institution: rekening?.institution ?? "",
        opening_balance: rekening?.opening_balance ?? 0,
    });

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
                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                        Hanya bank dan tunai yang boleh menyimpan dana
                        tujuan — nilai saham dan emas bergerak sendiri, sehingga
                        tujuan yang dananya di sana bisa meleset diam-diam.
                    </p>
                    <InputError message={form.errors.kind} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="institution" value="Lembaga (opsional)" />
                    <TextInput
                        id="institution"
                        className="mt-1.5 block w-full"
                        value={form.data.institution ?? ""}
                        onChange={(e) => form.setData("institution", e.target.value)}
                        maxLength={100}
                        placeholder="Bank BCA, Sekuritas, ..."
                    />
                    <InputError message={form.errors.institution} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="opening_balance" value="Saldo saat mulai mencatat" />
                    <CurrencyInput
                        id="opening_balance"
                        className="mt-1.5"
                        value={form.data.opening_balance}
                        onChange={(v) => form.setData("opening_balance", v)}
                    />
                    <p className="mt-1.5 text-xs text-text-muted">
                        Bukan saldo saat rekening dibuka. Isi 0 bila Anda mulai
                        dari kosong.
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
