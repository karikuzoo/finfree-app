import ApplicationLogo from "@/Components/ApplicationLogo";
import Avatar from "@/Components/Avatar";
import Dropdown from "@/Components/Dropdown";
import { Link, usePage } from "@inertiajs/react";
import { useState } from "react";

/**
 * Shell halaman publik — dipakai Beranda, Kalkulator, dan Berita.
 *
 * Ketiganya bisa diakses tanpa login (PRD FR-44), jadi headernya
 * menawarkan Masuk/Daftar untuk tamu. Begitu pengguna sudah login:
 * - menu pertama otomatis berubah dari "Beranda" menjadi "Dashboard"
 *   (mengarah ke /dashboard), jadi tidak perlu tombol Dashboard
 *   terpisah di sisi kanan.
 * - sisi kanan menampilkan dropdown akun (Profil, Keluar) yang sama
 *   seperti di AuthenticatedLayout.
 *
 * `match` tiap item memakai pola wildcard Ziggy (`route().current()`
 * menerima "calculator.*"), bukan nama route persis — supaya menu
 * "Kalkulator" tetap menyala di /kalkulator/tujuan (route name-nya
 * `calculator.goal`, beda dari link-nya sendiri `calculator.index`).
 */
export default function PublicLayout({ children }) {
    const user = usePage().props.auth?.user;
    const [openMobileNav, setOpenMobileNav] = useState(false);

    const menu = user
        ? [
              { label: "Dashboard", route: "dashboard", match: "dashboard" },
              {
                  label: "Kalkulator",
                  route: "calculator.index",
                  match: "calculator.*",
              },
              { label: "Berita", route: "news.index", match: "news.*" },
          ]
        : [
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
                        {user ? (
                            <div className="hidden md:block">
                                <Dropdown>
                                    <Dropdown.Trigger>
                                        <span className="inline-flex rounded-lg">
                                            <button
                                                type="button"
                                                className="inline-flex items-center gap-2.5 rounded-lg border border-transparent px-3 py-2 text-sm font-medium leading-4 text-text-secondary transition duration-150 ease-in-out hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none"
                                            >
                                                <Avatar user={user} size={28} />
                                                {user.name}

                                                <svg
                                                    className="-me-0.5 ms-2 h-4 w-4"
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
                                        </span>
                                    </Dropdown.Trigger>

                                    <Dropdown.Content>
                                        <Dropdown.Link
                                            href={route("profile.edit")}
                                        >
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
                        ) : (
                            <>
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
                            </>
                        )}

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

                        {user ? (
                            <div className="mt-2 border-t border-border pt-2">
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
                                    className={"block " + linkClass(false)}
                                >
                                    Profil
                                </Link>
                                <Link
                                    href={route("logout")}
                                    method="post"
                                    as="button"
                                    className={
                                        "block w-full text-left " +
                                        linkClass(false)
                                    }
                                >
                                    Keluar
                                </Link>
                            </div>
                        ) : (
                            <Link
                                href={route("login")}
                                className={
                                    "block sm:hidden " + linkClass(false)
                                }
                            >
                                Masuk
                            </Link>
                        )}
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
