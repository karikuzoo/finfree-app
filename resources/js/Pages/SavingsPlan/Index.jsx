import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import CurrencyInput from "@/Components/CurrencyInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import Modal from "@/Components/Modal";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import { formatRupiah } from "@/utils/format";
import { Head, Link, useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * Rencana menabung.
 *
 * Menjawab "sisa uang saya cukup untuk target yang mana", sedangkan Kalkulator
 * menjawab "berapa per bulan supaya target ini tercapai". Keduanya dipakai
 * bersama: kebutuhan tiap baris di sini dihitung rumus anuitas yang sama
 * dengan kalkulator, lalu dibagi menurut kemampuan yang nyata.
 *
 * Seluruh angka datang jadi dari SavingsPlanService — tidak ada pembagian yang
 * diulang di sini.
 */
export default function SavingsPlanIndex({ plan, goals, accounts, priorities }) {
    const [ubahAnggaran, setUbahAnggaran] = useState(false);
    const [ubahAlokasi, setUbahAlokasi] = useState(null);

    return (
        <AuthenticatedLayout>
            <Head title="Rencana menabung" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Rencana menabung
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Atur prioritas dan temukan ritme menabung yang realistis.
                        </p>
                    </div>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <KartuAnggaran
                        budget={plan.budget}
                        onUbah={() => setUbahAnggaran(true)}
                    />
                    <CaraMembaca />
                </div>

                {plan.budget.income <= 0 && plan.rows.length > 0 && (
                    <AnggaranKosong onIsi={() => setUbahAnggaran(true)} />
                )}

                <div className="mt-8 flex flex-wrap items-end justify-between gap-3">
                    <h2 className="text-base font-semibold text-text-primary">
                        Peta menabungmu
                    </h2>
                    <p className="text-xs text-text-muted">
                        Urut berdasarkan prioritas, lalu tenggat terdekat
                    </p>
                </div>

                {plan.rows.length === 0 ? (
                    <Kosong />
                ) : (
                    <>
                        <div className="mt-4 space-y-4">
                            {plan.rows.map((baris, urutan) => (
                                <BarisRencana
                                    key={baris.goal_id}
                                    baris={baris}
                                    urutan={urutan + 1}
                                    onSesuaikan={() =>
                                        setUbahAlokasi(
                                            goals.find((g) => g.id === baris.goal_id),
                                        )
                                    }
                                />
                            ))}
                        </div>

                        <Penutup plan={plan} />
                    </>
                )}
            </div>

            <FormAnggaran
                key={`anggaran-${ubahAnggaran}`}
                show={ubahAnggaran}
                budget={plan.budget}
                onClose={() => setUbahAnggaran(false)}
            />

            {ubahAlokasi && (
                <FormAlokasi
                    key={ubahAlokasi.id}
                    goal={ubahAlokasi}
                    accounts={accounts}
                    priorities={priorities}
                    onClose={() => setUbahAlokasi(null)}
                />
            )}
        </AuthenticatedLayout>
    );
}

function KartuAnggaran({ budget, onUbah }) {
    const baris = [
        { label: "Perkiraan penghasilan bulanan", nilai: budget.income, tanda: "" },
        { label: "Perkiraan pengeluaran bulanan", nilai: budget.expenses, tanda: "− " },
        { label: "Cicilan pokok aktif", nilai: budget.debt_principal, tanda: "− " },
        { label: "Cadangan bulanan", nilai: budget.reserve, tanda: "− " },
    ];

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-start justify-between gap-3">
                <h2 className="text-sm font-semibold text-text-primary">
                    Anggaran bulanan
                </h2>
                <button
                    type="button"
                    onClick={onUbah}
                    className="rounded-md text-sm font-medium text-lime-500 transition hover:text-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    Ubah
                </button>
            </div>

            <dl className="mt-4 space-y-2.5">
                {baris.map((b) => (
                    <div key={b.label} className="flex justify-between gap-3 text-sm">
                        <dt className="text-text-secondary">{b.label}</dt>
                        <dd className="num-tabular shrink-0 text-text-primary">
                            {b.tanda}
                            {formatRupiah(b.nilai)}
                        </dd>
                    </div>
                ))}
            </dl>

            <div className="mt-4 flex justify-between gap-3 border-t border-border pt-3">
                <span className="text-sm font-semibold text-text-primary">
                    Tersedia untuk target
                </span>
                <span className="num-tabular shrink-0 text-lg font-bold text-lime-500">
                    {formatRupiah(budget.capacity)}
                </span>
            </div>

            {/*
                Cicilan pokok muncul di daftar tapi TIDAK bisa disunting di sini
                — ia datang dari halaman Utang. Menyediakan isian keduanya akan
                melahirkan dua angka yang bisa berbeda.
            */}
            <p className="mt-3 text-xs leading-relaxed text-text-muted">
                Cicilan pokok dihitung otomatis dari{" "}
                <Link
                    href={route("debts.index")}
                    className="text-text-secondary underline hover:text-text-primary"
                >
                    Utang &amp; cicilan
                </Link>
                . Angka lain di kartu ini adalah PERKIRAAN Anda sendiri —
                bukan transaksi yang sudah terjadi, dan tidak menyentuh saldo
                rekening mana pun.
            </p>
        </div>
    );
}

function CaraMembaca() {
    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                Cara membaca rencana
            </p>
            <h2 className="mt-3 text-lg font-semibold leading-snug text-text-primary">
                Satu anggaran.
                <br />
                Prioritas yang jelas.
            </h2>
            <p className="mt-3 text-sm leading-relaxed text-text-secondary">
                Dana dibagikan dari prioritas tinggi ke rendah, lalu berdasarkan
                tenggat terdekat. Kekurangan dana{" "}
                <strong className="text-text-primary">ditampilkan</strong>, bukan
                ditutup dengan asumsi imbal hasil yang lebih tinggi.
            </p>
            <p className="mt-3 text-sm leading-relaxed text-text-secondary">
                Kebutuhan bulanan memakai rumus yang sama dengan Kalkulator —
                sudah memperhitungkan imbal hasil dan inflasi yang Anda tetapkan
                pada tiap tujuan.
            </p>
        </div>
    );
}

function BarisRencana({ baris, urutan, onSesuaikan }) {
    const warnaPrioritas = {
        high: "bg-lime-softBg text-lime-500",
        medium: "bg-bg-cardAlt text-text-secondary",
        low: "bg-bg-cardAlt text-text-muted",
    }[baris.priority];

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex flex-wrap items-start gap-x-6 gap-y-4">
                <span className="num-tabular text-sm font-semibold text-text-muted">
                    {String(urutan).padStart(2, "0")}
                </span>

                <div className="min-w-[10rem] flex-1">
                    <span
                        className={`inline-block rounded-full px-2.5 py-1 text-xs font-semibold ${warnaPrioritas}`}
                    >
                        {baris.priority_label}
                    </span>
                    <p className="mt-2 font-semibold text-text-primary">{baris.name}</p>
                    <p className="num-tabular mt-0.5 text-xs text-text-muted">
                        {baris.target_date
                            ? `${baris.target_date} · ${baris.months_left} bulan`
                            : "Tanpa tenggat"}
                    </p>
                </div>

                <div className="min-w-[9rem]">
                    <p className="text-xs text-text-muted">Kebutuhan / bulan</p>
                    <p className="num-tabular mt-0.5 font-semibold text-text-primary">
                        {formatRupiah(baris.need)}
                    </p>
                    <p className="mt-2 text-xs text-text-muted">Alokasi disarankan</p>
                    <p className="num-tabular mt-0.5 font-semibold text-lime-500">
                        {formatRupiah(baris.allocation)}/bulan
                    </p>
                </div>

                <div className="min-w-[13rem] flex-1">
                    {baris.achieved ? (
                        <p className="text-sm leading-relaxed text-state-success">
                            Sudah tercapai — dananya tidak lagi menyerap anggaran.
                        </p>
                    ) : baris.shortfall > 0 ? (
                        <p className="text-sm leading-relaxed text-state-warning">
                            Kurang{" "}
                            <span className="num-tabular font-semibold">
                                {formatRupiah(baris.shortfall)}
                            </span>{" "}
                            per bulan. Naikkan prioritasnya, mundurkan tenggatnya,
                            atau turunkan targetnya.
                        </p>
                    ) : (
                        <p className="text-sm leading-relaxed text-text-secondary">
                            Sisihkan{" "}
                            <span className="num-tabular">
                                {formatRupiah(baris.allocation)}
                            </span>{" "}
                            setiap bulan. Setara sekitar{" "}
                            <span className="num-tabular">
                                {formatRupiah(baris.daily_equivalent)}
                            </span>
                            /hari.
                        </p>
                    )}

                    <div className="mt-3 flex flex-wrap items-center gap-x-4 gap-y-2">
                        <TombolSisihkan baris={baris} />

                        <button
                            type="button"
                            onClick={onSesuaikan}
                            className="rounded-md text-sm font-medium text-text-secondary transition hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                        >
                            Sesuaikan target →
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}

/**
 * "Sudah saya sisihkan" — satu klik menaikkan dana yang ditandai sebesar
 * alokasi yang disarankan.
 *
 * Sebelum ini pengguna harus membuka form alokasi dan mengetik ulang TOTAL
 * barunya: menghitung sendiri 10.000.000 + 3.750.000. Aritmetika yang memang
 * tugas aplikasi.
 *
 * Yang sudah disisihkan bulan ini ditampilkan apa adanya, termasuk bila
 * jumlahnya belum sebanyak yang disarankan — rencana yang hanya mengenal
 * "sudah" dan "belum" memaksa orang berbohong pada dirinya sendiri di bulan
 * yang cuma sanggup separuh.
 */
/**
 * Seluruh halaman ini bergantung pada anggaran. Tanpa pemasukan, kemampuan
 * menabungnya nol, tiap baris mendapat alokasi nol, dan kekurangan dananya
 * tampil sebesar seluruh kebutuhan — angka menakutkan yang sebenarnya cuma
 * akibat pembagi yang kosong.
 *
 * Dulu keadaan itu tidak dijelaskan sama sekali: halaman tetap menampilkan
 * semuanya seolah rencananya sungguhan. Sekarang disebut lebih dulu, sebelum
 * angka-angka yang belum punya arti itu sempat dipercaya.
 */
function AnggaranKosong({ onIsi }) {
    return (
        <div className="mt-6 rounded-card border border-state-warning/40 bg-bg-cardAlt p-5">
            <h2 className="text-sm font-semibold text-text-primary">
                Isi anggaran bulanan dulu
            </h2>
            <p className="mt-2 max-w-2xl text-sm leading-relaxed text-text-secondary">
                Tanpa perkiraan penghasilan, Arus tidak tahu berapa yang
                bisa Anda sisihkan — jadi tiap target di bawah mendapat Rp 0 dan
                seluruhnya terlihat tertinggal jauh. Angka-angka itu belum
                berarti apa-apa sampai anggarannya diisi.
            </p>
            <button
                type="button"
                onClick={onIsi}
                className="mt-4 rounded-lg bg-lime-500 px-4 py-2 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
            >
                Isi anggaran
            </button>
        </div>
    );
}

function TombolSisihkan({ baris }) {    const form = useForm({ amount: baris.allocation });

    const sisihkan = () => {
        // transform() dipanggil TERPISAH, tidak dirantai.
        //
        // Di adapter React, transform() mengembalikan undefined — ia hanya
        // memasang callback-nya ke sebuah ref. Merantainya seperti di adapter
        // Vue (`form.transform(...).post(...)`) melempar TypeError, dan
        // tombolnya diam sepenuhnya: tidak ada yang terkirim, tidak ada pesan
        // galat di layar.
        //
        // Dan memang harus lewat transform, bukan useForm saja: nominalnya
        // dibaca dari props SAAT dikirim. Setelah sekali berhasil, alokasi
        // yang disarankan berubah, sedangkan state awal useForm tidak ikut
        // diperbarui — klik kedua akan mengirim angka yang sudah basi.
        form.transform(() => ({ amount: baris.allocation }));
        form.post(route("goals.set-aside", baris.goal_id), { preserveScroll: true });
    };

    const sudah = baris.set_aside_this_month;

    if (baris.achieved) {
        return null;
    }

    return (
        <div className="min-w-0">
            {/*
                Tombolnya TIDAK pernah lenyap begitu saja. Versi pertama
                menyembunyikannya saat alokasinya nol, dan hasilnya sebuah
                halaman yang menyuruh menyisihkan uang tanpa menyediakan
                caranya — tanpa satu kata pun menjelaskan sebabnya.
            */}
            {!baris.can_set_aside ? (
                <p className="text-xs leading-relaxed text-text-muted">
                    Tentukan dulu rekening tempat dananya berada lewat
                    &ldquo;Sesuaikan target&rdquo;.
                </p>
            ) : baris.allocation <= 0 ? (
                <p className="text-xs leading-relaxed text-text-muted">
                    Belum ada dana yang bisa dialokasikan untuk target ini bulan
                    ini.
                </p>
            ) : (
                <button
                    type="button"
                    onClick={sisihkan}
                    disabled={form.processing}
                    className="rounded-lg bg-lime-500 px-3 py-1.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                >
                    {form.processing ? "Menyimpan…" : "Sudah saya sisihkan"}
                </button>
            )}

            {sudah > 0 && (
                <p className="num-tabular mt-1.5 text-xs text-state-success">
                    Bulan ini sudah disisihkan {formatRupiah(sudah)}
                </p>
            )}

            <InputError message={form.errors.amount} className="mt-1.5" />
        </div>
    );
}

function Penutup({ plan }) {
    if (plan.total_shortfall > 0) {
        return (
            <div className="mt-6 rounded-card border border-border bg-bg-card p-5">
                <p className="text-sm leading-relaxed text-text-secondary">
                    Seluruh target menuntut{" "}
                    <span className="num-tabular font-semibold text-text-primary">
                        {formatRupiah(plan.total_need)}
                    </span>{" "}
                    per bulan, sedangkan kemampuan Anda{" "}
                    <span className="num-tabular font-semibold text-text-primary">
                        {formatRupiah(plan.budget.capacity)}
                    </span>
                    . Kekurangannya{" "}
                    <span className="num-tabular font-semibold text-state-warning">
                        {formatRupiah(plan.total_shortfall)}
                    </span>{" "}
                    per bulan.
                </p>
            </div>
        );
    }

    if (plan.unallocated > 0) {
        return (
            <div className="mt-6 rounded-card border border-border bg-bg-card p-5">
                <p className="text-sm leading-relaxed text-text-secondary">
                    Seluruh target Anda tertutup, dan masih tersisa{" "}
                    <span className="num-tabular font-semibold text-lime-500">
                        {formatRupiah(plan.unallocated)}
                    </span>{" "}
                    per bulan yang belum punya tujuan.
                </p>
            </div>
        );
    }

    return null;
}

function Kosong() {
    return (
        <div className="mt-4 rounded-card border border-border bg-bg-card px-6 py-12 text-center">
            <h2 className="text-lg font-semibold text-text-primary">
                Belum ada target untuk direncanakan
            </h2>
            <p className="mx-auto mt-2 max-w-sm text-sm leading-relaxed text-text-secondary">
                Buat tujuan finansial lebih dulu — rencana menabung membagi
                kemampuan Anda ke tujuan-tujuan itu menurut prioritasnya.
            </p>
            <div className="mt-6">
                <Link
                    href={route("goals.create")}
                    className="inline-block rounded-lg bg-lime-500 px-5 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                >
                    Buat tujuan
                </Link>
            </div>
        </div>
    );
}

function FormAnggaran({ show, budget, onClose }) {
    const form = useForm({
        planned_income: budget.income,
        planned_expenses: budget.expenses,
        monthly_reserve: budget.reserve,
    });

    const simpan = (e) => {
        e.preventDefault();
        form.patch(route("savings-plan.budget.update"), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    const bidang = [
        {
            nama: "planned_income",
            label: "Perkiraan penghasilan bulanan",
            bantuan:
                "Gaji dan penghasilan rutin lain. Ini PERKIRAAN untuk menghitung " +
                "kemampuan menabung — bukan uang masuk, dan tidak menambah saldo " +
                "rekening mana pun. Pemasukan yang sungguhan dicatat di Transaksi.",
        },
        {
            nama: "planned_expenses",
            label: "Perkiraan pengeluaran bulanan",
            bantuan: "Biaya hidup rutin, di luar cicilan pokok utang.",
        },
        {
            nama: "monthly_reserve",
            label: "Cadangan bulanan",
            bantuan:
                "Dana yang sengaja tidak dialokasikan ke target — penyangga untuk hal tak terduga.",
        },
    ];

    return (
        <Modal show={show} onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <h2 className="text-base font-semibold text-text-primary">
                    Anggaran bulanan
                </h2>

                {bidang.map((b) => (
                    <div key={b.nama}>
                        <InputLabel htmlFor={b.nama} value={b.label} />
                        <CurrencyInput
                            id={b.nama}
                            className="mt-1.5"
                            value={form.data[b.nama]}
                            onChange={(v) => form.setData(b.nama, v)}
                        />
                        <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                            {b.bantuan}
                        </p>
                        <InputError message={form.errors[b.nama]} className="mt-2" />
                    </div>
                ))}

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
 * Alokasi MENANDAI saldo rekening, tidak memindahkan uang. Kalimat itu
 * ditulis di formulirnya sendiri — tanpa penjelasan, orang mengira menandai
 * 10 juta akan mengurangi saldo banknya sebesar itu.
 */
function FormAlokasi({ goal, accounts, priorities, onClose }) {
    const form = useForm({
        account_id: goal.account_id ?? "",
        allocated_amount: goal.allocated_amount,
        priority: goal.priority,
    });

    const simpan = (e) => {
        e.preventDefault();
        form.patch(route("goals.allocation.update", goal.id), {
            preserveScroll: true,
            onSuccess: onClose,
        });
    };

    return (
        <Modal show onClose={onClose} maxWidth="md">
            <form onSubmit={simpan} className="space-y-5 p-6">
                <div>
                    <h2 className="text-base font-semibold text-text-primary">
                        Sesuaikan {goal.name}
                    </h2>
                    <p className="num-tabular mt-1.5 text-sm text-text-secondary">
                        Target {formatRupiah(goal.target_amount)}
                    </p>
                </div>

                <div>
                    <InputLabel htmlFor="priority" value="Prioritas" />
                    <select
                        id="priority"
                        className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                        value={form.data.priority}
                        onChange={(e) => form.setData("priority", e.target.value)}
                    >
                        {priorities.map((p) => (
                            <option key={p.value} value={p.value}>
                                {p.label}
                            </option>
                        ))}
                    </select>
                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                        Menentukan siapa yang dilayani lebih dulu saat dananya
                        tidak cukup untuk semua target.
                    </p>
                    <InputError message={form.errors.priority} className="mt-2" />
                </div>

                {accounts.length === 0 ? (
                    <p className="rounded-lg border border-border bg-bg-cardAlt p-4 text-sm leading-relaxed text-text-secondary">
                        Belum ada rekening bank atau tunai. Dana tujuan hanya
                        boleh disimpan di sana — nilai saham dan emas bergerak
                        sendiri, sehingga targetnya bisa meleset diam-diam.
                    </p>
                ) : (
                    <>
                        <div>
                            <InputLabel htmlFor="account_id" value="Uangnya disimpan di" />
                            <select
                                id="account_id"
                                className="mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary focus:border-lime-500 focus:ring-lime-500"
                                value={form.data.account_id ?? ""}
                                onChange={(e) =>
                                    form.setData("account_id", e.target.value || null)
                                }
                            >
                                <option value="">Belum ditentukan</option>
                                {accounts.map((r) => (
                                    <option key={r.id} value={r.id}>
                                        {r.name} — {formatRupiah(r.balance)}
                                    </option>
                                ))}
                            </select>
                            <InputError message={form.errors.account_id} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="allocated_amount"
                                value="Sudah terkumpul untuk tujuan ini"
                            />
                            <CurrencyInput
                                id="allocated_amount"
                                className="mt-1.5"
                                value={form.data.allocated_amount}
                                onChange={(v) => form.setData("allocated_amount", v)}
                            />
                            <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                                Berapa bagian dari saldo rekening itu yang untuk
                                tujuan ini. Saldo rekening Anda TIDAK berkurang —
                                uangnya tetap di sana, hanya kini punya tujuan.
                                Gabungan seluruh tujuan pada satu rekening tidak
                                boleh melebihi saldonya.
                            </p>
                            <InputError
                                message={form.errors.allocated_amount}
                                className="mt-2"
                            />
                        </div>
                    </>
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
