import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

/**
 * Goal — tempat MENENTUKAN tujuan finansial (dana darurat, DP rumah,
 * pensiun, dst) sekaligus dapat SARAN harus diapakan dana yang sudah
 * dimiliki sekarang, supaya mendekati target itu.
 *
 * Beda dari Dompet (Wallet/Index.jsx): Dompet menjawab "uang saya ada di
 * mana saja SEKARANG", Goal menjawab "saya mau ke mana, dan uang yang
 * sudah ada sebaiknya diapakan". Dashboard tetap jadi ringkasan gabungan
 * keduanya untuk goal utama.
 *
 * Masih kerangka (page dummy) — CRUD Tujuan (create/edit dari UI) belum
 * dibangun. Mesin saran alokasinya SENDIRI sudah ada
 * (InvestmentAllocationService, dipakai AllocationBreakdownChart di
 * Dashboard) — begitu CRUD-nya jadi, halaman ini tinggal memanggil
 * service yang sama per goal yang dipilih, bukan membangun dari nol.
 */
export default function GoalIndex() {
    const plannedFeatures = [
        "Tentukan tujuan baru: nama, nominal target, dan tenggat waktu",
        "Saran alokasi dana yang sudah dimiliki, berdasar jangka waktu & profil risiko (InvestmentAllocationService)",
        'Simulasi "bagaimana jika": ubah nominal harian, lihat proyeksi tanggal tercapai',
        "Pilih goal mana yang jadi fokus utama di Dashboard",
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Goal" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold tracking-tight text-text-primary">
                    Goal
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Tentukan tujuan finansial Anda, dan dapatkan saran harus
                    diapakan dana yang sudah Anda miliki sekarang supaya lebih
                    dekat ke target itu.
                </p>

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
                        <circle cx="16" cy="16" r="12" />
                        <circle cx="16" cy="16" r="6.5" />
                        <circle
                            cx="16"
                            cy="16"
                            r="1.5"
                            fill="currentColor"
                            stroke="none"
                        />
                    </svg>

                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Belum ada alur "buat tujuan" di sini
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Halaman ini masih kerangka — alur menentukan tujuan baru
                        dari antarmuka belum dibangun. Untuk sekarang, goal yang
                        sudah ada tetap bisa dipantau & diberi saran alokasi
                        lewat kartu di Dashboard.
                    </p>

                    <ul className="mx-auto mt-6 max-w-sm space-y-2 text-left text-xs text-text-muted">
                        {plannedFeatures.map((feature) => (
                            <li key={feature} className="flex gap-2">
                                <span aria-hidden="true">·</span>
                                <span>{feature}</span>
                            </li>
                        ))}
                    </ul>
                </div>
            </div>
        </AuthenticatedLayout>
    );
}
