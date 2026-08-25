import Avatar from '@/Components/Avatar';
import DangerButton from '@/Components/DangerButton';
import InputError from '@/Components/InputError';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import { Transition } from '@headlessui/react';
import { router, useForm, usePage } from '@inertiajs/react';
import { useRef, useState } from 'react';

export default function UpdateAvatarForm({ className = '' }) {
    const user = usePage().props.auth.user;
    const fileInput = useRef(null);

    // Pratinjau lokal supaya pengguna melihat pilihannya sebelum menyimpan.
    // URL objek ini hanya hidup di browser dan tidak pernah dikirim ke server.
    const [preview, setPreview] = useState(null);

    const { data, setData, post, errors, processing, recentlySuccessful, reset } =
        useForm({ avatar: null });

    const pick = (e) => {
        const file = e.target.files?.[0] ?? null;
        setData('avatar', file);
        setPreview(file ? URL.createObjectURL(file) : null);
    };

    const submit = (e) => {
        e.preventDefault();

        post(route('profile.avatar.update'), {
            preserveScroll: true,
            onSuccess: () => {
                reset();
                setPreview(null);
                if (fileInput.current) fileInput.current.value = '';
            },
        });
    };

    const removePhoto = () => {
        router.delete(route('profile.avatar.destroy'), {
            preserveScroll: true,
        });
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-text-primary">
                    Foto Profil
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                    Tanpa foto, inisial nama Anda yang ditampilkan. Gambar akan
                    dipotong menjadi persegi dan diperkecil otomatis.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-5">
                <div className="flex items-center gap-5">
                    {preview ? (
                        <img
                            src={preview}
                            alt="Pratinjau foto profil"
                            className="h-20 w-20 shrink-0 rounded-full border border-lime-500 object-cover"
                        />
                    ) : (
                        <Avatar user={user} size={80} />
                    )}

                    <div className="min-w-0">
                        <input
                            ref={fileInput}
                            type="file"
                            accept="image/jpeg,image/png,image/webp"
                            onChange={pick}
                            className="block w-full text-sm text-text-secondary file:mr-3 file:cursor-pointer file:rounded-lg file:border-0 file:bg-bg-cardAlt file:px-4 file:py-2 file:text-sm file:font-semibold file:text-text-primary hover:file:bg-border"
                        />
                        <p className="mt-2 text-xs text-text-muted">
                            JPG, PNG, atau WEBP. Maksimal 4 MB.
                        </p>
                        <InputError className="mt-2" message={errors.avatar} />
                    </div>
                </div>

                <div className="flex flex-wrap items-center gap-3">
                    <PrimaryButton disabled={processing || !data.avatar}>
                        {processing ? 'Mengunggah…' : 'Simpan Foto'}
                    </PrimaryButton>

                    {preview && (
                        <SecondaryButton
                            onClick={() => {
                                reset();
                                setPreview(null);
                                if (fileInput.current)
                                    fileInput.current.value = '';
                            }}
                        >
                            Batal
                        </SecondaryButton>
                    )}

                    {user.avatar_url && !preview && (
                        <DangerButton type="button" onClick={removePhoto}>
                            Hapus Foto
                        </DangerButton>
                    )}

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-state-success">Tersimpan.</p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
