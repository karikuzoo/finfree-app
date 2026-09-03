import CurrencyInput from "@/Components/CurrencyInput";
import DateInput from "@/Components/DateInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { calculateMonthlyContribution } from "@/utils/goalCalculator";
import { formatRupiah } from "@/utils/format";
import { todayInJakarta } from "@/utils/timezone";
import { Head, Link, useForm } from "@inertiajs/react";

/** Selisih bulan dari hari ini ke sebuah tanggal, dibulatkan ke atas. */
const bulanSampai = (iso) => {
    if (!iso) return null;

    const [t, b, d] = iso.split("-").map(Number);
    const [ct, cb, cd] = todayInJakarta().split("-").map(Number);
    const bulan = (t - ct) * 12 + (b - cb) + (d >= cd ? 0 : -1);

    return Math.max(1, bulan);
};

/**
 * Ubah tujuan finansial.
 *
 * Berbeda dari form buat tujuan, halaman ini memakai PEMILIH TANGGAL langsung,
 * bukan tiga mode (jangka waktu / setoran harian / tanpa tenggat).
 *
 * Alasannya: saat membuat, pengguna belum punya tanggal dan justru sedang
 * mencarinya — menurunkannya dari jangka waktu atau kemampuan menabung adalah
 * bantuan yang nyata. Saat mengubah, tanggalnya sudah ada dan yang ia
 * inginkan biasanya menggesernya sedikit. Memaksa ia menghitung ulang lewat
 * "berapa bulan dari sekarang" justru menyembunyikan tanggal yang sedang ia
 * sunting.
 *
 * Mengubah tujuan TIDAK menyentuh setoran yang sudah tercatat. Yang berubah
 * hanya rencananya; riwayatnya tetap utuh.
 */
export default function GoalEdit({ goal, currentAmount }) {
    const form = useForm({
        name: goal.name,
        target_amount: goal.target_amount,
        initial_amount: goal.initial_amount,
        target_date: goal.target_date ?? "",
        estimated_return_rate: goal.estimated_return_rate,
        estimated_inflation_rate: goal.estimated_inflation_rate,
    });

    const bulan = bulanSampai(form.data.target_date);

    const hasil = bulan
        ? calculateMonthlyContribution({
              targetAmount: Number(form.data.target_amount) || 0,
              currentAmount: Number(form.data.initial_amount) || 0,
              months: bulan,
              annualReturnRate: Number(form.data.estimated_return_rate) || 0,
              annualInflationRate:
                  Number(form.data.estimated_inflation_rate) || 0,
          })
        : null;

    // Menurunkan target di bawah dana yang sudah terkumpul membuat tujuan
    // langsung tercapai. Diperingatkan, bukan dilarang — mengecilkan target
    // memang wajar bila rencananya berubah.
    const targetDiBawahTerkumpul =
        Number(form.data.target_amount) > 0 &&
        Number(form.data.target_amount) < currentAmount;

    const submit = (e) => {
        e.preventDefault();
        form.patch(route("goals.update", goal.id));
    };

    return (
        <AuthenticatedLayout>
            <Head title={`Ubah ${goal.name}`} />

            <div className="mx-auto max-w-2xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="mb-8">
                    <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                        Ubah tujuan
                    </h1>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Setoran yang sudah tercatat tidak ikut berubah — yang
                        disesuaikan hanya rencananya.
                    </p>
                </div>

                <form onSubmit={submit} className="space-y-6">
                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <InputLabel htmlFor="name" value="Nama tujuan" />
                            <TextInput
                                id="name"
                                className="mt-1.5 block w-full"
                                value={form.data.name}
                                onChange={(e) => form.setData("name", e.target.value)}
                                maxLength={100}
                            />
                            <InputError message={form.errors.name} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="target_amount" value="Nominal target" />
                            <CurrencyInput
                                id="target_amount"
                                className="mt-1.5"
                                value={form.data.target_amount}
                                onChange={(v) => form.setData("target_amount", v)}
                            />
                            {targetDiBawahTerkumpul && (
                                <p className="mt-1.5 text-xs leading-relaxed text-state-warning">
                                    Target ini lebih kecil daripada dana yang
                                    sudah terkumpul ({formatRupiah(currentAmount)}
                                    ). Tujuannya akan langsung terhitung tercapai.
                                </p>
                            )}
                            <InputError message={form.errors.target_amount} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="initial_amount" value="Dana awal" />
                            <CurrencyInput
                                id="initial_amount"
                                className="mt-1.5"
                                value={form.data.initial_amount}
                                onChange={(v) => form.setData("initial_amount", v)}
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Uang yang sudah ada sebelum Anda mulai mencatat
                                setoran. Bukan total terkumpul —{" "}
                                {formatRupiah(currentAmount)} sudah termasuk
                                seluruh setoran Anda.
                            </p>
                            <InputError message={form.errors.initial_amount} className="mt-2" />
                        </div>

                        <div>
                            <InputLabel htmlFor="target_date" value="Tanggal target" />
                            <DateInput
                                id="target_date"
                                className="mt-1.5"
                                min={todayInJakarta()}
                                placeholder="Tanpa tenggat"
                                value={form.data.target_date}
                                onChange={(v) => form.setData("target_date", v)}
                            />
                            <p className="mt-1.5 text-xs text-text-muted">
                                Kosongkan bila tujuan ini dikumpulkan
                                terus-menerus tanpa tenggat.
                            </p>
                            <InputError message={form.errors.target_date} className="mt-2" />
                        </div>
                    </div>

                    <div className="space-y-5 rounded-card border border-border bg-bg-card p-5">
                        <div>
                            <h2 className="text-sm font-semibold text-text-primary">
                                Asumsi perhitungan
                            </h2>
                            <p className="mt-1 text-xs leading-relaxed text-text-muted">
                                Mengubahnya menghitung ulang rencana setoran.
                                Riwayat perhitungan sebelumnya tetap tersimpan.
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
                                    className="num-tabular mt-1.5 block w-full"
                                    value={form.data.estimated_return_rate}
                                    onChange={(e) =>
                                        form.setData("estimated_return_rate", e.target.value)
                                    }
                                />
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
                                    className="num-tabular mt-1.5 block w-full"
                                    value={form.data.estimated_inflation_rate}
                                    onChange={(e) =>
                                        form.setData("estimated_inflation_rate", e.target.value)
                                    }
                                />
                                <InputError
                                    message={form.errors.estimated_inflation_rate}
                                    className="mt-2"
                                />
                            </div>
                        </div>
                    </div>

                    {/*
                        Saat target turun di bawah dana yang sudah terkumpul,
                        rencana setoran kehilangan maknanya — tujuannya sudah
                        tercapai.

                        Angkanya sendiri tidak salah: perhitungan memakai DANA
                        AWAL, bukan total terkumpul, dan server menghitungnya
                        persis sama. Tetapi menampilkan "sisihkan sekian per
                        bulan" tepat di bawah peringatan "sudah tercapai"
                        membuat dua kalimat yang saling bertentangan di layar
                        yang sama.
                    */}
                    {targetDiBawahTerkumpul ? (
                        <div className="rounded-card border border-state-success/40 bg-bg-cardAlt p-5">
                            <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                Rencana setoran setelah diubah
                            </p>
                            <p className="mt-1.5 text-lg font-bold text-state-success">
                                Sudah tercapai
                            </p>
                            <p className="mt-1 text-xs leading-relaxed text-text-muted">
                                Dana yang terkumpul ({formatRupiah(currentAmount)})
                                sudah melampaui target ini, jadi tidak ada
                                setoran yang perlu direncanakan.
                            </p>
                        </div>
                    ) : (
                        hasil && (
                            <div className="rounded-card border border-lime-500/40 bg-lime-softBg p-5">
                                <p className="text-xs font-semibold uppercase tracking-wide text-text-secondary">
                                    Rencana setoran setelah diubah
                                </p>
                                <p className="num-tabular mt-1.5 text-3xl font-bold text-lime-500">
                                    {formatRupiah(hasil.monthly_contribution_required)}
                                    <span className="ml-1.5 text-base font-medium text-text-secondary">
                                        / bulan
                                    </span>
                                </p>
                                <p className="mt-1 text-xs text-text-muted">
                                    Dihitung dari sisa waktu sampai tanggal
                                    target, bukan dari jangka waktu aslinya.
                                </p>
                            </div>
                        )
                    )}

                    <div className="flex flex-wrap items-center gap-3">
                        <PrimaryButton disabled={form.processing}>
                            {form.processing ? "Menyimpan…" : "Simpan Perubahan"}
                        </PrimaryButton>

                        <Link href={route("goals.index")}>
                            <SecondaryButton type="button">Batal</SecondaryButton>
                        </Link>
                    </div>
                </form>
            </div>
        </AuthenticatedLayout>
    );
}
