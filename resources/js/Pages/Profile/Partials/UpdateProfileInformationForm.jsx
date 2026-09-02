import DateInput from '@/Components/DateInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import { Transition } from '@headlessui/react';
import { Link, useForm, usePage } from '@inertiajs/react';
import { nowInJakartaParts } from '@/utils/timezone';

/**
 * Batas atas tanggal lahir: usia minimal 17 tahun, menyalin aturan di
 * ProfileUpdateRequest. Anak di bawah 17 belum bisa membuka rekening efek
 * sendiri di Indonesia, jadi perencanaan investasi mandiri belum relevan.
 */
const usiaMinimal = () => {
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const d = new Date(tahun - 17, bulan, tanggal);

    return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`;
};
export default function UpdateProfileInformation({
    mustVerifyEmail,
    status,
    className = '',
}) {
    const user = usePage().props.auth.user;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            name: user.name,
            email: user.email,
            // Kolomnya nullable di database. `?? ''` mencegah React beralih
            // dari input tak terkendali ke terkendali saat pengguna mulai
            // mengetik — peralihan itu memicu peringatan dan bisa membuat
            // nilai pertama yang diketik hilang.
            birth_date: user.birth_date ?? '',
            nationality: user.nationality ?? '',
            phone: user.phone ?? '',
            occupation: user.occupation ?? '',
        });

    const submit = (e) => {
        e.preventDefault();

        patch(route('profile.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-text-primary">
                    Informasi Profil
                </h2>

                <p className="mt-1 text-sm text-text-secondary">
                    Perbarui nama, email, dan data identitas akun Anda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel htmlFor="name" value="Nama" />

                    <TextInput
                        id="name"
                        className="mt-1 block w-full"
                        value={data.name}
                        onChange={(e) => setData('name', e.target.value)}
                        required
                        isFocused
                        autoComplete="name"
                    />

                    <InputError className="mt-2" message={errors.name} />
                </div>

                <div>
                    <InputLabel htmlFor="email" value="Email" />

                    <TextInput
                        id="email"
                        type="email"
                        className="mt-1 block w-full"
                        value={data.email}
                        onChange={(e) => setData('email', e.target.value)}
                        required
                        autoComplete="username"
                    />

                    <InputError className="mt-2" message={errors.email} />
                </div>

                {/*
                    Data identitas — seluruhnya opsional. Keterangan di bawah
                    judul menyebutkan kegunaannya, karena meminta data pribadi
                    tanpa menjelaskan untuk apa adalah cara tercepat membuat
                    orang enggan mengisinya (dan bertentangan dengan prinsip
                    minimasi data UU 27/2022 PDP).
                */}
                <div className="border-t border-border pt-6">
                    <h3 className="text-sm font-semibold text-text-primary">
                        Data Identitas
                    </h3>
                    <p className="mt-1 text-xs leading-relaxed text-text-secondary">
                        Semuanya opsional. Tanggal lahir dipakai menghitung
                        jangka waktu dana pensiun, pekerjaan membantu menyarankan
                        profil risiko, dan nomor telepon untuk pengingat setoran
                        nanti. Kosongkan yang tidak ingin Anda bagikan.
                    </p>
                </div>

                <div className="grid gap-6 sm:grid-cols-2">
                    <div>
                        <InputLabel
                            htmlFor="birth_date"
                            value="Tanggal lahir"
                        />

                        {/* Batasnya menyalin ProfileUpdateRequest: setelah
                            1900 (menyaring salah ketik tahun), dan usia minimal
                            17 tahun. */}
                        <DateInput
                            id="birth_date"
                            className="mt-1"
                            min="1900-01-02"
                            max={usiaMinimal()}
                            placeholder="Pilih tanggal lahir"
                            value={data.birth_date}
                            onChange={(v) => setData('birth_date', v)}
                        />

                        <InputError
                            className="mt-2"
                            message={errors.birth_date}
                        />
                    </div>

                    <div>
                        <InputLabel
                            htmlFor="nationality"
                            value="Kewarganegaraan"
                        />

                        <TextInput
                            id="nationality"
                            className="mt-1 block w-full"
                            value={data.nationality}
                            onChange={(e) =>
                                setData('nationality', e.target.value)
                            }
                            placeholder="Indonesia"
                            autoComplete="country-name"
                        />

                        <InputError
                            className="mt-2"
                            message={errors.nationality}
                        />
                    </div>

                    <div>
                        <InputLabel htmlFor="phone" value="Nomor telepon" />

                        <TextInput
                            id="phone"
                            type="tel"
                            inputMode="tel"
                            className="mt-1 block w-full"
                            value={data.phone}
                            onChange={(e) => setData('phone', e.target.value)}
                            placeholder="0812 3456 7890"
                            autoComplete="tel"
                        />

                        <InputError className="mt-2" message={errors.phone} />
                    </div>

                    <div>
                        <InputLabel htmlFor="occupation" value="Pekerjaan" />

                        <TextInput
                            id="occupation"
                            className="mt-1 block w-full"
                            value={data.occupation}
                            onChange={(e) =>
                                setData('occupation', e.target.value)
                            }
                            placeholder="Karyawan swasta"
                            autoComplete="organization-title"
                        />

                        <InputError
                            className="mt-2"
                            message={errors.occupation}
                        />
                    </div>
                </div>

                {mustVerifyEmail && user.email_verified_at === null && (
                    <div>
                        <p className="mt-2 text-sm text-text-primary">
                            Alamat email Anda belum terverifikasi.
                            <Link
                                href={route('verification.send')}
                                method="post"
                                as="button"
                                className="rounded-md text-sm text-text-secondary underline hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                            >
                                Klik di sini untuk mengirim ulang email verifikasi.
                            </Link>
                        </p>

                        {status === 'verification-link-sent' && (
                            <div className="mt-2 text-sm font-medium text-state-success">
                                Tautan verifikasi baru sudah dikirim ke alamat email Anda.
                            </div>
                        )}
                    </div>
                )}

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan</PrimaryButton>

                    <Transition
                        show={recentlySuccessful}
                        enter="transition ease-in-out"
                        enterFrom="opacity-0"
                        leave="transition ease-in-out"
                        leaveTo="opacity-0"
                    >
                        <p className="text-sm text-text-secondary">
                            Tersimpan.
                        </p>
                    </Transition>
                </div>
            </form>
        </section>
    );
}
