import ApplicationLogo from "@/Components/ApplicationLogo";
import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";

/**
 * Shell halaman publik — dipakai Beranda, Kalkulator, dan Berita.
 *
 * Ketiganya bisa diakses tanpa login (PRD FR-44). Untuk TAMU, komponen
 * ini merender navbar atas sendiri (Masuk/Daftar).
 *
 * Begitu ada user yang login, komponen ini TIDAK merender navbar atas
 * sama sekali — didelegasikan penuh ke AuthenticatedLayout (sidebar kiri
 * + dropdown akun), supaya Kalkulator dan Berita otomatis konsisten
 * dengan Dashboard, dan markup sidebar cuma didefinisikan sekali di satu
 * tempat (AuthenticatedLayout.jsx), bukan disalin ke sini.
 */
export default function PublicLayout({ children }) {
    const user = usePage().props.auth?.user;

    if (user) {
        return <AuthenticatedLayout>{children}</AuthenticatedLayout>;
    }

    return <GuestTopNav>{children}</GuestTopNav>;
}

function GuestTopNav({ children }) {
    const [openMobileNav, setOpenMobileNav] = useState(false);

    const menu = [
        { label: "Beranda", route: "home", match: "home" },
        {
            label: "Kalkulator",
            route: "calculator.index",
            match: "calculator.*",
        },
        { label: "Berita", route: "news.index", match: "news.*" },
    ];

    const linkClass = (isActive) =>
        "rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-surface " +
        (isActive
            ? "bg-lime-softBg text-lime-500"
            : "text-text-secondary hover:bg-bg-cardAlt hover:text-text-primary");

    return (
        <div className="flex min-h-screen flex-col bg-bg-base">
            <header className="border-b border-border bg-bg-surface">
                <nav className="mx-auto flex max-w-7xl items-center justify-between gap-4 px-4 py-3 sm:px-6 lg:px-8">
                    <Link
                        href={route("home")}
                        className="flex shrink-0 items-center gap-2.5 rounded-lg focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-surface"
                    >
                        <ApplicationLogo className="h-8 w-8 text-lime-500" />
                        <span className="text-lg font-bold tracking-tight text-text-primary">
                            FinGoal
                        </span>
                    </Link>

                    <div className="hidden items-center gap-1 md:flex">
                        {menu.map((item) => (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={linkClass(
                                    route().current(item.match),
                                )}
                                aria-current={
                                    route().current(item.match)
                                        ? "page"
                                        : undefined
                                }
                            >
                                {item.label}
                            </Link>
                        ))}
                    </div>

                    <div className="flex shrink-0 items-center gap-2">
                        <Link
                            href={route("login")}
                            className="hidden rounded-lg px-4 py-2 text-sm font-medium text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 sm:inline-block"
                        >
                            Masuk
                        </Link>
                        <Link
                            href={route("register")}
                            className="rounded-lg bg-lime-500 px-4 py-2 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-surface"
                        >
                            Daftar
                        </Link>

                        <button
                            type="button"
                            onClick={() => setOpenMobileNav((v) => !v)}
                            aria-label="Buka menu"
                            aria-expanded={openMobileNav}
                            className="rounded-lg p-2 text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 md:hidden"
                        >
                            <svg
                                className="h-5 w-5"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinecap="round"
                            >
                                {openMobileNav ? (
                                    <path d="M6 18L18 6M6 6l12 12" />
                                ) : (
                                    <path d="M4 6h16M4 12h16M4 18h16" />
                                )}
                            </svg>
                        </button>
                    </div>
                </nav>

                {openMobileNav && (
                    <div className="border-t border-border px-4 py-2 md:hidden">
                        {menu.map((item) => (
                            <Link
                                key={item.route}
                                href={route(item.route)}
                                className={
                                    "block " +
                                    linkClass(route().current(item.match))
                                }
                            >
                                {item.label}
                            </Link>
                        ))}

                        <Link
                            href={route("login")}
                            className={"block sm:hidden " + linkClass(false)}
                        >
                            Masuk
                        </Link>
                    </div>
                )}
            </header>

            <main className="flex-1">{children}</main>

            <footer className="border-t border-border">
                <div className="mx-auto max-w-7xl px-4 py-6 text-xs text-text-muted sm:px-6 lg:px-8">
                    FinGoal — alat simulasi dan perencanaan. Tidak menjual,
                    membeli, atau menyalurkan produk investasi apa pun.
                </div>
            </footer>
        </div>
    );
}
