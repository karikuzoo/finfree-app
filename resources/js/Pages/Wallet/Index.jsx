import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head } from "@inertiajs/react";

/**
 * Dompet — menjawab "uang saya ada di mana saja SEKARANG": rekap total
 * dana per tempat/instrumen (tabungan, deposito, kas, investasi, dst),
 * digabung LINTAS goal — beda dari `current_amount` per goal yang sudah
 * ada di Dashboard (itu progres SATU goal terhadap targetnya).
 *
 * Beda dari Goal (Goal/Index.jsx): Dompet murni potret "sekarang ada
 * berapa & di mana", TIDAK menyarankan apa pun harus diapakan — saran
 * alokasi itu tugas Goal.
 *
 * Masih kerangka (page dummy) — perlu data model tersendiri untuk
 * "tempat penyimpanan dana" (rekening bank, e-wallet, kas, dst) yang
 * belum ada; `current_amount` di data model saat ini terikat ke goal
 * (initial_amount + SUM(contributions)), bukan ke tempat fisik dananya.
 */
export default function WalletIndex() {
    const plannedFeatures = [
        "Catat saldo per rekening/tempat penyimpanan (bank, e-wallet, kas)",
        "Breakdown total dana per instrumen (tabungan, deposito, saham, emas)",
        "Total kekayaan gabungan dari seluruh tempat, lintas semua goal",
        "Sinkron otomatis saat setoran goal dicatat",
    ];

    return (
        <AuthenticatedLayout>
            <Head title="Dompet" />

            <div className="mx-auto max-w-4xl px-4 py-14 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold tracking-tight text-text-primary">
                    Dompet
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Lihat di mana saja uang Anda berada saat ini — rekening
                    bank, e-wallet, kas, sampai instrumen investasi — dalam satu
                    rekap total, digabung dari seluruh tujuan finansial Anda.
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
                        <path d="M6 10a2 2 0 012-2h16a2 2 0 012 2v13a2 2 0 01-2 2H8a2 2 0 01-2-2V10z" />
                        <path d="M6 13h20" />
                        <circle
                            cx="22.5"
                            cy="19"
                            r="1.6"
                            fill="currentColor"
                            stroke="none"
                        />
                    </svg>

                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Belum ada rekap sebaran dana di sini
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Halaman ini masih kerangka — pencatatan "dana ada di
                        mana" per tempat penyimpanan belum dibangun. Untuk
                        sekarang, total dana yang sudah terkumpul tetap bisa
                        dilihat per goal lewat Dashboard.
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
