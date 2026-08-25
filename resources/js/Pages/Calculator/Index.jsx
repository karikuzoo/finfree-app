import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

/**
 * Kalkulator utilitas — dapat diakses tanpa login (PRD FR-44).
 *
 * Halaman ini masih kerangka. Mesin hitungnya sudah ada dan teruji
 * (App\Services\GoalCalculatorService, 19 test terhadap
 * docs/fixtures/calculator-cases.json), tinggal disambungkan ke controller
 * dan form. Yang ditampilkan di bawah adalah daftar kalkulator yang akan
 * tersedia, bukan janji kosong tanpa dasar.
 */
export default function CalculatorIndex() {
    const calculators = [
        {
            name: 'Tujuan Finansial',
            desc: 'Berapa yang harus disisihkan tiap bulan agar target tercapai pada tanggal tertentu.',
            rilis: 'Rilis 1',
        },
        {
            name: 'Pinjaman / KPR',
            desc: 'Angsuran bulanan, total bunga, dan grafik amortisasi dari pokok pinjaman dan tenor.',
            rilis: 'Rilis 2',
        },
        {
            name: 'Investasi',
            desc: 'Proyeksi nilai akhir dari setoran rutin — kebalikan dari kalkulator tujuan.',
            rilis: 'Rilis 2',
        },
    ];

    return (
        <PublicLayout>
            <Head title="Kalkulator" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <span className="inline-block rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold uppercase tracking-wider text-lime-500">
                    Gratis, tanpa daftar
                </span>

                <h1 className="mt-5 text-3xl font-bold tracking-tight text-text-primary">
                    Kalkulator
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Hitung dulu, daftar belakangan. Kalkulator di sini bisa
                    dipakai siapa saja tanpa akun — akun hanya dibutuhkan bila
                    Anda ingin menyimpan hasilnya sebagai tujuan dan memantau
                    progresnya tiap bulan.
                </p>

                <div className="mt-10 space-y-3">
                    {calculators.map((c) => (
                        <div
                            key={c.name}
                            className="flex flex-wrap items-start justify-between gap-3 rounded-card border border-border bg-bg-card p-5"
                        >
                            <div className="max-w-lg">
                                <h2 className="text-base font-semibold text-text-primary">
                                    {c.name}
                                </h2>
                                <p className="mt-1.5 text-sm leading-relaxed text-text-secondary">
                                    {c.desc}
                                </p>
                            </div>
                            <span className="shrink-0 rounded-full border border-border-strong px-3 py-1 text-xs font-medium text-text-muted">
                                {c.rilis}
                            </span>
                        </div>
                    ))}
                </div>

                <div className="mt-10 rounded-card border-l-2 border-lime-500 bg-bg-cardAlt p-5">
                    <h2 className="text-sm font-semibold text-text-primary">
                        Sedang dibangun
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Mesin perhitungannya sudah selesai dan teruji — rumus
                        anuitas efektif dengan penyesuaian inflasi, lengkap
                        dengan penanganan kasus batas. Yang belum ada baru
                        formulir dan tampilan hasilnya.
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
