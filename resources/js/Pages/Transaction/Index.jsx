import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import CurrencyInput from "@/Components/CurrencyInput";
import DangerButton from "@/Components/DangerButton";
import DateInput from "@/Components/DateInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, Link, router, useForm } from "@inertiajs/react";
import { useState } from "react";

/** Jenis yang menuntut bidang tambahan. Dijaga juga di backend. */
const BUTUH_TUJUAN = "transfer";
const BUTUH_UTANG = "payment";

/**
 * Catatan transaksi.
 *
 * Penyaringan bulan dilakukan di BACKEND lewat query string, bukan dengan
 * memuat semua transaksi lalu menyaringnya di sini. Riwayat keuangan tumbuh
 * tanpa batas atas; halaman yang memuat seluruhnya akan melambat diam-diam
 * seiring pemakaian, dan justru pada pengguna yang paling rajin mencatat.
 */
export default function TransactionIndex({
    transactions,
    bulan,
    cashFlow,
    accounts,
    debts,
    types,
}) {
    const [menyunting, setMenyunting] = useState(null);
    const [menambah, setMenambah] = useState(false);

    const gantiBulan = (nilai) => {
        router.get(
            route("transactions.index"),
            { bulan: nilai },
            { preserveState: true, preserveScroll: true, replace: true },
        );
    };

    return (
        <AuthenticatedLayout>
            <Head title="Transaksi" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Transaksi
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Setiap pemasukan dan pengeluaran, tercatat dengan jelas.
                        </p>
                    </div>

                    <div className="flex flex-wrap items-center gap-2">
                        <input
                            type="month"
                            value={bulan}
                            onChange={(e) => gantiBulan(e.target.value)}
                            max={todayInJakarta().slice(0, 7)}
                            aria-label="Pilih bulan"
                            className="num-tabular rounded-lg border-border-strong bg-bg-base text-sm text-text-primary focus:border-lime-500 focus:ring-lime-500"
                        />
                        <PrimaryButton
                            onClick={() => setMenambah(true)}
                            disabled={accounts.length === 0}
                        >
                            Catat transaksi
                        </PrimaryButton>
                    </div>
                </div>

                <RingkasanArusKas arus={cashFlow} />

                {accounts.length === 0 ? (
                    <BelumAdaRekening />
                ) : transactions.data.length === 0 ? (
                    <BulanKosong />
                ) : (
                    <>
                        <div className="mt-6 overflow-hidden rounded-card border border-border bg-bg-card">
                            <table className="w-full text-sm">
                                <thead>
                                    <tr className="border-b border-border text-left text-xs uppercase tracking-wide text-text-muted">
                                        <th className="px-4 py-3 font-semibold">Transaksi</th>
                                        <th className="hidden px-4 py-3 font-semibold sm:table-cell">
                                            Rekening
                                        </th>
                                        <th className="hidden px-4 py-3 font-semibold md:table-cell">
                                            Tanggal
                                        </th>
                                        <th className="px-4 py-3 text-right font-semibold">
                                            Nominal
                                        </th>
                                        <th className="w-10 px-4 py-3">
                                            <span className="sr-only">Aksi</span>
                                        </th>
                                    </tr>
                                </thead>
                                <tbody>
                                    {transactions.data.map((t) => (
                                        <BarisTransaksi
                                            key={t.id}
                                            transaksi={t}
                                            onSunting={() => setMenyunting(t)}
                                        />
                                    ))}
                                </tbody>
                            </table>
                        </div>

                        <Halaman links={transactions.links} />

                        <p className="mt-4 text-xs leading-relaxed text-text-muted">
                            Transfer dan penyesuaian nilai tidak dihitung sebagai
                            pemasukan atau pengeluaran — keduanya tidak memindahkan
                            uang ke luar atau ke dalam kekayaan Anda.
                        </p>
                    </>
                )}
            </div>

            <FormTransaksi
                show={menambah || menyunting !== null}
                transaksi={menyunting}
                accounts={accounts}
                debts={debts}
                types={types}
                onClose={() => {
                    setMenambah(false);
                    setMenyunting(null);
                }}
            />
        </AuthenticatedLayout>
    );
}

function RingkasanArusKas({ arus }) {
    const kartu = [
        { label: "Pemasukan", nilai: arus.income, warna: "text-state-success" },
        { label: "Pengeluaran", nilai: arus.expense, warna: "text-state-danger" },
        { label: "Pokok utang", nilai: arus.principal, warna: "text-text-primary" },
        { label: "Sisa arus kas", nilai: arus.net, warna: "text-lime-500" },
    ];

    return (
        <div className="mt-8 grid grid-cols-2 gap-4 lg:grid-cols-4">
            {kartu.map((k) => (
                <div key={k.label} className="rounded-card border border-border bg-bg-card p-4">
                    <p className="text-xs font-medium text-text-secondary">{k.label}</p>
                    <p className={`num-tabular mt-1.5 break-words text-lg font-bold ${k.warna}`}>
                        {formatRupiah(k.nilai)}
                    </p>
                </div>
            ))}
        </div>
    );
}

function BarisTransaksi({ transaksi, onSunting }) {
    const [konfirmasiHapus, setKonfirmasiHapus] = useState(false);
    const form = useForm({});

    const hapus = () => {
        form.delete(route("transactions.destroy", transaksi.id), {
            preserveScroll: true,
            onSuccess: () => setKonfirmasiHapus(false),
        });
    };

    // Tandanya mengikuti arah uang pada rekening asal, sama seperti yang
    // dipakai backend menghitung saldo — bukan ditebak dari jenisnya di sini.
    const tanda = transaksi.increases_balance ? "+" : "−";

    return (
        <tr className="border-b border-border last:border-0">
            <td className="px-4 py-3">
                <p className="font-medium text-text-primary">{transaksi.name}</p>
                <p className="text-xs text-text-muted">
                    {[transaksi.category, transaksi.type_label]
                        .filter(Boolean)
                        .join(" · ")}
                    {transaksi.to_account && ` → ${transaksi.to_account}`}
                    {transaksi.debt && ` · ${transaksi.debt}`}
                </p>
            </td>
            <td className="hidden px-4 py-3 text-text-secondary sm:table-cell">
                {transaksi.account}
            </td>
            <td className="num-tabular hidden px-4 py-3 text-text-secondary md:table-cell">
                {transaksi.occurred_on}
            </td>
            <td className="num-tabular px-4 py-3 text-right font-semibold text-text-primary">
                {tanda}
                {formatRupiah(Math.abs(transaksi.amount))}
            </td>
            <td className="px-4 py-3 text-right">
                <div className="flex justify-end gap-1">
                    <button
                        type="button"
                        onClick={onSunting}
                        aria-label={`Ubah ${transaksi.name}`}
                        className="rounded-md p-1.5 text-text-muted transition hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M11.5 2.5a1.4 1.4 0 0 1 2 2L6 12l-3 1 1-3z" />
                        </svg>
                    </button>
                    <button
                        type="button"
                        onClick={() => setKonfirmasiHapus(true)}
                        aria-label={`Hapus ${transaksi.name}`}
                        className="rounded-md p-1.5 text-text-muted transition hover:text-state-danger focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        <svg className="h-4 w-4" viewBox="0 0 16 16" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M3 4.5h10M6.5 4.5V3h3v1.5M4.5 4.5l.5 8h6l.5-8" />
                        </svg>
                    </button>
                </div>

                <Modal show={konfirmasiHapus} onClose={() => setKonfirmasiHapus(false)} maxWidth="md">
                    <div className="p-6 text-left">
                        <h2 className="text-base font-semibold text-text-primary">
                            Hapus {transaksi.name}?
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                            Saldo rekening akan disesuaikan kembali. Kalau
                            penghapusan ini membuat saldo menjadi minus, ia akan
                            ditolak.
                        </p>
                        {form.errors.transaction && (
                            <InputError message={form.errors.transaction} className="mt-3" />
                        )}
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
            </td>
        </tr>
    );
}

function Halaman({ links }) {
    if (links.length <= 3) return null;

    return (
        <div className="mt-4 flex flex-wrap justify-center gap-1">
            {links.map((tautan, i) =>
                tautan.url === null ? (
                    <span
                        key={i}
                        className="rounded-md px-3 py-1.5 text-sm text-text-disabled"
                        dangerouslySetInnerHTML={{ __html: tautan.label }}
                    />
                ) : (
                    <Link
                        key={i}
                        href={tautan.url}
                        preserveScroll
                        className={
                            "rounded-md px-3 py-1.5 text-sm transition " +
                            (tautan.active
                                ? "bg-lime-500 font-semibold text-onPrimary"
                                : "text-text-secondary hover:bg-bg-cardAlt hover:text-text-primary")
                        }
                        dangerouslySetInnerHTML={{ __html: tautan.label }}
                    />
                ),
            )}
        </div>
    );
}

function BelumAdaRekening() {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Tambahkan rekening dulu
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Setiap transaksi harus berasal dari sebuah rekening, jadi
                rekeningnya perlu ada lebih dulu.
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

function BulanKosong() {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Belum ada transaksi bulan ini
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Catat pemasukan dan pengeluaran Anda, atau pilih bulan lain di
                atas untuk melihat riwayat sebelumnya.
            </p>
        </div>
    );
}

/**
 * Satu form untuk mencatat dan menyunting.
 *
 * Bidang `to_account_id` dan `debt_id` hanya muncul untuk jenis yang
 * membutuhkannya, dan DIKOSONGKAN saat jenisnya berganti. Backend menolak
 * keduanya bila terisi pada jenis yang salah (lihat StoreTransactionRequest) —
 * membiarkan nilai sisa dari pilihan sebelumnya akan membuat pengeluaran biasa
 * tersimpan seolah membayar utang.
 */
function FormTransaksi({ show, transaksi, accounts, debts, types, onClose }) {
    const menyunting = transaksi !== null;

    const form = useForm({
        account_id: transaksi?.account_id ?? accounts[0]?.id ?? "",
        type: transaksi?.type ?? "expense",
        name: transaksi?.name ?? "",
        amount: transaksi?.amount ?? 0,
        to_account_id: transaksi?.to_account_id ?? "",
        debt_id: transaksi?.debt_id ?? "",
        category: transaksi?.category ?? "",
        occurred_on: transaksi?.occurred_on ?? todayInJakarta(),
    });

    const gantiJenis = (jenis) => {
        form.setData((data) => ({
            ...data,
            type: jenis,
            to_account_id: jenis === BUTUH_TUJUAN ? data.to_account_id : "",
            debt_id: jenis === BUTUH_UTANG ? data.debt_id : "",
        }));
    };

    const simpan = (e) => {
        e.preventDefault();

        const opsi = { preserveScroll: true, onSuccess: onClose };

        if (menyunting) {
            form.patch(route("transactions.update", transaksi.id), opsi);
        } else {
            form.post(route("transactions.store"), opsi);
        }
    };

    const transfer = form.data.type === BUTUH_TUJUAN;
    const pembayaran = form.data.type === BUTUH_UTANG;
    const penyesuaian = form.data.type === "adjustment";

    return (
        <Modal show={show} onClose={onClose} maxWidth="lg">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <h2 className="text-base font-semibold text-text-primary">
                    {menyunting ? "Ubah transaksi" : "Catat transaksi"}
                </h2>

                <div>
                    <InputLabel htmlFor="type" value="Jenis" />
                    <select
                        id="type"
                        className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                        value={form.data.type}
                        onChange={(e) => gantiJenis(e.target.value)}
                    >
                        {types.map((jenis) => (
                            <option key={jenis.value} value={jenis.value}>
                                {jenis.label}
                            </option>
                        ))}
                    </select>
                    <InputError message={form.errors.type} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="name" value="Nama transaksi" />
                    <TextInput
                        id="name"
                        className="mt-1.5 block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        maxLength={100}
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="account_id" value={transfer ? "Dari rekening" : "Rekening"} />
                        <select
                            id="account_id"
                            className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                            value={form.data.account_id}
                            onChange={(e) => form.setData("account_id", e.target.value)}
                        >
                            {accounts.map((r) => (
                                <option key={r.id} value={r.id}>
                                    {r.name}
                                </option>
                            ))}
                        </select>
                        <InputError message={form.errors.account_id} className="mt-2" />
                    </div>

                    {transfer && (
                        <div>
                            <InputLabel htmlFor="to_account_id" value="Ke rekening" />
                            <select
                                id="to_account_id"
                                className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                                value={form.data.to_account_id}
                                onChange={(e) => form.setData("to_account_id", e.target.value)}
                            >
                                <option value="">Pilih rekening tujuan</option>
                                {accounts
                                    .filter((r) => String(r.id) !== String(form.data.account_id))
                                    .map((r) => (
                                        <option key={r.id} value={r.id}>
                                            {r.name}
                                        </option>
                                    ))}
                            </select>
                            <InputError message={form.errors.to_account_id} className="mt-2" />
                        </div>
                    )}

                    {pembayaran && (
                        <div>
                            <InputLabel htmlFor="debt_id" value="Utang yang dibayar" />
                            <select
                                id="debt_id"
                                className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                                value={form.data.debt_id}
                                onChange={(e) => form.setData("debt_id", e.target.value)}
                            >
                                <option value="">Pilih utang</option>
                                {debts.map((u) => (
                                    <option key={u.id} value={u.id}>
                                        {u.name}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.debt_id} className="mt-2" />
                        </div>
                    )}
                </div>

                <div className="grid grid-cols-1 gap-5 sm:grid-cols-2">
                    <div>
                        <InputLabel htmlFor="amount" value="Nominal" />
                        <CurrencyInput
                            id="amount"
                            className="mt-1.5"
                            value={form.data.amount}
                            onChange={(v) => form.setData("amount", v)}
                        />
                        {penyesuaian && (
                            <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                                Boleh minus bila nilai asetnya turun.
                            </p>
                        )}
                        <InputError message={form.errors.amount} className="mt-2" />
                    </div>

                    <div>
                        <InputLabel htmlFor="occurred_on" value="Tanggal" />
                        <DateInput
                            id="occurred_on"
                            className="mt-1.5"
                            max={todayInJakarta()}
                            value={form.data.occurred_on}
                            onChange={(v) => form.setData("occurred_on", v)}
                        />
                        <InputError message={form.errors.occurred_on} className="mt-2" />
                    </div>
                </div>

                {!transfer && !penyesuaian && (
                    <div>
                        <InputLabel htmlFor="category" value="Kategori (opsional)" />
                        <TextInput
                            id="category"
                            className="mt-1.5 block w-full"
                            value={form.data.category ?? ""}
                            onChange={(e) => form.setData("category", e.target.value)}
                            maxLength={100}
                            placeholder="Gaji, Belanja, Transportasi, ..."
                        />
                        <InputError message={form.errors.category} className="mt-2" />
                    </div>
                )}

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
