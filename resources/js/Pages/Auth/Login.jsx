import PasswordInput from '@/Components/PasswordInput';
import Checkbox from '@/Components/Checkbox';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { Head, Link, useForm } from '@inertiajs/react';

export default function Login({ status, canResetPassword }) {
    const { data, setData, post, processing, errors, reset } = useForm({
        email: '',
        password: '',
        remember: false,
    });

    const submit = (e) => {
        e.preventDefault();

        post(route('login'), {
            onFinish: () => reset('password'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Masuk" />
            <h2 className="text-2xl font-semibold tracking-tight">Selamat datang kembali</h2>
            <p className="mb-7 mt-2 text-sm leading-7 text-text-secondary">Masuk untuk melanjutkan rencana keuanganmu.</p>

            {status && (
                <div className="mb-4 text-sm font-medium text-state-success">
                    {status}
                </div>
            )}

            <form onSubmit={submit}>
                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        isFocused={true}
                        onChange={(e) => setData('email', e.target.value)}
                    />

                    <InputError message={errors.email} className="mt-2" />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Kata Sandi" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="current-password"
                        onChange={(e) => setData('password', e.target.value)}
                    />

                    <InputError message={errors.password} className="mt-2" />
                </div>

                <div className="mt-4 block">
                    <label className="flex items-center">
                        <Checkbox
                            name="remember"
                            checked={data.remember}
                            onChange={(e) =>
                                setData('remember', e.target.checked)
                            }
                        />
                        <span className="ms-2 text-sm text-text-secondary">
                            Ingat saya
                        </span>
                    </label>
                </div>

                <div className="mt-6 flex flex-wrap items-center justify-between gap-4">
                    {canResetPassword && (
                        <Link
                            href={route('password.request')}
                            className="rounded-md text-sm text-text-secondary underline hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                        >
                            Lupa kata sandi?
                        </Link>
                    )}

                    <PrimaryButton className="px-8" disabled={processing}>
                        Masuk
                    </PrimaryButton>
                </div>
            </form>
            <p className="mt-7 border-t border-border pt-6 text-center text-sm text-text-secondary">Belum punya akun? <Link href={route('register')} className="font-medium text-lime-500">Daftar sekarang</Link></p>
        </GuestLayout>
    );
}
