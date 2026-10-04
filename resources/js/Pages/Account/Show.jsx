import AccountBadge from "@/Components/AccountBadge";
import SecondaryButton from "@/Components/SecondaryButton";
import { KeteranganSatuan } from "@/Components/UnitsInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { FormRekening } from "@/Pages/Account/Index";
import { formatRupiah } from "@/utils/format";
import { Head, Link } from "@inertiajs/react";
import { useState } from "react";

/**
 * Detail satu rekening — props dari AccountController::show.
 *
 * Kepalanya memakai bentuk yang sama dengan kartu di daftar (`account`),
 * jadi angkanya pasti sama. Mutasinya (`mutations`, paginator Laravel)
 * sudah membawa saldo sesudah tiap baris dari server; halaman ini tidak
 * menjumlahkan apa pun (CLAUDE.md §6.9).
 */
export default function AccountShow({ account, mutations, kinds }) {
    const [menyunting, setMenyunting] = useState(false);

    return (
        <AuthenticatedLayout>
            <Head title={account.name} />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <Link
                    href={route("accounts.index")}
                    className="text-xs font-medium text-text-muted transition hover:text-text-primary"
                >
                    ← Semua rekening
                </Link>

                <Ringkasan account={account} onSunting={() => setMenyunting(true)} />
                <DanaTujuan account={account} />
                <Mutasi mutations={mutations} />
            </div>

            <FormRekening
                key={menyunting ? "sunting" : "tutup"}
                show={menyunting}
                rekening={account}
                kinds={kinds}
                onClose={() => setMenyunting(false)}
            />
        </AuthenticatedLayout>
    );
}

/** "2026-09-05" -> "5 Sep 2026". Tanggal yang sudah diketahui, bukan "hari ini". */
const tanggal = (iso) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "short",
        year: "numeric",
    });

function Ringkasan({ account, onSunting }) {
    return (
        <div className="mt-4 rounded-card border border-border bg-bg-card p-5">
            <div className="flex flex-wrap items-start justify-between gap-3">
                <div className="flex min-w-0 items-center gap-3">
                    <AccountBadge rekening={account} />
                    <div className="min-w-0">
                        <h1 className="truncate text-xl font-bold tracking-tight text-text-primary">
                            {account.name}
                        </h1>
                        <p className="truncate text-sm text-text-muted">
                            {account.institution || "—"} · {account.kind_label}
                        </p>
                    </div>
                </div>
                <SecondaryButton onClick={onSunting}>Ubah</SecondaryButton>
            </div>

            <p className="mt-5 text-xs font-semibold uppercase tracking-wide text-text-muted">
                Saldo sekarang
            </p>
            <p className="num-tabular mt-1 text-3xl font-bold text-text-primary">
                {formatRupiah(account.balance)}
            </p>
            <KeteranganSatuan rekening={account} nilai={account.balance} />

            <dl className="mt-5 grid grid-cols-1 gap-x-6 gap-y-2 border-t border-border pt-4 text-sm sm:grid-cols-3">
                <Angka istilah="Saldo awal" nilai={account.opening_balance} />
                <Angka istilah="Untuk tujuan" nilai={account.allocated} />
                <Angka istilah="Bebas dipakai" nilai={account.free} tebal />
            </dl>

            {account.needs_valuation && (
                <p className="mt-4 text-xs leading-relaxed text-text-muted">
                    {account.last_valuation
                        ? `Nilai terakhir diperbarui ${tanggal(account.last_valuation)}.`
                        : "Nilainya belum pernah diperbarui sejak dicatat."}{" "}
                    Nilainya tidak berubah sendiri — catat penyesuaian nilai saat
                    harganya bergerak.
                </p>
            )}
        </div>
    );
}

function Angka({ istilah, nilai, tebal = false }) {
    return (
        <div className="flex items-baseline justify-between gap-3 sm:block">
            <dt className="text-text-muted">{istilah}</dt>
            <dd
                className={
                    "num-tabular sm:mt-0.5 " +
                    (tebal ? "font-semibold text-text-primary" : "text-text-secondary")
                }
            >
                {formatRupiah(nilai)}
            </dd>
        </div>
    );
}

/** Rincian "untuk tujuan" per tujuan — di kartu hanya namanya yang disebut. */
function DanaTujuan({ account }) {
    if (!account.allocated_goals?.length) return null;

    return (
        <section className="mt-6 rounded-card border border-border bg-bg-card p-5">
            <h2 className="text-sm font-semibold text-text-primary">Dana tujuan di rekening ini</h2>
            <p className="mt-1 text-xs text-text-muted">
                Uangnya tetap di rekening ini — tujuan hanya menandainya, jadi
                pengeluaran dibatasi pada yang bebas dipakai.
            </p>
            <ul className="mt-3 divide-y divide-border">
                {account.allocated_goals.map((g) => (
                    <li key={g.id} className="flex items-baseline justify-between gap-3 py-2 text-sm">
                        <span className="min-w-0 truncate text-text-secondary">{g.name}</span>
                        <span className="num-tabular shrink-0 text-text-primary">
                            {formatRupiah(g.amount)}
                        </span>
                    </li>
                ))}
            </ul>
        </section>
    );
}

/** Baris kedua tiap mutasi: jenis, lalu lawan transfer / utang / kategori. */
function keterangan(m) {
    const rincian = m.counterpart
        ? (m.type_label === "Transfer masuk" ? `dari ${m.counterpart}` : `ke ${m.counterpart}`)
        : (m.debt ?? m.category);

    return rincian ? `${m.type_label} · ${rincian}` : m.type_label;
}

function Mutasi({ mutations }) {
    return (
        <section className="mt-6 rounded-card border border-border bg-bg-card p-5">
            <h2 className="text-sm font-semibold text-text-primary">Mutasi</h2>
            <p className="mt-1 text-xs text-text-muted">
                Terbaru di atas. Saldo di kanan adalah saldo sesudah transaksi itu.
            </p>

            {mutations.data.length === 0 ? (
                <p className="mt-6 text-center text-sm text-text-secondary">
                    Belum ada transaksi di rekening ini.
                </p>
            ) : (
                <ul className="mt-3 divide-y divide-border">
                    {mutations.data.map((m) => (
                        <li key={m.id} className="flex items-start justify-between gap-3 py-3">
                            <div className="min-w-0">
                                <p className="truncate text-sm font-medium text-text-primary">{m.name}</p>
                                <p className="mt-0.5 truncate text-xs text-text-muted">
                                    <time dateTime={m.occurred_on}>{tanggal(m.occurred_on)}</time>
                                    {" · "}
                                    {keterangan(m)}
                                </p>
                            </div>
                            <div className="shrink-0 text-right">
                                <p
                                    className={
                                        "num-tabular text-sm font-semibold " +
                                        (m.amount >= 0 ? "text-state-success" : "text-text-primary")
                                    }
                                >
                                    {m.amount >= 0 ? "+" : "−"}
                                    {formatRupiah(Math.abs(m.amount))}
                                </p>
                                <p className="num-tabular mt-0.5 text-xs text-text-muted">
                                    Saldo {formatRupiah(m.balance_after)}
                                </p>
                            </div>
                        </li>
                    ))}
                </ul>
            )}

            {(mutations.prev_page_url || mutations.next_page_url) && (
                <nav
                    aria-label="Halaman mutasi"
                    className="mt-4 flex items-center justify-between border-t border-border pt-4 text-sm"
                >
                    <HalamanLain href={mutations.prev_page_url}>← Lebih baru</HalamanLain>
                    <span className="text-xs text-text-muted">
                        Halaman {mutations.current_page} dari {mutations.last_page}
                    </span>
                    <HalamanLain href={mutations.next_page_url}>Lebih lama →</HalamanLain>
                </nav>
            )}
        </section>
    );
}

function HalamanLain({ href, children }) {
    if (!href) return <span className="w-24" aria-hidden="true" />;

    return (
        <Link
            href={href}
            preserveScroll
            className="w-24 font-semibold text-lime-500 hover:underline last:text-right"
        >
            {children}
        </Link>
    );
}
