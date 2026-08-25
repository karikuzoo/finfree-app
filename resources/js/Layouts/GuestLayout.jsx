import ApplicationLogo from '@/Components/ApplicationLogo';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return (
        <div className="flex min-h-screen flex-col items-center bg-bg-base px-4 pt-10 sm:justify-center sm:pt-0">
            <Link
                href="/"
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
