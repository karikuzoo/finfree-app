import CurrencyInput from '@/Components/CurrencyInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import SecondaryButton from '@/Components/SecondaryButton';
import TextInput from '@/Components/TextInput';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatDuration, formatRupiah } from '@/utils/format';
import { Head, useForm } from '@inertiajs/react';

const tenorPresets = [
    { label: '1 thn', months: 12 },
    { label: '3 thn', months: 36 },
    { label: '5 thn', months: 60 },
    { label: '10 thn', months: 120 },
    { label: '20 thn', months: 240 },
];

export default function CalculatorGoal({ input, result }) {
    // Nilai awal diambil dari props supaya form tetap terisi setelah halaman
    // dimuat ulang atau tautannya dibagikan ke orang lain.
    const form = useForm({
        target_amount: input?.target_amount ?? '',
        current_amount: input?.current_amount ?? '',
        months: input?.months ?? '',
        annual_return_rate: input?.annual_return_rate ?? '',
        annual_inflation_rate: input?.annual_inflation_rate ?? '',
    });

    function submit(e) {
        e.preventDefault();

        // Field kosong dibuang, bukan dikirim sebagai string kosong — supaya
        // validasi "numeric" di backend tidak menolaknya padahal memang opsional.
        form.transform((data) =>
            Object.fromEntries(
                Object.entries(data).filter(([, v]) => v !== '' && v !== null),
            ),
        ).get(route('calculator.goal'), {
            preserveScroll: true,
            preserveState: true,
        });
    }

    return (
        <PublicLayout>
            <Head title="Kalkulator Tujuan Finansial" />

            <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                <span className="inline-block rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold uppercase tracking-wider text-lime-500">
                    Gratis, tanpa daftar
                </span>

                <h1 className="mt-4 text-3xl font-bold tracking-tight text-text-primary">
                    Kalkulator Tujuan Finansial
                </h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-text-secondary">
                    Berapa yang harus disisihkan tiap bulan agar target Anda
                    tercapai tepat waktu.
                </p>

                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    {/* ── Kolom kiri: parameter ─────────────────────────── */}
                    <form
                        onSubmit={submit}
                        className="rounded-card border border-border bg-bg-card p-6"
                    >
                        <h2 className="text-base font-semibold text-text-primary">
                            Parameter
                        </h2>

                        <div className="mt-5 space-y-5">
                            <div>
                                <InputLabel
                                    htmlFor="target_amount"
                                    value="Nominal target"
                                />
                                <CurrencyInput
                                    id="target_amount"
                                    className="mt-1.5"
                                    placeholder="500.000.000"
                                    value={form.data.target_amount}
                                    onChange={(v) =>
                                        form.setData('target_amount', v)
                                    }
                                />
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Dalam nilai uang hari ini. Inflasi
                                    diperhitungkan terpisah di bawah.
                                </p>
                                <InputError
                                    className="mt-1.5"
                                    message={form.errors.target_amount}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="current_amount"
                                    value="Dana yang sudah dimiliki"
                                />
                                <CurrencyInput
                                    id="current_amount"
                                    className="mt-1.5"
                                    placeholder="0"
                                    value={form.data.current_amount}
                                    onChange={(v) =>
                                        form.setData('current_amount', v)
                                    }
                                />
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Opsional. Dana ini ikut berkembang selama
                                    jangka waktu di bawah.
                                </p>
                                <InputError
                                    className="mt-1.5"
                                    message={form.errors.current_amount}
                                />
                            </div>

                            <div>
                                <InputLabel
                                    htmlFor="months"
                                    value="Jangka waktu (bulan)"
                                />
                                <TextInput
                                    id="months"
                                    type="number"
                                    min="1"
                                    max="720"
                                    className="num-tabular mt-1.5 block w-full"
                                    placeholder="120"
                                    value={form.data.months}
                                    onChange={(e) =>
                                        form.setData('months', e.target.value)
                                    }
                                />

                                <div className="mt-2 flex flex-wrap gap-1.5">
                                    {tenorPresets.map((p) => (
                                        <button
                                            key={p.months}
                                            type="button"
                                            onClick={() =>
                                                form.setData('months', p.months)
                                            }
                                            className={
                                                'rounded-full border px-2.5 py-1 text-xs transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
                                                (Number(form.data.months) ===
                                                p.months
                                                    ? 'border-lime-500 bg-lime-softBg text-lime-500'
                                                    : 'border-border-strong text-text-muted hover:text-text-primary')
                                            }
                                        >
                                            {p.label}
                                        </button>
                                    ))}
                                </div>

                                {form.data.months ? (
                                    <p className="mt-2 text-xs text-text-muted">
                                        = {formatDuration(form.data.months)}
                                    </p>
                                ) : null}

                                <InputError
                                    className="mt-1.5"
                                    message={form.errors.months}
                                />
                            </div>

                            <div className="grid gap-5 sm:grid-cols-2">
                                <div>
                                    <InputLabel
                                        htmlFor="annual_return_rate"
                                        value="Imbal hasil (% / tahun)"
                                    />
                                    <TextInput
                                        id="annual_return_rate"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="30"
                                        className="num-tabular mt-1.5 block w-full"
                                        placeholder="8"
                                        value={form.data.annual_return_rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'annual_return_rate',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        className="mt-1.5"
                                        message={form.errors.annual_return_rate}
                                    />
                                </div>

                                <div>
                                    <InputLabel
                                        htmlFor="annual_inflation_rate"
                                        value="Inflasi (% / tahun)"
                                    />
                                    <TextInput
                                        id="annual_inflation_rate"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="20"
                                        className="num-tabular mt-1.5 block w-full"
                                        placeholder="0"
                                        value={form.data.annual_inflation_rate}
                                        onChange={(e) =>
                                            form.setData(
                                                'annual_inflation_rate',
                                                e.target.value,
                                            )
                                        }
                                    />
                                    <InputError
                                        className="mt-1.5"
                                        message={
                                            form.errors.annual_inflation_rate
                                        }
                                    />
                                </div>
                            </div>

                            <p className="text-xs leading-relaxed text-text-muted">
                                Isi imbal hasil sesuai instrumen yang Anda
                                rencanakan — deposito, obligasi, reksa dana, dan
                                saham punya kisaran yang berbeda jauh. FinGoal
                                tidak mengisikan angka apa pun untuk Anda karena
                                angka itulah yang paling menentukan hasilnya.
                            </p>
                        </div>

                        <PrimaryButton
                            className="mt-6 w-full justify-center py-3 text-sm"
                            disabled={form.processing}
                        >
                            {form.processing ? 'Menghitung…' : 'Hitung Sekarang'}
                        </PrimaryButton>
                    </form>

                    {/* ── Kolom kanan: hasil ────────────────────────────── */}
                    <div className="rounded-card border border-border bg-bg-card p-6">
                        <h2 className="text-base font-semibold text-text-primary">
                            Hasil
                        </h2>

                        {!result ? (
                            <EmptyResult />
                        ) : result.already_achieved ? (
                            <AchievedResult result={result} />
                        ) : (
                            <FullResult result={result} />
                        )}
                    </div>
                </div>
            </div>
        </PublicLayout>
    );
}

function EmptyResult() {
    return (
        <div className="flex min-h-[20rem] flex-col items-center justify-center text-center">
            <svg
                className="h-14 w-14 text-text-muted"
                viewBox="0 0 32 32"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                aria-hidden="true"
            >
                <rect x="7" y="4" width="18" height="24" rx="2.5" />
                <path d="M11 10h10M11 16h4M11 21h4M19 16v5M16.5 18.5h5" />
            </svg>
            <p className="mt-4 max-w-xs text-sm text-text-secondary">
                Isi parameter di sebelah kiri, lalu tekan Hitung Sekarang.
            </p>
        </div>
    );
}

function AchievedResult({ result }) {
    return (
        <div className="mt-5">
            <div className="rounded-lg border-l-2 border-state-success bg-bg-cardAlt p-5">
                <p className="text-sm font-semibold text-state-success">
                    Target Anda sudah tercapai
                </p>
                <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                    Dana yang sudah Anda miliki, bila dibiarkan berkembang
                    dengan imbal hasil tersebut, akan melampaui target sebelum
                    jangka waktunya habis. Tidak perlu setoran bulanan.
                </p>
            </div>

            <Breakdown result={result} />
        </div>
    );
}

function FullResult({ result }) {
    return (
        <div className="mt-5">
            <div className="rounded-lg border-l-2 border-lime-500 bg-bg-cardAlt p-5">
                <p className="text-xs font-medium uppercase tracking-wider text-text-secondary">
                    Setoran bulanan
                </p>
                <p className="num-tabular mt-1 font-mono text-4xl font-bold leading-tight text-lime-500">
                    {formatRupiah(result.monthly_contribution_required)}
                </p>
            </div>

            <Breakdown result={result} />
        </div>
    );
}

function Breakdown({ result }) {
    const rows = [
        {
            label: 'Target setelah inflasi',
            value: formatRupiah(result.future_value_target),
            hint: 'Nominal yang sebenarnya perlu Anda kumpulkan pada tanggal target.',
        },
        {
            label: 'Total setoran Anda',
            value: formatRupiah(result.total_contribution_projection),
        },
        {
            label: 'Hasil pengembangan',
            value: formatRupiah(result.total_investment_growth_projection),
            hint: 'Bagian yang datang dari imbal hasil, bukan dari kantong Anda.',
        },
        {
            label: 'Proyeksi dana akhir',
            value: formatRupiah(result.future_value_projection),
        },
    ];

    return (
        <>
            <dl className="mt-5 space-y-3">
                {rows.map((row) => (
                    <div key={row.label}>
                        <div className="flex items-baseline justify-between gap-3">
                            <dt className="text-sm text-text-secondary">
                                {row.label}
                            </dt>
                            <dd className="num-tabular text-sm font-medium text-text-primary">
                                {row.value}
                            </dd>
                        </div>
                        {row.hint && (
                            <p className="mt-0.5 text-xs text-text-muted">
                                {row.hint}
                            </p>
                        )}
                    </div>
                ))}
            </dl>

            <div className="mt-5 border-t border-border pt-4">
                <p className="text-xs leading-relaxed text-text-muted">
                    Metode: anuitas efektif, setoran di akhir bulan. Imbal hasil
                    tahunan dikonversi menjadi bulanan secara majemuk, bukan
                    dibagi dua belas. Inflasi menaikkan nominal target, bukan
                    mengurangi imbal hasil. Setoran bulanan dibulatkan ke atas
                    ke rupiah penuh agar target tidak meleset tipis.
                </p>
                <p className="mt-3 text-xs leading-relaxed text-text-muted">
                    Angka di atas adalah simulasi berdasarkan asumsi yang Anda
                    isi sendiri, bukan saran investasi personal. Imbal hasil
                    nyata berfluktuasi dan tidak dijamin.
                </p>
            </div>

            <div className="mt-5">
                <SecondaryButton disabled className="w-full justify-center">
                    Jadikan Tujuan
                </SecondaryButton>
                <p className="mt-2 text-center text-xs text-text-muted">
                    Menyimpan hasil sebagai tujuan yang dipantau — tersedia di
                    Rilis 1.
                </p>
            </div>
        </>
    );
}
