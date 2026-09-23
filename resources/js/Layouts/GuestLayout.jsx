import ApplicationLogo from '@/Components/ApplicationLogo';
import { GoalIcon } from '@/Components/Icons';
import { Link } from '@inertiajs/react';

export default function GuestLayout({ children }) {
    return <div className="flex min-h-screen flex-col bg-bg-base">
        <header className="flex items-center justify-between gap-4 border-b border-border px-6 py-6 sm:px-12"><Link href={route('home')} className="flex items-center gap-3"><span className="flex h-10 w-10 items-center justify-center rounded-xl bg-lime-500 text-onPrimary"><ApplicationLogo className="h-7 w-7"/></span><span className="text-2xl font-semibold tracking-tight">FinGoal<span className="text-lime-500">.</span></span></Link><Link href={route('home')} className="text-sm text-text-secondary hover:text-lime-500">← Kembali ke beranda</Link></header>
        <main className="mx-auto grid w-full max-w-6xl flex-1 items-center gap-10 px-6 py-12 md:grid-cols-2 md:gap-20 lg:py-20"><section><p className="text-xs font-semibold tracking-[.18em] text-text-muted">KEUANGAN PRIBADI</p><h1 className="mt-5 text-4xl font-semibold leading-tight tracking-tight lg:text-6xl">Tujuan besar.<br/><span className="text-lime-500">Mulai dari hari ini.</span></h1><p className="mt-6 max-w-sm text-base leading-8 text-text-secondary">Rencanakan tabungan, pantau progres, dan wujudkan satu per satu tujuan finansialmu.</p><div className="mt-9 flex max-w-sm gap-4 border-t border-border pt-6"><GoalIcon className="h-7 w-7 shrink-0 text-lime-500"/><div><p className="text-sm font-medium">Satu ruang untuk rencanamu.</p><p className="mt-2 text-sm leading-7 text-text-muted">Tujuan, setoran, dan perkembangan dana tersusun dalam satu tempat.</p></div></div></section><section className="w-full rounded-card border border-border bg-bg-card p-6 sm:p-9">{children}</section></main>
        <footer className="border-t border-border px-6 py-6 text-center text-sm text-text-muted">FinGoal — simulasi perencanaan keuangan, bukan nasihat investasi.</footer>
    </div>;
}
