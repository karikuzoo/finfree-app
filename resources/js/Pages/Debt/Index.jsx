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
import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Utang & cicilan.
 *
 * Status lunas TIDAK punya tombol sendiri — ia diturunkan dari riwayat
 * pembayaran. Tombol "tandai lunas" akan menjadi sumber kebenaran kedua yang
 * bisa berbeda dari angkanya sendiri, dan utang bertanda lunas dengan sisa
 * tiga juta adalah tampilan yang tidak bisa dipercaya lagi.
 */
export default function DebtIndex({ debts, totalRemaining, monthlyPrincipal, accounts }) {
    const [menyunting, setMenyunting] = useState(null);
    const [menambah, setMenambah] = useState(false);
    const [membayar, setMembayar] = useState(null);

    return (
        <AuthenticatedLayout>
            <Head title="Utang & cicilan" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Utang &amp; cicilan
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Ketahui sisa kewajiban dan catat pembayarannya.
                        </p>
                    </div>

                    <PrimaryButton onClick={() => setMenambah(true)}>
                        Catat utang
                    </PrimaryButton>
                </div>

                <div className="mt-8 rounded-card border border-border bg-bg-card p-5">
                    <div className="flex flex-wrap items-start justify-between gap-4">
                        <div>
                            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                                Total sisa utang
                            </p>
                            <p className="num-tabular mt-1.5 text-3xl font-bold text-text-primary">
                                {formatRupiah(totalRemaining)}
                            </p>
                            {monthlyPrincipal > 0 && (
                                <p className="num-tabular mt-1 text-sm text-text-secondary">
                                    Rencana pokok {formatRupiah(monthlyPrincipal)}/bulan
                                </p>
                            )}
                        </div>
                        <p className="max-w-sm text-xs leading-relaxed text-text-muted">
                            Catat sisa pokok saat mulai memakai Arus — bukan nilai
                            pinjaman aslinya. Mencatat utang tidak menambah saldo
                            rekening. Bunga dicatat terpisah sebagai pengeluaran,
                            supaya sisa pokoknya selalu menunjukkan angka
                            sebenarnya.
                        </p>
                    </div>
                </div>

                {debts.length === 0 ? (
                    <Kosong onTambah={() => setMenambah(true)} />
                ) : (
                    <div className="mt-6 grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-3">
                        {debts.map((utang) => (
                            <KartuUtang
                                key={utang.id}
                                utang={utang}
                                adaRekening={accounts.length > 0}
                                onSunting={() => setMenyunting(utang)}
                                onBayar={() => setMembayar(utang)}
                            />
                        ))}
                    </div>
                )}
            </div>

            <FormUtang
                key={menyunting?.id ?? "baru"}
                show={menambah || menyunting !== null}
                utang={menyunting}
                onClose={() => {
                    setMenambah(false);
                    setMenyunting(null);
                }}
            />

            {membayar && (
                <FormPembayaran
                    key={membayar.id}
                    utang={membayar}
                    accounts={accounts}
                    onClose={() => setMembayar(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}

function KartuUtang({ utang, adaRekening, onSunting, onBayar }) {
    const [konfirmasiHapus, setKonfirmasiHapus] = useState(false);
    const form = useForm({});

    const hapus = () => {
        form.delete(route("debts.destroy", utang.id), {
            preserveScroll: true,
            onSuccess: () => setKonfirmasiHapus(false),
        });
    };

    const lewatJatuhTempo =
        !utang.settled && utang.due_on !== null && utang.due_on < todayInJakarta();

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <p className="min-w-0 truncate font-semibold text-text-primary">
                    {utang.name}
                </p>
                <span
                    className={
                        "shrink-0 rounded-full px-2.5 py-1 text-xs font-semibold " +
                        (utang.settled
                            ? "bg-lime-softBg text-lime-500"
                            : "bg-bg-cardAlt text-text-secondary")
                    }
                >
                    {utang.settled ? "Lunas" : "Aktif"}
                </span>
            </div>

            <p className="num-tabular mt-4 text-xl font-bold text-text-primary">
                {formatRupiah(utang.remaining)}
            </p>
            <p className="num-tabular mt-0.5 text-xs text-text-muted">
                dari {formatRupiah(utang.principal)} · {utang.progress.toFixed(1)}% terbayar
            </p>

            <div className="mt-3 h-1.5 overflow-hidden rounded-full bg-bg-cardAlt">
                <div
                    className="h-full rounded-full bg-lime-500"
                    style={{ width: `${Math.min(100, utang.progress)}%` }}
                />
            </div>

            <div className="mt-4 space-y-1.5 border-t border-border pt-3 text-xs">
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Rencana pokok per bulan</span>
                    <span className="num-tabular text-text-secondary">
                        {utang.monthly_principal > 0
                            ? formatRupiah(utang.monthly_principal)
                            : "Tidak tetap"}
                    </span>
                </div>
                <div className="flex justify-between gap-2">
                    <span className="text-text-muted">Jatuh tempo akhir</span>
                    <span
                        className={
                            "num-tabular " +
                            (lewatJatuhTempo ? "font-semibold text-state-warning" : "text-text-secondary")
                        }
                    >
                        {utang.due_on ?? "—"}
                    </span>
                </div>
            </div>

            {lewatJatuhTempo && (
                <p className="mt-3 text-xs leading-relaxed text-state-warning">
                    Sudah lewat jatuh tempo.
                </p>
            )}

            {form.errors.debt && <InputError message={form.errors.debt} className="mt-3" />}

            <div className="mt-4 flex flex-wrap gap-2">
                {!utang.settled && (
                    <PrimaryButton
                        onClick={onBayar}
                        disabled={!adaRekening}
                        className="flex-1 justify-center"
                    >
                        Catat pembayaran
                    </PrimaryButton>
                )}
                <SecondaryButton onClick={onSunting} className="justify-center">
                    Ubah
                </SecondaryButton>
                <SecondaryButton onClick={() => setKonfirmasiHapus(true)} className="justify-center">
                    Hapus
                </SecondaryButton>
            </div>

            <Modal show={konfirmasiHapus} onClose={() => setKonfirmasiHapus(false)} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-base font-semibold text-text-primary">
                        Hapus {utang.name}?
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Utang yang sudah punya riwayat pembayaran tidak bisa
                        dihapus — pembayarannya adalah uang yang benar-benar
                        keluar dari rekening Anda.
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
                Tidak ada utang tercatat
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Kalau Anda punya cicilan atau pinjaman berjalan, catat sisa
                pokoknya di sini. Kekayaan bersih Anda akan menghitungnya.
            </p>
            <div className="mt-6">
                <PrimaryButton onClick={onTambah}>Catat utang</PrimaryButton>
            </div>
        </div>
    );
}

function FormUtang({ show, utang, onClose }) {
    const menyunting = utang !== null && utang !== undefined;

    const form = useForm({
        name: utang?.name ?? "",
        principal: utang?.principal ?? 0,
        monthly_principal: utang?.monthly_principal ?? 0,
        due_on: utang?.due_on ?? "",
    });

    const simpan = (e) => {
        e.preventDefault();

        const opsi = { preserveScroll: true, onSuccess: onClose };

        if (menyunting) {
            form.patch(route("debts.update", utang.id), opsi);
        } else {
            form.post(route("debts.store"), opsi);
        }
    };

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <h2 className="text-base font-semibold text-text-primary">
                    {menyunting ? `Ubah ${utang.name}` : "Catat utang"}
                </h2>

                <div>
                    <InputLabel htmlFor="name" value="Nama utang" />
                    <TextInput
                        id="name"
                        className="mt-1.5 block w-full"
                        value={form.data.name}
                        onChange={(e) => form.setData("name", e.target.value)}
                        maxLength={100}
                        placeholder="Cicilan motor, pinjaman keluarga, ..."
                        autoFocus
                    />
                    <InputError message={form.errors.name} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="principal" value="Sisa pokok saat mulai mencatat" />
                    <CurrencyInput
                        id="principal"
                        className="mt-1.5"
                        value={form.data.principal}
                        onChange={(v) => form.setData("principal", v)}
                    />
                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                        Bukan nilai pinjaman aslinya, dan tanpa bunga. Mencatat ini
                        tidak menambah saldo rekening mana pun.
                    </p>
                    <InputError message={form.errors.principal} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="monthly_principal" value="Rencana pokok per bulan" />
                    <CurrencyInput
                        id="monthly_principal"
                        className="mt-1.5"
                        value={form.data.monthly_principal}
                        onChange={(v) => form.setData("monthly_principal", v)}
                    />
                    <p className="mt-1.5 text-xs text-text-muted">
                        Isi 0 bila cicilannya tidak tetap.
                    </p>
                    <InputError message={form.errors.monthly_principal} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="due_on" value="Jatuh tempo akhir (opsional)" />
                    <DateInput
                        id="due_on"
                        className="mt-1.5"
                        placeholder="Tanpa jatuh tempo"
                        value={form.data.due_on}
                        onChange={(v) => form.setData("due_on", v)}
                    />
                    <InputError message={form.errors.due_on} className="mt-2" />
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
 * Pembayaran dikirim ke endpoint TRANSAKSI, bukan endpoint utang.
 *
 * Ia memang transaksi: uangnya keluar dari sebuah rekening. Seluruh
 * penolakannya — saldo tidak cukup, pembayaran melebihi sisa pokok — sudah
 * berlaku di sana tanpa perlu ditulis ulang.
 */
function FormPembayaran({ utang, accounts, onClose }) {
    const form = useForm({
        account_id: accounts[0]?.id ?? "",
        type: "payment",
        name: `Bayar ${utang.name}`,
        amount: utang.monthly_principal > 0 ? utang.monthly_principal : 0,
        debt_id: utang.id,
        occurred_on: todayInJakarta(),
    });

    const simpan = (e) => {
        e.preventDefault();
        form.post(route("transactions.store"), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <div>
                    <h2 className="text-base font-semibold text-text-primary">
                        Catat pembayaran {utang.name}
                    </h2>
                    <p className="num-tabular mt-1.5 text-sm text-text-secondary">
                        Sisa pokok: {formatRupiah(utang.remaining)}
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="bayar_account" value="Dibayar dari rekening" />
                    <select
                        id="bayar_account"
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

                <div>
                    <InputLabel htmlFor="bayar_amount" value="Nominal pokok" />
                    <CurrencyInput
                        id="bayar_amount"
                        className="mt-1.5"
                        value={form.data.amount}
                        onChange={(v) => form.setData("amount", v)}
                    />
                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                        Pokoknya saja. Bunga dicatat terpisah sebagai pengeluaran —
                        mencampurnya membuat utang tampak lunas lebih cepat
                        daripada kenyataannya.
                    </p>
                    <InputError message={form.errors.amount} className="mt-2" />
                </div>

                <div>
                    <InputLabel htmlFor="bayar_tanggal" value="Tanggal" />
                    <DateInput
                        id="bayar_tanggal"
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
