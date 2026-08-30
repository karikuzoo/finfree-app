import PublicLayout from "@/Layouts/PublicLayout";
import { Head, Link, usePage } from "@inertiajs/react";

/**
 * Halaman depan. Menggantikan halaman marketing Laravel bawaan Breeze.
 *
 * Angka contoh di kartu hasil sengaja memakai nilai dari test vector
 * (docs/fixtures/calculator-cases.json, kasus "dasar-tanpa-inflasi"):
 * target 500 juta, 10 tahun, return 8% efektif -> Rp 2.775.862 per bulan.
 * Jadi ilustrasinya bukan angka karangan, dan tetap benar bila dicek ulang.
 */
export default function Welcome() {
    const user = usePage().props.auth?.user;

    return (
        <PublicLayout>
            <Head title="Rencanakan tujuan finansial Anda" />

            <div className="mx-auto max-w-7xl px-4 sm:px-6 lg:px-8">
                <section className="grid items-center gap-12 py-16 lg:grid-cols-2 lg:py-24">
                    <div>
                        <span className="inline-block rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold uppercase tracking-wider text-lime-500">
                            Perencanaan keuangan pribadi
                        </span>

                        <h1 className="mt-5 text-4xl font-bold leading-tight tracking-tight text-text-primary sm:text-5xl">
                            Berapa yang harus Anda sisihkan tiap bulan?
                        </h1>

                        <p className="mt-5 max-w-xl text-base leading-relaxed text-text-secondary">
                            Tentukan tujuan finansial Anda — dana pensiun,
                            rumah, kendaraan, dana darurat, pendidikan. FinGoal
                            menghitung setoran bulanannya, menyarankan alokasi
                            instrumen yang masuk akal untuk jangka waktu itu,
                            lalu memantau progresnya.
                        </p>

                        <div className="mt-8 flex flex-wrap gap-3">
                            <Link
                                href={route("calculator.index")}
                                className="rounded-lg bg-lime-500 px-5 py-3 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                            >
                                Coba kalkulatornya — tanpa daftar
                            </Link>
                            <Link
                                href={route("register")}
                                className="rounded-lg border border-border-strong px-5 py-3 text-sm font-semibold text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                            >
                                {"Buat akun"}
                            </Link>
                        </div>

                        <p className="mt-4 text-xs text-text-muted">
                            Kalkulator bisa dipakai tanpa akun. Akun hanya
                            dibutuhkan untuk menyimpan tujuan dan memantau
                            progresnya.
                        </p>
                    </div>

                    <div className="rounded-card border border-border bg-bg-card p-6">
                        <p className="text-xs font-medium uppercase tracking-wider text-text-secondary">
                            Contoh perhitungan
                        </p>

                        <dl className="mt-4 space-y-2 text-sm">
                            <div className="flex justify-between">
                                <dt className="text-text-secondary">Target</dt>
                                <dd className="num-tabular font-medium text-text-primary">
                                    Rp 500.000.000
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-text-secondary">
                                    Jangka waktu
                                </dt>
                                <dd className="num-tabular font-medium text-text-primary">
                                    10 tahun
                                </dd>
                            </div>
                            <div className="flex justify-between">
                                <dt className="text-text-secondary">
                                    Estimasi imbal hasil
                                </dt>
                                <dd className="num-tabular font-medium text-text-primary">
                                    8% / tahun
                                </dd>
                            </div>
                        </dl>

                        <div className="mt-5 rounded-lg border-l-2 border-lime-500 bg-bg-cardAlt p-4">
                            <p className="text-xs font-medium uppercase tracking-wider text-text-secondary">
                                Setoran bulanan
                            </p>
                            <p className="num-tabular mt-1 font-mono text-3xl font-bold text-lime-500">
                                Rp 2.775.862
                            </p>
                        </div>

                        <p className="mt-4 text-xs leading-relaxed text-text-muted">
                            Anuitas efektif, setoran akhir bulan. Estimasi
                            bersifat simulasi, bukan saran investasi personal.
                        </p>
                    </div>
                </section>

                <section className="grid gap-4 border-t border-border py-14 sm:grid-cols-3">
                    {[
                        {
                            title: "Hitung, bukan menebak",
                            body: "Rumus anuitas dengan penyesuaian inflasi, bukan pembagian kasar target dibagi jumlah bulan.",
                        },
                        {
                            title: "Alokasi sesuai jangka waktu",
                            body: "Tujuan dua tahun dan dua puluh tahun tidak layak diisi instrumen yang sama.",
                        },
                        {
                            title: "Pantau realisasinya",
                            body: "Catat setoran tiap bulan dan lihat apakah Anda tertinggal atau di depan rencana.",
                        },
                    ].map((item) => (
                        <div
                            key={item.title}
                            className="rounded-card border border-border bg-bg-card p-5"
                        >
                            <h2 className="text-sm font-semibold text-text-primary">
                                {item.title}
                            </h2>
                            <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                                {item.body}
                            </p>
                        </div>
                    ))}
                </section>
            </div>
        </PublicLayout>
    );
}
