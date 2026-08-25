import PublicLayout from '@/Layouts/PublicLayout';
import { Head } from '@inertiajs/react';

/**
 * Berita finansial. Masih kerangka — modul ini dijadwalkan di Rilis 3
 * (PRD §12) dan punya gerbang mulai: kualitas hasil pencarian sumber berita
 * diuji lebih dulu dengan kata kunci nyata. Bila cakupannya kurang, sumbernya
 * diganti atau modulnya dicoret — jangan dipaksakan.
 *
 * Kategori di bawah adalah FR-17. Perlu diingat kategori itu tidak datang
 * dari sumber; ia diklasifikasi sendiri saat ingest (FR-28).
 */
export default function NewsIndex() {
    const categories = [
        'Kebijakan Moneter',
        'Pasar Saham',
        'Properti',
        'Investasi',
        'Tips Keuangan',
    ];

    return (
        <PublicLayout>
            <Head title="Berita" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold tracking-tight text-text-primary">
                    Berita &amp; Analisis Keuangan
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Perkembangan ekonomi Indonesia dan dunia — kebijakan
                    moneter, pasar saham, properti, dan investasi — dihimpun
                    dari media pemberitaan sebagai konteks saat Anda mengambil
                    keputusan finansial.
                </p>

                <div className="mt-8 flex flex-wrap gap-2">
                    {categories.map((c) => (
                        <span
                            key={c}
                            className="rounded-full border border-border px-3 py-1.5 text-xs font-medium text-text-muted"
                        >
                            {c}
                        </span>
                    ))}
                </div>

                <div className="mt-10 rounded-card border border-border bg-bg-card px-6 py-14 text-center">
                    <svg
                        className="mx-auto h-14 w-14 text-text-muted"
                        viewBox="0 0 32 32"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <rect x="4" y="6" width="24" height="20" rx="2.5" />
                        <path d="M9 12h8M9 17h14M9 21h10" />
                    </svg>

                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Belum ada berita
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Modul berita dijadwalkan pada Rilis 3, setelah fitur
                        tujuan dan kalkulator selesai.
                    </p>
                    <p className="mx-auto mt-3 max-w-md text-xs leading-relaxed text-text-muted">
                        Sumber beritanya belum dikunci. Penyedia yang
                        direncanakan adalah API berita umum, sehingga cakupan
                        berita ekonomi berbahasa Indonesia perlu diuji dulu
                        sebelum modul ini dianggap layak rilis.
                    </p>
                </div>
            </div>
        </PublicLayout>
    );
}
