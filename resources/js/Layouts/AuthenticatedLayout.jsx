import Brand from '@/Components/Brand';
import Avatar from '@/Components/Avatar';
import { Link, usePage } from '@inertiajs/react';
import { Dialog, DialogPanel, DialogTitle } from '@headlessui/react';
import { useState } from 'react';
import { DashboardIcon, GoalIcon, WalletIcon, HistoryIcon, CalculatorIcon, NewsIcon, UserIcon, LogoutIcon } from '@/Components/Icons';

const groups = [
    { title: 'KEUANGAN PRIBADI', items: [
        ['Dashboard', 'dashboard', 'dashboard', DashboardIcon],
        ['Transaksi', 'transactions.index', 'transactions.*', HistoryIcon],
        ['Rekening & aset', 'accounts.index', 'accounts.*', WalletIcon],
        ['Investasi', 'investments.index', 'investments.*', CalculatorIcon],
        ['Tujuan saya', 'goals.index', 'goals.*', GoalIcon],
        ['Dompet & aset', 'wallet.index', 'wallet.*', WalletIcon],
        ['Riwayat', 'history.index', 'history.*', HistoryIcon],
    ] },
    { title: 'RENCANAKAN MASA DEPAN', items: [
        ['Kalkulator', 'calculator.index', 'calculator.*', CalculatorIcon],
        ['Berita keuangan', 'news.index', 'news.*', NewsIcon],
        ['Profil & pengaturan', 'profile.edit', 'profile.*', UserIcon],
    ] },
];

export default function AuthenticatedLayout({ header, children }) {
    const user = usePage().props.auth.user;
    const [mobileOpen, setMobileOpen] = useState(false);
    const activeLabel = groups.flatMap(group => group.items).find(item => route().current(item[2]))?.[0] || 'Ruang pribadi';
    const sidebar = <>
        <Link href={route('dashboard')} className="flex items-center gap-3 px-6 pt-8 text-text-primary">
            <Brand/>
        </Link>
        <p className="mb-8 mt-3 px-6 text-sm text-text-muted">Ruang untuk tumbuh.</p>
        <nav aria-label="Navigasi utama" className="flex-1 space-y-7 px-3">
            {groups.map(group => <div key={group.title}><p className="mb-3 px-3 text-xs font-semibold tracking-widest text-text-muted">{group.title}</p><div className="space-y-1">{group.items.map(([label, name, match, Icon]) => {
                const active = route().current(match);
                return <Link key={name} href={route(name)} onClick={() => setMobileOpen(false)} aria-current={active ? 'page' : undefined} className={'flex min-h-11 items-center gap-3 rounded-lg border-l-2 px-3 py-3 text-sm font-medium transition ' + (active ? 'border-lime-500 bg-lime-softBg text-lime-500' : 'border-transparent text-text-secondary hover:bg-bg-cardAlt hover:text-text-primary')}><Icon className="h-5 w-5 shrink-0" /><span>{label}</span></Link>;
            })}</div></div>)}
        </nav>
        <div className="m-5 mt-8 rounded-xl border border-border bg-gradient-to-br from-lime-softBg to-bg-surface p-4"><GoalIcon className="h-6 w-6 text-lime-500" /><p className="mt-3 text-sm leading-7 text-text-secondary">Langkah kecil hari ini.<br/><span className="text-text-primary">Masa depan lebih tenang.</span></p><Link href={route('goals.create')} className="mt-4 inline-flex items-center gap-2 text-sm font-medium text-lime-500">Susun tujuanmu <span aria-hidden="true">→</span></Link></div>
        <div className="mx-5 mb-5 border-t border-border pt-5"><Link href={route('profile.edit')} className="flex min-w-0 items-center gap-3"><Avatar user={user} size={36}/><span className="min-w-0"><span className="block truncate text-sm font-medium">{user.name}</span><span className="text-xs text-text-muted">Akun pribadi</span></span></Link><Link href={route('logout')} method="post" as="button" className="mt-4 flex items-center gap-2 text-sm text-text-muted hover:text-text-primary"><LogoutIcon className="h-4 w-4"/> Keluar akun</Link></div>
    </>;
    return <div className="flex min-h-screen bg-bg-base">
        <a href="#main-content" className="sr-only focus:not-sr-only focus:fixed focus:left-4 focus:top-4 focus:z-50 focus:rounded-lg focus:bg-lime-500 focus:p-3 focus:text-onPrimary">Lewati ke konten</a>
        <aside className="sticky top-0 hidden h-screen w-[244px] shrink-0 flex-col overflow-y-auto border-r border-border bg-bg-surface lg:flex">{sidebar}</aside>
        <Dialog open={mobileOpen} onClose={setMobileOpen} className="relative z-50 lg:hidden"><div className="fixed inset-0 bg-black/60" aria-hidden="true"/><DialogPanel className="fixed inset-y-0 left-0 flex w-[min(300px,90vw)] flex-col overflow-y-auto border-r border-border bg-bg-surface"><DialogTitle className="sr-only">Menu Arus</DialogTitle><button onClick={() => setMobileOpen(false)} className="absolute right-3 top-2 p-2 text-xl text-text-secondary" aria-label="Tutup menu">×</button>{sidebar}</DialogPanel></Dialog>
        <div className="flex min-w-0 flex-1 flex-col">
            <div className="flex min-h-[76px] items-center justify-between gap-3 border-b border-border px-4 sm:px-8"><div className="flex min-w-0 items-center gap-3"><button type="button" onClick={() => setMobileOpen(true)} aria-label="Buka menu navigasi" aria-expanded={mobileOpen} className="rounded-lg p-2 text-text-secondary lg:hidden"><svg width="22" height="22" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2" aria-hidden="true"><path d="M4 6h16M4 12h16M4 18h16"/></svg></button><span className="hidden text-sm text-text-muted sm:inline">Ruang pribadi</span><span className="hidden text-text-muted sm:inline" aria-hidden="true">›</span><span className="truncate text-sm font-medium">{activeLabel}</span></div><Link href={route('profile.edit')} aria-label="Buka profil saya" className="flex shrink-0 items-center gap-3"><span className="hidden text-xs text-text-secondary sm:inline">Akun pribadi</span><Avatar user={user} size={32}/></Link></div>
            {header && <header className="px-4 pt-8 sm:px-8">{header}</header>}
            <main id="main-content" className="min-w-0 flex-1">{children}</main>
            <footer className="mx-4 flex flex-wrap justify-between gap-3 border-t border-border py-5 text-xs text-text-muted sm:mx-8"><span><b className="font-semibold text-text-secondary">arus.</b> Uang lebih terarah. Hidup lebih tenang.</span><span>Simulasi edukatif · Bukan nasihat investasi</span></footer>
        </div>
    </div>;
}
