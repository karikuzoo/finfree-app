import ApplicationLogo from "@/Components/ApplicationLogo";
import Avatar from "@/Components/Avatar";
import Dropdown from "@/Components/Dropdown";
import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";

const menu = [
    { label: "Dashboard", route: "dashboard", match: "dashboard" },
    { label: "Kalkulator", route: "calculator.index", match: "calculator.*" },
    { label: "Berita", route: "news.index", match: "news.*" },
];

function sidebarLinkClass(isActive) {
    return (
        "flex items-center rounded-lg px-3 py-2 text-sm font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
        (isActive
            ? "bg-lime-softBg text-lime-500"
            : "text-text-secondary hover:bg-bg-cardAlt hover:text-text-primary")
    );
}

/**
 * Shell aplikasi untuk pengguna yang sudah login — SIDEBAR kiri, bukan
 * navbar atas seperti sebelumnya. PublicLayout (dipakai Welcome/
 * Kalkulator/Berita) mendelegasikan ke komponen ini begitu ada user
 * yang login, jadi sidebar ini satu-satunya tempat markup navigasi
 * "sudah login" didefinisikan — Kalkulator dan Berita otomatis ikut
 * memakai sidebar yang sama, bukan salinan terpisah.
 *
 * Di layar sempit (< md), sidebar disembunyikan dan diganti topbar tipis
 * + drawer yang dibuka lewat tombol hamburger — sidebar selebar penuh
 * butuh terlalu banyak ruang di layar HP.
 */
export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);

    const navLinks = (onNavigate) =>
        menu.map((item) => (
            <Link
                key={item.route}
                href={route(item.route)}
                onClick={onNavigate}
                className={sidebarLinkClass(route().current(item.match))}
            >
                {item.label}
            </Link>
        ));

    return (
        <div className="flex min-h-screen bg-bg-base">
            {/* Sidebar — desktop */}
            <aside className="hidden md:flex md:w-64 md:shrink-0 md:flex-col md:border-r md:border-border md:bg-bg-surface">
                <Link
                    href="/"
                    className="flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-6 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    <ApplicationLogo className="h-8 w-8 text-lime-500" />
                    <span className="text-lg font-bold tracking-tight text-text-primary">
                        FinGoal
                    </span>
                </Link>

                <nav className="flex-1 space-y-1 px-3 py-4">{navLinks()}</nav>

                <div className="border-t border-border p-3">
                    <Dropdown>
                        <Dropdown.Trigger>
                            <button
                                type="button"
                                className="flex w-full items-center gap-2.5 rounded-lg px-2 py-2 text-left text-sm font-medium text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none"
                            >
                                <Avatar user={user} size={32} />
                                <span className="min-w-0 flex-1 truncate">
                                    {user.name}
                                </span>
                                <svg
                                    className="h-4 w-4 shrink-0"
                                    xmlns="http://www.w3.org/2000/svg"
                                    viewBox="0 0 20 20"
                                    fill="currentColor"
                                >
                                    <path
                                        fillRule="evenodd"
                                        d="M5.293 7.293a1 1 0 011.414 0L10 10.586l3.293-3.293a1 1 0 111.414 1.414l-4 4a1 1 0 01-1.414 0l-4-4a1 1 0 010-1.414z"
                                        clipRule="evenodd"
                                    />
                                </svg>
                            </button>
                        </Dropdown.Trigger>

                        <Dropdown.Content align="left">
                            <Dropdown.Link href={route("profile.edit")}>
                                Profil
                            </Dropdown.Link>
                            <Dropdown.Link
                                href={route("logout")}
                                method="post"
                                as="button"
                            >
                                Keluar
                            </Dropdown.Link>
                        </Dropdown.Content>
                    </Dropdown>
                </div>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                {/* Topbar tipis — mobile saja, cuma logo + tombol drawer */}
                <div className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-bg-surface px-4 md:hidden">
                    <Link
                        href="/"
                        className="flex items-center gap-2.5 focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        <ApplicationLogo className="h-8 w-8 text-lime-500" />
                        <span className="text-lg font-bold tracking-tight text-text-primary">
                            FinGoal
                        </span>
                    </Link>

                    <button
                        type="button"
                        onClick={() => setMobileOpen((v) => !v)}
                        aria-label="Buka menu navigasi"
                        aria-expanded={mobileOpen}
                        className="rounded-lg p-2 text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        <svg
                            className="h-6 w-6"
                            stroke="currentColor"
                            fill="none"
                            viewBox="0 0 24 24"
                        >
                            {mobileOpen ? (
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M6 18L18 6M6 6l12 12"
                                />
                            ) : (
                                <path
                                    strokeLinecap="round"
                                    strokeLinejoin="round"
                                    strokeWidth="2"
                                    d="M4 6h16M4 12h16M4 18h16"
                                />
                            )}
                        </svg>
                    </button>
                </div>

                {/* Drawer — mobile saja */}
                {mobileOpen && (
                    <div className="border-b border-border bg-bg-surface px-3 py-3 md:hidden">
                        <div className="space-y-1">
                            {navLinks(() => setMobileOpen(false))}
                        </div>

                        <div className="mt-3 border-t border-border pt-3">
                            <div className="flex items-center gap-3 px-3 py-2">
                                <Avatar user={user} size={36} />
                                <div className="min-w-0">
                                    <div className="truncate text-sm font-medium text-text-primary">
                                        {user.name}
                                    </div>
                                    <div className="truncate text-xs text-text-muted">
                                        {user.email}
                                    </div>
                                </div>
                            </div>

                            <Link
                                href={route("profile.edit")}
                                onClick={() => setMobileOpen(false)}
                                className={"block " + sidebarLinkClass(false)}
                            >
                                Profil
                            </Link>
                            <Link
                                href={route("logout")}
                                method="post"
                                as="button"
                                className={
                                    "block w-full text-left " +
                                    sidebarLinkClass(false)
                                }
                            >
                                Keluar
                            </Link>
                        </div>
                    </div>
                )}

                {header && (
                    <header className="border-b border-border bg-bg-surface">
                        <div className="px-4 py-6 sm:px-6 lg:px-8">
                            {header}
                        </div>
                    </header>
                )}

                <main className="flex-1">{children}</main>
            </div>
        </div>
    );
}
