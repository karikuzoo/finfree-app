import CurrencyInput from "@/Components/CurrencyInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { nowInJakartaParts } from "@/utils/timezone";
import {
    calculateMonthlyContribution,
    solveMonths,
} from "@/utils/goalCalculator";
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
 * Imbal hasil & inflasi bernilai awal NOL, bukan angka "wajar" yang
 * disodorkan lebih dulu. Kolomnya tetap terisi — bukan kosong yang
 * menghentikan pengguna baru — tetapi nolnya tidak menjanjikan apa pun:
 * hasilnya murni target dibagi jangka waktu.
 *
 * Alasannya: angka yang sudah terisi cenderung diterima apa adanya. Asumsi
 * imbal hasil yang lolos tanpa dipikirkan langsung membentuk setoran bulanan
 * yang dijanjikan aplikasi ke pengguna, dan janji itu meleset bila asumsinya
 * tidak pernah benar-benar dipilih. Angka acuan tetap disebutkan di bawah
 * kolomnya bagi yang ingin memakainya.
 *
 * Isian tanggal target menyesuaikan mode yang dipilih, dan mode "Tanpa
 * tenggat" tidak mengirim tanggal sama sekali. Aturan yang sama ditegakkan
 * ulang di StoreGoalRequest — yang di sini semata kenyamanan, penjaganya
 * tetap di server.
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
export default function GoalCreate({ isFirstGoal }) {
    const form = useForm({
        name: "",
        target_amount: "",
        initial_amount: "",
        target_date: "",
        // Nol, bukan angka "wajar" yang disodorkan lebih dulu. Angka yang
        // sudah terisi cenderung diterima apa adanya, dan asumsi imbal hasil
        // yang diterima tanpa dipikirkan langsung membentuk setoran bulanan
        // yang dijanjikan ke pengguna. Mulai dari nol berarti hasilnya polos:
        // target dibagi jangka waktu, tanpa menjanjikan pertumbuhan apa pun.
        // Pengguna menambahkan asumsi secara sadar, bukan mewarisinya.
        estimated_return_rate: "0",
        estimated_inflation_rate: "0",
    });

    const [mode, setMode] = useState("waktu");
    const [months, setMonths] = useState("");
    const [dailyAmount, setDailyAmount] = useState("");

    // Tujuan tanpa tenggat kini jadi MODE tersendiri, bukan turunan dari
    // jenis tujuan. Sebelumnya hanya "dana darurat" yang boleh tanpa tanggal;
    // setelah pilihan jenis dihapus, kemampuan itu akan ikut hilang kalau
    // tidak dipindahkan ke sini — padahal kolom target_date di database
    // memang nullable dan Dashboard sudah menangani tujuan tanpa tenggat
    // (days_remaining & on_track bernilai null).
    const perluTanggal = mode !== "tanpa";

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

    // Jangka waktu efektif menurut mode yang sedang dipakai. NULL untuk
    // "Tanpa tenggat" — tanpa jangka waktu, setoran bulanan tidak punya arti.
    const bulanEfektif =
        mode === "waktu"
            ? Number(months) || null
            : mode === "harian"
              ? bulanTerhitung
              : null;

    // Hasil perhitungan yang SAMA dengan yang nanti disimpan server.
    //
    // Sebelum ini, imbal hasil dan inflasi diminta lalu hasilnya hanya ditulis
    // ke goal_calculations dan tidak pernah ditampilkan di mana pun — pengguna
    // mengisi dua angka yang tampak tidak berpengaruh apa-apa. Meminta masukan
    // lalu membuang keluarannya diam-diam lebih buruk daripada tidak meminta.
    //
    // Mesinnya cermin dari GoalCalculatorService dan diuji terhadap test vector
    // yang sama (docs/fixtures/calculator-cases.json), jadi angka di sini pasti
    // sama dengan yang tersimpan. Dihitung ulang tiap render tanpa useMemo:
    // biayanya beberapa operasi aritmetika, jauh di bawah ongkos memoization.
    const hasil = bulanEfektif
        ? calculateMonthlyContribution({
              targetAmount: Number(form.data.target_amount) || 0,
              currentAmount: Number(form.data.initial_amount) || 0,
              months: bulanEfektif,
              annualReturnRate: Number(form.data.estimated_return_rate) || 0,
              annualInflationRate:
                  Number(form.data.estimated_inflation_rate) || 0,
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

                        {/*
                            Tombol mode SELALU dirender. Sebelumnya seluruh blok
                            ini dibungkus {perluTanggal && ...}, sehingga memilih
                            "Tanpa tenggat" membuat tombolnya sendiri ikut
                            menghilang — pengguna terjebak dan harus memuat ulang
                            halaman untuk berpindah mode. Yang boleh berganti
                            hanyalah isian di bawahnya.
                        */}
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
                                    <ModePill
                                        label="Tanpa tenggat"
                                        aktif={mode === "tanpa"}
                                        onClick={() => setMode("tanpa")}
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
                                ) : mode === "harian" ? (
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
                            ) : (
                                <p className="mt-4 rounded-lg border border-border-strong bg-bg-cardAlt px-4 py-3 text-xs leading-relaxed text-text-secondary">
                                    Tujuan ini dikumpulkan terus-menerus tanpa
                                    tenggat — cocok untuk dana darurat. Yang
                                    dipantau progresnya, bukan hitung mundurnya,
                                    dan setoran bulanan tidak dihitung karena
                                    tanpa jangka waktu angka itu tidak punya
                                    arti.
                                </p>
                            )}

                            <InputError
                                message={form.errors.target_date}
                                className="mt-2"
                            />
                        </div>
                    </div>

                    {/* ── Asumsi perhitungan ───────────────────────────── */}
                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-text-primary">
                                Asumsi perhitungan
                            </h2>
                            <p className="mt-1 text-xs leading-relaxed text-text-muted">
                                Keduanya mulai dari nol — hasilnya murni target
                                dibagi jangka waktu, tanpa menganggap dana Anda
                                bertumbuh. Isi bila Anda ingin memperhitungkan
                                imbal hasil dan inflasi.
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
                                {/*
                                    TIDAK menyebutkan angka acuan apa pun.
                                    Versi sebelumnya menulis "sekitar 4% untuk
                                    deposito, 7% campuran obligasi" — angka yang
                                    tidak pernah diverifikasi ke sumber mana pun
                                    dan tidak muncul di dokumen mana pun. PRD D-7
                                    mensyaratkan angka return/inflasi disimpan
                                    beserta rates_as_of dan rates_source, sebab
                                    ia langsung membentuk setoran bulanan yang
                                    dijanjikan aplikasi ke pengguna.

                                    Angka acuan baru boleh muncul di sini setelah
                                    mesin rekomendasi instrumen (Rilis 2) memasok
                                    nilai bersumber — LPS untuk deposito, DJPPR
                                    untuk SBN, BPS untuk inflasi. Sampai saat itu,
                                    tidak menyebutkan angka lebih jujur daripada
                                    menyebutkan angka karangan.
                                */}
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Biarkan 0 bila dananya hanya ditabung tanpa
                                    diinvestasikan. Isi sesuai imbal hasil
                                    instrumen yang Anda pakai.
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
                                    saat tanggal target tiba. Biarkan 0 bila
                                    target Anda sudah dalam nilai nominal.
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

                    {/* ── Hasil perhitungan ────────────────────────────── */}
                    {hasil && (
                        <div className="rounded-card border border-lime-500/40 bg-lime-softBg p-5">
                            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                Yang perlu Anda sisihkan
                            </p>

                            <p className="num-tabular mt-1.5 text-3xl font-bold text-lime-500">
                                {formatRupiah(hasil.monthly_contribution_required)}
                                <span className="ml-1.5 text-base font-medium text-text-secondary">
                                    / bulan
                                </span>
                            </p>

                            <p className="mt-1 text-xs text-text-muted">
                                Selama {formatDuration(bulanEfektif)}.
                            </p>

                            <dl className="mt-4 grid grid-cols-1 gap-x-6 gap-y-2 border-t border-lime-500/20 pt-4 text-sm sm:grid-cols-2">
                                <Baris
                                    istilah="Total disetor"
                                    nilai={formatRupiah(hasil.total_contribution_projection)}
                                />
                                <Baris
                                    istilah="Hasil investasi"
                                    nilai={formatRupiah(hasil.total_investment_growth_projection)}
                                />
                                <Baris
                                    istilah="Target setelah inflasi"
                                    nilai={formatRupiah(hasil.future_value_target)}
                                />
                                <Baris
                                    istilah="Proyeksi akhir"
                                    nilai={formatRupiah(hasil.future_value_projection)}
                                    tebal
                                />
                            </dl>

                            {/*
                                Inflasi tidak terlihat bekerja tanpa kalimat ini.
                                Nominal target yang tersimpan tetap nilai HARI INI
                                — itu angka yang pengguna maksud dan pahami — jadi
                                tanpa penjelasan, mengisi kolom inflasi terasa
                                tidak berpengaruh apa-apa.
                            */}
                            {Number(form.data.estimated_inflation_rate) > 0 && (
                                <p className="mt-3 text-xs leading-relaxed text-text-secondary">
                                    Target Anda{" "}
                                    {formatRupiah(form.data.target_amount)} dalam
                                    nilai hari ini. Dengan inflasi{" "}
                                    {form.data.estimated_inflation_rate}% per
                                    tahun, daya beli setara itu membutuhkan{" "}
                                    {formatRupiah(hasil.future_value_target)} saat
                                    tanggal target tiba — dan angka itulah yang
                                    dikejar perhitungan di atas.
                                </p>
                            )}

                            {hasil.already_achieved && (
                                <p className="mt-3 text-xs leading-relaxed text-state-success">
                                    Dana awal Anda sudah cukup untuk mencapai
                                    target ini tanpa setoran tambahan.
                                </p>
                            )}
                        </div>
                    )}
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

function Baris({ istilah, nilai, tebal = false }) {
    return (
        <div className="flex items-baseline justify-between gap-3">
            <dt className="text-text-secondary">{istilah}</dt>
            <dd
                className={
                    "num-tabular " +
                    (tebal ? "font-bold text-text-primary" : "text-text-primary")
                }
            >
                {nilai}
            </dd>
        </div>
    );
}