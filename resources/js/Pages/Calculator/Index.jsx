import { TautanRiwayat } from '@/Components/CalculatorFrame';
import PublicLayout from '@/Layouts/PublicLayout';
import { Head, Link } from '@inertiajs/react';

/**
 * Kalkulator utilitas — dapat diakses tanpa login (PRD FR-44).
 *
 * Ketiganya memakai satu mesin hitung, GoalCalculatorService (CLAUDE.md
 * §6.8). Kartu tanpa `href` tampil sebagai kartu biasa berlabel `rilis` —
 * kini tidak ada lagi, tetapi cabangnya dipertahankan untuk kalkulator
 * berikutnya (mis. PPh 21, FR-46).
 */
export default function CalculatorIndex() {
    const calculators = [
        {
            name: 'Tujuan Finansial',
            desc: 'Berapa yang harus disisihkan tiap bulan agar target tercapai pada tanggal tertentu.',
            href: route('calculator.goal'),
        },
        {
            name: 'Pinjaman / KPR',
            desc: 'Angsuran bulanan, total bunga, dan grafik amortisasi dari pokok pinjaman dan tenor.',
            href: route('calculator.loan'),
        },
        {
            name: 'Investasi',
            desc: 'Proyeksi nilai akhir dari setoran rutin — kebalikan dari kalkulator tujuan.',
            href: route('calculator.investment'),
        },
    ];

    return (
        <PublicLayout>
            <Head title="Kalkulator" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <span className="inline-block rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold uppercase tracking-wider text-lime-500">
                        Gratis, tanpa daftar
                    </span>
                    <TautanRiwayat />
                </div>

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
                    {calculators.map((c) => {
                        const body = (
                            <>
                                <div className="max-w-lg">
                                    <h2 className="text-base font-semibold text-text-primary">
                                        {c.name}
                                    </h2>
                                    <p className="mt-1.5 text-sm leading-relaxed text-text-secondary">
                                        {c.desc}
                                    </p>
                                </div>
                                {c.href ? (
                                    <span className="shrink-0 rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold text-lime-500">
                                        Buka →
                                    </span>
                                ) : (
                                    <span className="shrink-0 rounded-full border border-border-strong px-3 py-1 text-xs font-medium text-text-muted">
                                        {c.rilis}
                                    </span>
                                )}
                            </>
                        );

                        const shell =
                            'flex flex-wrap items-start justify-between gap-3 rounded-card border p-5 transition ';

                        // Kalkulator yang sudah jadi bisa diklik; yang belum
                        // tampil sebagai kartu biasa dengan penanda rilisnya.
                        return c.href ? (
                            <Link
                                key={c.name}
                                href={c.href}
                                className={
                                    shell +
                                    'border-border-strong bg-bg-card hover:bg-bg-cardAlt focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base'
                                }
                            >
                                {body}
                            </Link>
                        ) : (
                            <div
                                key={c.name}
                                className={shell + 'border-border bg-bg-card'}
                            >
                                {body}
                            </div>
                        );
                    })}
                </div>

                <div className="mt-10 rounded-card border-l-2 border-lime-500 bg-bg-cardAlt p-5">
                    <h2 className="text-sm font-semibold text-text-primary">
                        Cara kami menghitung
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Anuitas efektif dengan setoran di akhir bulan. Imbal
                        hasil tahunan dikonversi menjadi bulanan secara majemuk,
                        bukan sekadar dibagi dua belas — selisih keduanya
                        membesar untuk jangka panjang. Inflasi menaikkan nominal
                        target, bukan mengurangi imbal hasil, sehingga tidak ada
                        perhitungan ganda. Satu pengecualian: bunga pinjaman
                        dibagi dua belas seperti di bank, karena itu angka
                        kontrak — supaya angsurannya cocok dengan simulasi bank.
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
