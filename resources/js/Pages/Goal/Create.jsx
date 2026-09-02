import CurrencyInput from "@/Components/CurrencyInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { nowInJakartaParts } from "@/utils/timezone";
import { solveMonths } from "@/utils/goalCalculator";
import { formatDuration, formatRupiah } from "@/utils/format";
import { useState } from "react";

/**
 * Batas jangka waktu: 1–719 bulan (bukan 720/60 tahun genap), menyalin
 * `after:today` + `before:+60 tahun` di StoreGoalRequest tapi menyisakan
 * jarak aman 1 bulan — perhitungan tanggal di browser (tambahBulan di
 * bawah) dan di server (Carbon::now()->addYears(60) saat request masuk)
 * dievaluasi pada detik yang sedikit berbeda; 719 bulan menghindari kasus
 * tepi langka di mana keduanya jatuh di sisi berlawanan dari batas.
 */
const BATAS_BULAN = 719;

const tenorPresets = [
    { label: "1 thn", months: 12 },
    { label: "3 thn", months: 36 },
    { label: "5 thn", months: 60 },
    { label: "10 thn", months: 120 },
    { label: "20 thn", months: 240 },
];

/** n bulan dari hari ini (WIB), format YYYY-MM-DD. */
const tambahBulan = (jumlahBulan) => {
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const d = new Date(tahun, bulan + jumlahBulan, tanggal);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

/** "2027-11-30" -> "30 November 2027". Format tanggal yang SUDAH diketahui
 * (bukan "hari ini"), jadi aman dipakai toLocaleDateString apa pun zona
 * waktu perangkat pembacanya — lihat resources/js/utils/timezone.js untuk
 * kasus yang justru berbahaya (menentukan "hari ini"). */
const formatTanggalIndonesia = (iso) =>
    new Date(`${iso}T00:00:00`).toLocaleDateString("id-ID", {
        day: "numeric",
        month: "long",
        year: "numeric",
    });

/**
 * Alur "Buat Tujuan Pertama".
 *
 * Nilai awal imbal hasil & inflasi sengaja SUDAH TERISI. Pengguna baru
 * umumnya tidak tahu harus menulis angka berapa, dan kolom kosong di sini
 * menghentikan mereka sama sekali. Angkanya bisa diubah, dan asalnya
 * dijelaskan tepat di bawah kolomnya — bukan angka yang muncul entah dari
 * mana.
 *
 * Kolom tanggal target menghilang bila jenis tujuan tidak bertenggat (dana
 * darurat). Aturan yang sama ditegakkan ulang di StoreGoalRequest — yang di
 * sini semata kenyamanan, penjaganya tetap di server.
 *
 * DUA CARA menentukan tanggal target (`mode`), keduanya UJUNG-UJUNGNYA
 * menghasilkan `target_date` yang sama-sama dikirim ke StoreGoalRequest —
 * backend tidak perlu tahu cara mana yang dipakai pengguna:
 *  - "waktu": pengguna pilih jangka waktu (bulan), gaya sama dengan
 *    Calculator/Goal.jsx (kolom bulan + preset tenor tahunan).
 *  - "harian": pengguna pilih nominal setoran per hari, lalu jangka
 *    waktunya DIHITUNG MUNDUR lewat solveMonths() — pencarian biner yang
 *    sama dipakai WhatIfPanel di kalkulator publik, sudah diuji terhadap
 *    test vector bersama (goalCalculator.test.mjs). Setoran harian
 *    diasumsikan × 30 = setoran bulanan — penyederhanaan yang disebutkan
 *    apa adanya ke pengguna, karena mesin hitungnya sendiri (rate
 *    majemuk, D-1/D-2) seluruhnya bulanan, bukan harian.
 * Perhitungan di sini HANYA pratinjau; begitu tersimpan, GoalController
 * tetap menghitung ulang lewat GoalCalculatorService dari target_date
 * yang terkirim — sama seperti kalkulator publik memperlakukan
 * WhatIfPanel-nya.
 */
export default function GoalCreate({ goalTypes, isFirstGoal }) {
    const form = useForm({
        type: "",
        name: "",
        target_amount: "",
        initial_amount: "",
        target_date: "",
        estimated_return_rate: "7",
        estimated_inflation_rate: "3.5",
    });

    const [mode, setMode] = useState("waktu");
    const [months, setMonths] = useState("");
    const [dailyAmount, setDailyAmount] = useState("");

    const jenisTerpilih = goalTypes.find((t) => t.value === form.data.type);
    const perluTanggal = jenisTerpilih ? jenisTerpilih.requiresDate : true;

    const pilihJenis = (value) => {
        const jenis = goalTypes.find((t) => t.value === value);

        form.setData((sebelumnya) => ({
            ...sebelumnya,
            type: value,
            // Nama diisikan otomatis dari label jenisnya, tetapi HANYA bila
            // pengguna belum menulis apa pun — mengetik lalu kehilangan
            // ketikan sendiri saat berganti pilihan itu menjengkelkan.
            name: sebelumnya.name.trim() === "" ? jenis.label : sebelumnya.name,
        }));
    };

    // Pratinjau mode "harian" — dihitung ulang tiap render, bukan lewat
    // useMemo/useEffect: solveMonths() adalah pencarian biner ≤10 langkah,
    // jauh lebih murah daripada overhead memoization-nya sendiri.
    const setoranBulananSetara = Number(dailyAmount || 0) * 30;
    const bulanTerhitung =
        perluTanggal &&
        mode === "harian" &&
        Number(dailyAmount) > 0 &&
        Number(form.data.target_amount) > 0
            ? solveMonths({
                  targetAmount: Number(form.data.target_amount),
                  currentAmount: Number(form.data.initial_amount) || 0,
                  monthlyContribution: setoranBulananSetara,
                  annualReturnRate:
                      Number(form.data.estimated_return_rate) || 0,
                  annualInflationRate:
                      Number(form.data.estimated_inflation_rate) || 0,
                  maxMonths: BATAS_BULAN,
              })
            : null;

    const submit = (e) => {
        e.preventDefault();

        let targetDate = "";

        if (perluTanggal) {
            if (mode === "waktu") {
                targetDate = tambahBulan(Number(months));
            } else if (bulanTerhitung) {
                targetDate = tambahBulan(bulanTerhitung);
            } else {
                // Tombol submit sudah disabled untuk kondisi ini (lihat
                // JSX di bawah) — ini cuma jaga-jaga.
                return;
            }
        }

        form.transform((data) => ({ ...data, target_date: targetDate }));
        form.post(route("goals.store"));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Buat Tujuan" />

            <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                        {isFirstGoal
                            ? "Buat tujuan pertama Anda"
                            : "Buat tujuan baru"}
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Tentukan apa yang ingin dicapai dan kapan. FinGoal
                        menghitung berapa yang perlu Anda sisihkan tiap bulan,
                        lalu memantau progresnya di Dashboard.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    {/* ── Jenis tujuan ─────────────────────────────────── */}
                    <div className="rounded-card border border-border bg-bg-card p-5">
                        <InputLabel value="Jenis tujuan" />

                        <div className="mt-3 grid grid-cols-2 gap-2 sm:grid-cols-3">
                            {goalTypes.map((jenis) => {
                                const aktif = form.data.type === jenis.value;

                                return (
                                    <button
                                        key={jenis.value}
                                        type="button"
                                        onClick={() => pilihJenis(jenis.value)}
                                        aria-pressed={aktif}
                                        className={
                                            "rounded-lg border px-3 py-3 text-sm font-semibold transition focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card " +
                                            (aktif
                                                ? "border-lime-500 bg-lime-softBg text-lime-500"
                                                : "border-border-strong text-text-secondary hover:border-text-muted hover:text-text-primary")
                                        }
                                    >
                                        {jenis.label}
                                    </button>
                                );
                            })}
                        </div>

                        <InputError
                            message={form.errors.type}
                            className="mt-2"
                        />
                    </div>

                    {/* ── Rincian tujuan ───────────────────────────────── */}
                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <InputLabel htmlFor="name" value="Nama tujuan" />
                            <TextInput
                                id="name"
                                className="mt-1.5 block w-full"
                                value={form.data.name}
                                onChange={(e) =>
                                    form.setData("name", e.target.value)
                                }
                                placeholder="DP Rumah Pertama"
                                maxLength={100}
                            />
                            <InputError
                                message={form.errors.name}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="target_amount"
                                value="Nominal target"
                            />
                            <CurrencyInput
                                id="target_amount"
                                className="mt-1.5"
                                placeholder="500.000.000"
                                value={form.data.target_amount}
                                onChange={(v) =>
                                    form.setData("target_amount", v)
                                }
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Dalam nilai uang hari ini. Pengaruh inflasi
                                dihitung terpisah di bawah.
                            </p>
                            <InputError
                                message={form.errors.target_amount}
                                className="mt-2"
                            />
                        </div>

                        <div>
                            <InputLabel
                                htmlFor="initial_amount"
                                value="Dana awal (opsional)"
                            />
                            <CurrencyInput
                                id="initial_amount"
                                className="mt-1.5"
                                placeholder="0"
                                value={form.data.initial_amount}
                                onChange={(v) =>
                                    form.setData("initial_amount", v)
                                }
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Uang yang sudah Anda sisihkan untuk tujuan ini.
                            </p>
                            <InputError
                                message={form.errors.initial_amount}
                                className="mt-2"
                            />
                        </div>

                        {perluTanggal && (
                            <div>
                                <InputLabel value="Tentukan lewat" />

                                <div className="mt-2 flex gap-2">
                                    <ModePill
                                        label="Jangka waktu"
                                        aktif={mode === "waktu"}
                                        onClick={() => setMode("waktu")}
                                    />
                                    <ModePill
                                        label="Setoran harian"
                                        aktif={mode === "harian"}
                                        onClick={() => setMode("harian")}
                                    />
                                </div>

                                {mode === "waktu" ? (
                                    <div className="mt-4">
                                        <InputLabel
                                            htmlFor="months"
                                            value="Jangka waktu (bulan)"
                                        />
                                        <TextInput
                                            id="months"
                                            type="number"
                                            min="1"
                                            max={BATAS_BULAN}
                                            className="num-tabular mt-1.5 block w-full"
                                            placeholder="120"
                                            value={months}
                                            onChange={(e) =>
                                                setMonths(e.target.value)
                                            }
                                        />

                                        <div className="mt-2 flex flex-wrap gap-1.5">
                                            {tenorPresets.map((p) => (
                                                <button
                                                    key={p.months}
                                                    type="button"
                                                    onClick={() =>
                                                        setMonths(p.months)
                                                    }
                                                    className={
                                                        "rounded-full border px-2.5 py-1 text-xs transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                                                        (Number(months) ===
                                                        p.months
                                                            ? "border-lime-500 bg-lime-softBg text-lime-500"
                                                            : "border-border-strong text-text-muted hover:text-text-primary")
                                                    }
                                                >
                                                    {p.label}
                                                </button>
                                            ))}
                                        </div>

                                        {Number(months) > 0 && (
                                            <p className="mt-2 text-xs text-text-muted">
                                                = {formatDuration(months)},
                                                target tercapai sekitar{" "}
                                                <span className="text-text-secondary">
                                                    {formatTanggalIndonesia(
                                                        tambahBulan(
                                                            Number(months),
                                                        ),
                                                    )}
                                                </span>
                                            </p>
                                        )}
                                    </div>
                                ) : (
                                    <div className="mt-4">
                                        <InputLabel
                                            htmlFor="daily_amount"
                                            value="Setoran per hari"
                                        />
                                        <CurrencyInput
                                            id="daily_amount"
                                            className="mt-1.5"
                                            placeholder="50.000"
                                            value={dailyAmount}
                                            onChange={setDailyAmount}
                                        />
                                        <p className="mt-1.5 text-xs text-text-muted">
                                            Diasumsikan setara{" "}
                                            {formatRupiah(setoranBulananSetara)}{" "}
                                            per bulan (× 30 hari) — mesin
                                            hitungnya bekerja bulanan, bukan
                                            harian.
                                        </p>

                                        {Number(dailyAmount) > 0 &&
                                            Number(form.data.target_amount) <=
                                                0 && (
                                                <p className="mt-3 text-xs text-text-muted">
                                                    Isi nominal target di atas
                                                    dulu untuk melihat perkiraan
                                                    tercapainya.
                                                </p>
                                            )}

                                        {Number(dailyAmount) > 0 &&
                                            Number(form.data.target_amount) >
                                                0 &&
                                            (bulanTerhitung ? (
                                                <p className="mt-3 rounded-lg border-l-2 border-lime-500 bg-bg-cardAlt p-3 text-xs leading-relaxed text-text-secondary">
                                                    Dengan setoran ini, target
                                                    diperkirakan tercapai dalam{" "}
                                                    <span className="font-semibold text-text-primary">
                                                        {formatDuration(
                                                            bulanTerhitung,
                                                        )}
                                                    </span>{" "}
                                                    — sekitar tanggal{" "}
                                                    <span className="font-semibold text-text-primary">
                                                        {formatTanggalIndonesia(
                                                            tambahBulan(
                                                                bulanTerhitung,
                                                            ),
                                                        )}
                                                    </span>
                                                    .
                                                </p>
                                            ) : (
                                                <p className="mt-3 rounded-lg border-l-2 border-state-warning bg-bg-cardAlt p-3 text-xs leading-relaxed text-text-secondary">
                                                    Dengan nominal ini, target
                                                    tidak tercapai dalam 59
                                                    tahun — inflasi menaikkan
                                                    target lebih cepat daripada
                                                    dana Anda bertumbuh. Coba
                                                    nominal yang lebih besar.
                                                </p>
                                            ))}
                                    </div>
                                )}

                                <InputError
                                    message={form.errors.target_date}
                                    className="mt-2"
                                />
                            </div>
                        )}

                        {jenisTerpilih && !perluTanggal && (
                            <p className="rounded-lg border border-border-strong bg-bg-cardAlt px-4 py-3 text-xs leading-relaxed text-text-secondary">
                                Dana darurat tidak diberi tanggal target. Ia
                                dikumpulkan terus-menerus tanpa tenggat, jadi
                                yang dipantau progresnya — bukan hitung
                                mundurnya.
                            </p>
                        )}
                    </div>

                    {/* ── Asumsi perhitungan ───────────────────────────── */}
                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-text-primary">
                                Asumsi perhitungan
                            </h2>
                            <p className="mt-1 text-xs leading-relaxed text-text-muted">
                                Sudah diisi dengan angka yang wajar untuk
                                Indonesia. Ubah bila Anda punya asumsi sendiri.
                            </p>
                        </div>

                        <div className="grid gap-5 sm:grid-cols-2">
                            <div>
                                <InputLabel
                                    htmlFor="estimated_return_rate"
                                    value="Estimasi imbal hasil (% / tahun)"
                                />
                                <TextInput
                                    id="estimated_return_rate"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="30"
                                    className="mt-1.5 block w-full num-tabular"
                                    value={form.data.estimated_return_rate}
                                    onChange={(e) =>
                                        form.setData(
                                            "estimated_return_rate",
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="mt-1.5 text-xs text-text-muted">
                                    7% mendekati campuran deposito dan obligasi
                                    jangka menengah.
                                </p>
                                <InputError
                                    message={form.errors.estimated_return_rate}
                                    className="mt-2"
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="estimated_inflation_rate"
                                    value="Estimasi inflasi (% / tahun)"
                                />
                                <TextInput
                                    id="estimated_inflation_rate"
                                    type="number"
                                    step="0.1"
                                    min="0"
                                    max="20"
                                    className="mt-1.5 block w-full num-tabular"
                                    value={form.data.estimated_inflation_rate}
                                    onChange={(e) =>
                                        form.setData(
                                            "estimated_inflation_rate",
                                            e.target.value,
                                        )
                                    }
                                />
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Menaikkan target agar nilainya tetap setara
                                    saat tanggal target tiba.
                                </p>
                                <InputError
                                    message={
                                        form.errors.estimated_inflation_rate
                                    }
                                    className="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton
                            disabled={
                                form.processing ||
                                (perluTanggal &&
                                    mode === "waktu" &&
                                    !(Number(months) > 0)) ||
                                (perluTanggal &&
                                    mode === "harian" &&
                                    !bulanTerhitung)
                            }
                        >
                            {form.processing ? "Menyimpan…" : "Simpan Tujuan"}
                        </PrimaryButton>

                        <Link href={route("dashboard")}>
                            <SecondaryButton type="button">
                                Batal
                            </SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}

function ModePill({ label, aktif, onClick }) {
    return (
        <button
            type="button"
            onClick={onClick}
            aria-pressed={aktif}
            className={
                "rounded-full px-3.5 py-1.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                (aktif
                    ? "bg-lime-500 text-onPrimary"
                    : "bg-bg-cardAlt text-text-secondary hover:text-text-primary")
            }
        >
            {label}
        </button>
    );
}
