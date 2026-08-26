import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="relative flex min-h-screen flex-col items-center bg-bg-base px-4 pb-10 pt-20 sm:justify-center sm:pt-24">
            {/*
                Mengarah langsung ke beranda, bukan history.back(). Riwayat
                browser bisa berisi apa saja — halaman error, atau bahkan situs
                lain kalau pengguna tiba dari tautan luar. Tujuan yang pasti
                lebih baik daripada tujuan yang tergantung riwayat.
            */}
            <Link
                href={route('home')}
                className="group absolute left-4 top-5 inline-flex items-center gap-2.5 rounded-full border border-border-strong bg-bg-card py-1.5 pl-1.5 pr-4 text-sm font-medium text-text-secondary transition duration-200 ease-out hover:border-lime-500 hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base sm:left-6 sm:top-6"
            >
                {/*
                    Lingkaran ikon berubah lime hanya saat hover. DESIGN.md §2.5
                    membatasi satu elemen berisi lime penuh per layar, dan jatah
                    itu milik tombol utama di dalam form — jadi di sini lime
                    hanya muncul sesaat sebagai tanda "ini bisa diklik".
                */}
                <span className="flex h-7 w-7 items-center justify-center rounded-full bg-bg-cardAlt text-text-secondary transition duration-200 ease-out group-hover:bg-lime-500 group-hover:text-onPrimary">
                    <svg
                        className="h-4 w-4 transition-transform duration-200 ease-out motion-safe:group-hover:-translate-x-0.5"
                        viewBox="0 0 20 20"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M11.5 5 L6.5 10 L11.5 15" />
                    </svg>
                </span>
                Kembali ke beranda
            </Link>

            <Link
                href={route('home')}
                className="flex items-center gap-3 rounded-lg focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
            >
                <ApplicationLogo className="h-9 w-9 text-lime-500" />
                <span className="text-xl font-bold tracking-tight text-text-primary">
                    FinGoal
                </span>
            </Link>

            <div className="mt-6 w-full overflow-hidden rounded-card border border-border bg-bg-card px-6 py-6 sm:max-w-md">
                {children}
            </div>

            <p className="mt-6 max-w-md text-center text-xs text-text-muted">
                Simulasi perencanaan keuangan untuk tujuan pribadi. Bukan
                nasihat investasi.
            </p>
        </div>
    );
}
