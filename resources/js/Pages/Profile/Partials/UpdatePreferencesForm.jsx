import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import { Transition } from '@headlessui/react';
import { useForm, usePage } from '@inertiajs/react';

/**
 * Preferensi investasi — profil risiko, instrumen syariah, mata uang.
 *
 * Profil risiko dipilih lewat kartu, bukan dropdown, karena setiap pilihan
 * perlu penjelasan agar bisa dipilih dengan sadar. Dropdown hanya menampilkan
 * label, dan "Moderat" tidak berarti apa-apa bagi orang yang baru mulai
 * berinvestasi.
 */
export default function UpdatePreferencesForm({ className = '' }) {
    const user = usePage().props.auth.user;
    const riskProfiles = usePage().props.riskProfiles;

    const { data, setData, patch, errors, processing, recentlySuccessful } =
        useForm({
            risk_profile: user.risk_profile,
            prefers_syariah: user.prefers_syariah,
        });

    const submit = (e) => {
        e.preventDefault();
        patch(route('profile.preferences.update'));
    };

    return (
        <section className={className}>
            <header>
                <h2 className="text-lg font-medium text-text-primary">
                    Preferensi Investasi
                </h2>
                <p className="mt-1 text-sm text-text-secondary">
                    Dipakai sebagai nilai awal saat FinGoal menyarankan alokasi
                    instrumen untuk tujuan Anda. Tiap tujuan tetap bisa memakai
                    profil risiko yang berbeda.
                </p>
            </header>

            <form onSubmit={submit} className="mt-6 space-y-6">
                <div>
                    <InputLabel value="Profil risiko" />

                    <div className="mt-2 space-y-2">
                        {riskProfiles.map((option) => {
                            const selected = data.risk_profile === option.value;

                            return (
                                <label
                                    key={option.value}
                                    className={
                                        'flex cursor-pointer gap-3 rounded-lg border p-4 transition ' +
                                        (selected
                                            ? 'border-lime-500 bg-lime-softBg'
                                            : 'border-border-strong hover:bg-bg-cardAlt')
                                    }
                                >
                                    <input
                                        type="radio"
                                        name="risk_profile"
                                        value={option.value}
                                        checked={selected}
                                        onChange={(e) =>
                                            setData(
                                                'risk_profile',
                                                e.target.value,
                                            )
                                        }
                                        className="mt-0.5 border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-card"
                                    />
                                    <span>
                                        <span
                                            className={
                                                'block text-sm font-semibold ' +
                                                (selected
                                                    ? 'text-lime-500'
                                                    : 'text-text-primary')
                                            }
                                        >
                                            {option.label}
                                        </span>
                                        <span className="mt-0.5 block text-xs leading-relaxed text-text-secondary">
                                            {option.description}
                                        </span>
                                    </span>
                                </label>
                            );
                        })}
                    </div>

                    <InputError className="mt-2" message={errors.risk_profile} />
                </div>

                <div>
                    <InputLabel value="Instrumen syariah" />

                    <label className="mt-2 flex cursor-pointer gap-3 rounded-lg border border-border-strong p-4 transition hover:bg-bg-cardAlt">
                        <input
                            type="checkbox"
                            checked={data.prefers_syariah}
                            onChange={(e) =>
                                setData('prefers_syariah', e.target.checked)
                            }
                            className="mt-0.5 rounded border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-card"
                        />
                        <span>
                            <span className="block text-sm font-medium text-text-primary">
                                Sarankan instrumen syariah saja
                            </span>
                            <span className="mt-0.5 block text-xs leading-relaxed text-text-secondary">
                                Rekomendasi dibatasi pada sukuk, reksa dana
                                syariah, deposito syariah, dan saham indeks
                                syariah.
                            </span>
                        </span>
                    </label>

                    <InputError
                        className="mt-2"
                        message={errors.prefers_syariah}
                    />
                </div>

                <div>
                    <InputLabel value="Mata uang" />
                    <p className="mt-2 rounded-lg border border-border px-4 py-3 text-sm text-text-secondary">
                        Rupiah (IDR).{' '}
                        <span className="text-text-muted">
                            Mata uang lain belum didukung.
                        </span>
                    </p>
                </div>

                <div className="flex items-center gap-4">
                    <PrimaryButton disabled={processing}>Simpan</PrimaryButton>

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
