import AuthenticatedLayout from '@/Layouts/AuthenticatedLayout';
import Avatar from '@/Components/Avatar';
import { Head, usePage } from '@inertiajs/react';
import DeleteUserForm from './Partials/DeleteUserForm';
import UpdateAvatarForm from './Partials/UpdateAvatarForm';
import UpdatePasswordForm from './Partials/UpdatePasswordForm';
import UpdatePreferencesForm from './Partials/UpdatePreferencesForm';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm';

export default function Edit({ mustVerifyEmail, status }) {
    const user = usePage().props.auth.user;
    const sections = [['identitas','Informasi pribadi'],['foto','Foto profil'],['preferensi','Preferensi investasi'],['keamanan','Keamanan'],['hapus','Penghapusan akun']];
    return <AuthenticatedLayout><Head title="Profil & pengaturan"/>
        <div className="mx-auto max-w-7xl px-4 py-8 sm:px-8">
            <p className="mb-2 text-xs font-semibold tracking-[.15em] text-text-muted">AKUN SAYA</p><h1 className="text-3xl font-semibold tracking-tight">Profil & pengaturan</h1><p className="mt-3 text-sm text-text-secondary">Kelola informasi pribadi dan preferensi perencanaanmu.</p>
            <div className="mt-8 grid items-start gap-6 xl:grid-cols-[280px_minmax(0,1fr)]">
                <aside className="rounded-card border border-border bg-bg-card p-6 xl:sticky xl:top-6"><div className="flex flex-col items-center text-center"><Avatar user={user} size={80}/><h2 className="mt-5 max-w-full break-words text-xl font-semibold">{user.name}</h2><p className="mt-2 max-w-full break-words text-sm text-text-muted">{user.email}</p><span className="mt-4 rounded-full bg-lime-softBg px-3 py-1 text-xs text-lime-500">{user.email_verified_at ? 'Email terverifikasi' : 'Email belum terverifikasi'}</span></div><nav aria-label="Bagian pengaturan" className="mt-6 space-y-1 border-t border-border pt-5">{sections.map(([id,label]) => <a key={id} href={'#'+id} className="block rounded-lg px-3 py-3 text-sm text-text-secondary hover:bg-lime-softBg hover:text-lime-500">{label}</a>)}</nav></aside>
                <div className="min-w-0 space-y-6">
                    <section id="identitas" className="scroll-mt-6 rounded-card border border-border bg-bg-card p-6 sm:p-8"><UpdateProfileInformationForm mustVerifyEmail={mustVerifyEmail} status={status} className="max-w-2xl"/></section>
                    <section id="foto" className="scroll-mt-6 rounded-card border border-border bg-bg-card p-6 sm:p-8"><UpdateAvatarForm className="max-w-2xl"/></section>
                    <section id="preferensi" className="scroll-mt-6 rounded-card border border-border bg-bg-card p-6 sm:p-8"><UpdatePreferencesForm className="max-w-2xl"/></section>
                    <section id="keamanan" className="scroll-mt-6 rounded-card border border-border bg-bg-card p-6 sm:p-8"><UpdatePasswordForm className="max-w-2xl"/></section>
                    <section id="hapus" className="scroll-mt-6 rounded-card border border-state-danger/30 bg-bg-card p-6 sm:p-8"><DeleteUserForm className="max-w-2xl"/></section>
                </div>
            </div>
        </div>
    </AuthenticatedLayout>;
}
