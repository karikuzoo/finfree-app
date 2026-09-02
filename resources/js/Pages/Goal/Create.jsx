import CurrencyInput from "@/Components/CurrencyInput";
import DateInput from "@/Components/DateInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link, useForm } from "@inertiajs/react";
import { nowInJakartaParts } from "@/utils/timezone";

/** Batas bawah & atas tanggal target, menyalin aturan di StoreGoalRequest. */
const geserHari = (jumlahHari) => {
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const d = new Date(tahun, bulan, tanggal + jumlahHari);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};

const besok = () => geserHari(1);

const enamPuluhTahunLagi = () => {
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const d = new Date(tahun + 60, bulan, tanggal - 1);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, "0")}-${String(d.getDate()).padStart(2, "0")}`;
};
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
            // Tanggal dikosongkan saat berpindah ke jenis tanpa tenggat,
            // supaya tidak ada nilai tersembunyi yang ikut terkirim.
            target_date: jenis.requiresDate ? sebelumnya.target_date : "",
        }));
    };

    const submit = (e) => {
        e.preventDefault();
        form.post(route("goals.store"));
    };

    return (
        <AuthenticatedLayout>
            <Head title="Buat Tujuan" />

            <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                        {isFirstGoal ? "Buat tujuan pertama Anda" : "Buat tujuan baru"}
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

                        <InputError message={form.errors.type} className="mt-2" />
                    </div>

                    {/* ── Rincian tujuan ───────────────────────────────── */}
                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <InputLabel htmlFor="name" value="Nama tujuan" />
                            <TextInput
                                id="name"
                                className="mt-1.5 block w-full"
                                value={form.data.name}
                                onChange={(e) => form.setData("name", e.target.value)}
                                placeholder="DP Rumah Pertama"
                                maxLength={100}
                            />
                            <InputError message={form.errors.name} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="target_amount" value="Nominal target" />
                            <CurrencyInput
                                id="target_amount"
                                className="mt-1.5"
                                placeholder="500.000.000"
                                value={form.data.target_amount}
                                onChange={(v) => form.setData("target_amount", v)}
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Dalam nilai uang hari ini. Pengaruh inflasi
                                dihitung terpisah di bawah.
                            </p>
                            <InputError message={form.errors.target_amount} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="initial_amount" value="Dana awal (opsional)" />
                            <CurrencyInput
                                id="initial_amount"
                                className="mt-1.5"
                                placeholder="0"
                                value={form.data.initial_amount}
                                onChange={(v) => form.setData("initial_amount", v)}
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Uang yang sudah Anda sisihkan untuk tujuan ini.
                            </p>
                            <InputError message={form.errors.initial_amount} className="mt-2" />
                        </div>

                        {perluTanggal && (
                            <div>
                                <InputLabel htmlFor="target_date" value="Tanggal target" />
                                {/* Batasnya menyalin StoreGoalRequest: tanggal
                                    target harus setelah hari ini, dan paling
                                    jauh 60 tahun ke depan. Tanggal yang mustahil
                                    lebih baik tidak bisa diklik sama sekali
                                    daripada baru ditolak setelah tombol simpan
                                    ditekan. */}
                                <DateInput
                                    id="target_date"
                                    className="mt-1.5"
                                    min={besok()}
                                    max={enamPuluhTahunLagi()}
                                    value={form.data.target_date}
                                    onChange={(v) => form.setData("target_date", v)}
                                />
                                <InputError message={form.errors.target_date} className="mt-2" />
                            </div>
                        )}

                        {jenisTerpilih && ! perluTanggal && (
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
                                        form.setData("estimated_return_rate", e.target.value)
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
                                        form.setData("estimated_inflation_rate", e.target.value)
                                    }
                                />
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Menaikkan target agar nilainya tetap setara
                                    saat tanggal target tiba.
                                </p>
                                <InputError
                                    message={form.errors.estimated_inflation_rate}
                                    className="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton disabled={form.processing}>
                            {form.processing ? "Menyimpan…" : "Simpan Tujuan"}
                        </PrimaryButton>

                        <Link href={route("dashboard")}>
                            <SecondaryButton type="button">Batal</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
