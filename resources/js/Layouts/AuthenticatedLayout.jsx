import ApplicationLogo from "@/Components/ApplicationLogo";
import Avatar from "@/Components/Avatar";
import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";
import {
    DashboardIcon,
    GoalIcon,
    WalletIcon,
    HistoryIcon,
    CalculatorIcon,
    NewsIcon,
    UserIcon,
    LogoutIcon,
} from "@/Components/Icons";

const menu = [
    {
        label: "Dashboard",
        route: "dashboard",
        match: "dashboard",
        icon: DashboardIcon,
    },
    {
        label: "Goal",
        route: "goals.index",
        match: "goals.*",
        icon: GoalIcon,
    },
    {
        label: "Dompet",
        route: "wallet.index",
        match: "wallet.*",
        icon: WalletIcon,
    },
    {
        label: "Riwayat",
        route: "history.index",
        match: "history.*",
        icon: HistoryIcon,
    },
    {
        label: "Kalkulator",
        route: "calculator.index",
        match: "calculator.*",
        icon: CalculatorIcon,
    },
    { label: "Berita", route: "news.index", match: "news.*", icon: NewsIcon },
    {
        label: "Profil",
        route: "profile.edit",
        match: "profile.edit",
        icon: UserIcon,
    },
    { label: "Keluar", route: "logout", match: "logout", icon: LogoutIcon },
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
        menu.map((item) => {
            const Icon = item.icon;

            if (item.route === "logout") {
                return (
                    <Link
                        key={item.route}
                        href={route("logout")}
                        method="post"
                        as="button"
                        onClick={onNavigate}
                        className={
                            "w-full text-left " + sidebarLinkClass(false)
                        }
                    >
                        <Icon className="h-7 w-7 shrink-0" />
                        <span className="ml-3">{item.label}</span>
                    </Link>
                );
            }

            return (
                <Link
                    key={item.route}
                    href={route(item.route)}
                    onClick={onNavigate}
                    className={sidebarLinkClass(route().current(item.match))}
                >
                    <Icon className="h-7 w-7 shrink-0" />
                    <span className="ml-3">{item.label}</span>
                </Link>
            );
        });

    return (
        <div className="flex min-h-screen bg-bg-base">
            {/* Sidebar — desktop */}
            <aside className="hidden md:flex md:w-64 md:shrink-0 md:flex-col md:border-r md:border-border md:bg-bg-surface">
                <Link
                    href={route("dashboard")}
                    className="flex h-16 shrink-0 items-center gap-2.5 border-b border-border px-6 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    <ApplicationLogo className="h-8 w-8 text-lime-500" />
                    <span className="text-lg font-bold tracking-tight text-text-primary">
                        FinGoal
                    </span>
                </Link>

                <div className="space-y-1 border-t border-border p-3">
                    <div className="flex items-center gap-2.5 px-2 py-2">
                        <Avatar user={user} size={32} />
                        <span className="min-w-0 flex-1 truncate text-sm font-medium text-text-primary">
                            {user.name}
                        </span>
                    </div>
                </div>

                <nav className="flex-1 space-y-1 px-3 py-4">{navLinks()}</nav>
            </aside>

            <div className="flex min-w-0 flex-1 flex-col">
                {/* Topbar tipis — mobile saja, cuma logo + tombol drawer */}
                <div className="flex h-16 shrink-0 items-center justify-between border-b border-border bg-bg-surface px-4 md:hidden">
                    <Link
                        href={route("dashboard")}
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
