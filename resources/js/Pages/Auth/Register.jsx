import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PasswordInput from '@/Components/PasswordInput';
import PasswordRequirements from '@/Components/PasswordRequirements';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import GuestLayout from '@/Layouts/GuestLayout';
import { pemeriksa } from '@/utils/registerValidation';
import { Head, Link, useForm } from '@inertiajs/react';
import { useState } from 'react';

export default function Register() {
    const { data, setData, post, processing, errors, reset } = useForm({
        name: '',
        email: '',
        password: '',
        password_confirmation: '',
    });

    // Pesan yang muncul SEBELUM tombol ditekan. Terpisah dari `errors` milik
    // Inertia yang datang dari backend setelah submit; keduanya ditampilkan
    // lewat komponen yang sama, dengan pesan backend didahulukan karena
    // backend yang berwenang menentukan diterima atau tidak.
    const [lokal, setLokal] = useState({});

    /**
     * Diperiksa saat field ditinggalkan, bukan saat mengetik (DESIGN.md §9.4).
     * Memeriksa tiap ketukan berarti menegur "Format email tidak valid" pada
     * huruf pertama yang diketik, padahal orangnya belum selesai mengetik.
     */
    const periksaField = (field) => () =>
        setLokal((s) => ({ ...s, [field]: pemeriksa[field](data) }));

    /**
     * Saat mengetik, pesan yang **sudah tampil** diperbarui mengikuti isi
     * terbaru — hilang bila sudah benar, atau berganti bila salahnya berpindah.
     * Tanpa ini pesan bisa tertinggal berbunyi "Nama wajib diisi" padahal
     * fieldnya sudah berisi, hanya isinya yang belum sesuai.
     *
     * Field yang belum pernah ditinggalkan tetap dibiarkan diam — orang yang
     * baru mengetik huruf pertama belum pantas ditegur.
     */
    const ubah = (field) => (e) => {
        const nilai = e.target.value;
        setData(field, nilai);

        setLokal((s) =>
            s[field]
                ? { ...s, [field]: pemeriksa[field]({ ...data, [field]: nilai }) }
                : s,
        );
    };

    const submit = (e) => {
        e.preventDefault();

        // Periksa seluruh field sekali lagi sebelum mengirim, supaya field
        // yang belum pernah disentuh ikut ketahuan tanpa menunggu balasan
        // server.
        const semua = Object.fromEntries(
            Object.entries(pemeriksa).map(([field, uji]) => [field, uji(data)]),
        );

        setLokal(semua);

        if (Object.values(semua).some(Boolean)) {
            document.getElementById(
                Object.keys(semua).find((f) => semua[f]),
            )?.focus();

            return;
        }

        post(route('register'), {
            onFinish: () => reset('password', 'password_confirmation'),
        });
    };

    return (
        <GuestLayout>
            <Head title="Daftar" />

            <form onSubmit={submit} noValidate>
                <div>
                    <InputLabel htmlFor="name" value="Nama" />

                    <TextInput
                        id="name"
                        name="name"
                        value={data.name}
                        className="mt-1 block w-full"
                        autoComplete="name"
                        isFocused={true}
                        onChange={ubah('name')}
                        onBlur={periksaField('name')}
                    />

                    <InputError
                        message={errors.name ?? lokal.name}
                        className="mt-2"
                    />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        name="email"
                        value={data.email}
                        className="mt-1 block w-full"
                        autoComplete="username"
                        onChange={ubah('email')}
                        onBlur={periksaField('email')}
                    />

                    <InputError
                        message={errors.email ?? lokal.email}
                        className="mt-2"
                    />
                </div>

                <div className="mt-4">
                    <InputLabel htmlFor="password" value="Kata Sandi" />

                    <PasswordInput
                        id="password"
                        name="password"
                        value={data.password}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={ubah('password')}
                        onBlur={periksaField('password')}
                    />

                    <InputError
                        message={errors.password ?? lokal.password}
                        className="mt-2"
                    />

                    <PasswordRequirements
                        className="mt-2"
                        value={data.password}
                    />
                </div>

                <div className="mt-4">
                    <InputLabel
                        htmlFor="password_confirmation"
                        value="Konfirmasi Kata Sandi"
                    />

                    <PasswordInput
                        id="password_confirmation"
                        name="password_confirmation"
                        value={data.password_confirmation}
                        className="mt-1 block w-full"
                        autoComplete="new-password"
                        onChange={ubah('password_confirmation')}
                        onBlur={periksaField('password_confirmation')}
                    />

                    <InputError
                        message={
                            errors.password_confirmation ??
                            lokal.password_confirmation
                        }
                        className="mt-2"
                    />
                </div>

                <div className="mt-4 flex items-center justify-end">
                    <Link
                        href={route('login')}
                        className="rounded-md text-sm text-text-secondary underline hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                    >
                        Sudah punya akun?
                    </Link>

                    <PrimaryButton className="ms-4" disabled={processing}>
                        Daftar
                    </PrimaryButton>
                </div>
            </form>
        </GuestLayout>
    );
}
