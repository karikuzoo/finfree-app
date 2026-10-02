import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import PrimaryButton from '@/Components/PrimaryButton';
import TextInput from '@/Components/TextInput';
import PublicLayout from '@/Layouts/PublicLayout';
import { formatDuration } from '@/utils/format';
import { Head, Link, usePage } from '@inertiajs/react';

/**
 * Kerangka bersama kalkulator utilitas publik (FR-41, FR-42): judul, kolom
 * parameter di kiri, kolom hasil di kanan. Tampilannya sengaja sama dengan
 * Calculator/Goal.jsx supaya ketiga kalkulator terasa satu keluarga.
 *
 * `onSubmit` diteruskan apa adanya; tiap halaman yang menentukan route dan
 * transform-nya sendiri.
 */
export default function CalculatorFrame({
    title,
    subtitle,
    onSubmit,
    processing,
    resetHref,
    fields,
    result,
    below,
}) {
    return (
        <PublicLayout>
            <Head title={title} />

            <div className="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-center justify-between gap-3">
                    <Link
                        href={route('calculator.index')}
                        className="text-xs font-medium text-text-muted transition hover:text-text-primary"
                    >
                        ← Semua kalkulator
                    </Link>
                    <TautanRiwayat />
                </div>

                <div className="mt-4">
                    <span className="inline-block rounded-full bg-lime-softBg px-3 py-1 text-xs font-semibold uppercase tracking-wider text-lime-500">
                        Gratis, tanpa daftar
                    </span>
                </div>

                <h1 className="mt-4 text-3xl font-bold tracking-tight text-text-primary">{title}</h1>
                <p className="mt-2 max-w-2xl text-sm leading-relaxed text-text-secondary">{subtitle}</p>

                <div className="mt-8 grid gap-6 lg:grid-cols-2">
                    <form onSubmit={onSubmit} className="rounded-card border border-border bg-bg-card p-6">
                        <h2 className="text-base font-semibold text-text-primary">Parameter</h2>

                        <div className="mt-5 space-y-5">{fields}</div>

                        <div className="mt-6 flex flex-col gap-3 sm:flex-row sm:items-center">
                            <PrimaryButton className="w-full justify-center py-3 text-sm" disabled={processing}>
                                {processing ? 'Menghitung…' : 'Hitung Sekarang'}
                            </PrimaryButton>

                            {resetHref && (
                                <a
                                    href={resetHref}
                                    className="inline-flex w-full items-center justify-center rounded-lg border border-border-strong bg-transparent px-4 py-3 text-sm font-semibold text-text-secondary transition hover:bg-bg-cardAlt hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base sm:w-auto"
                                >
                                    Reset
                                </a>
                            )}
                        </div>
                    </form>

                    <div className="rounded-card border border-border bg-bg-card p-6">
                        <h2 className="text-base font-semibold text-text-primary">Hasil</h2>
                        {result}
                    </div>
                </div>

                {below && <div className="mt-6">{below}</div>}
            </div>
        </PublicLayout>
    );
}

/** Isian jangka waktu dalam bulan + tombol tenor tahunan. */
/**
 * Tautan ke riwayat kalkulasi (FR-45) — hanya bagi yang sudah masuk, karena
 * hanya merekalah yang hitungannya dicatat.
 */
export function TautanRiwayat() {
    if (!usePage().props.auth?.user) return null;

    return (
        <Link
            href={route('calculator.history')}
            className="text-xs font-medium text-text-muted transition hover:text-text-primary"
        >
            Riwayat kalkulasi →
        </Link>
    );
}

export function TenorField({ id = 'months', label, value, onChange, error, max, presets }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                type="number"
                min="1"
                max={max}
                className="num-tabular mt-1.5 block w-full"
                placeholder="120"
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />

            <div className="mt-2 flex flex-wrap gap-1.5">
                {presets.map((tahun) => (
                    <button
                        key={tahun}
                        type="button"
                        onClick={() => onChange(tahun * 12)}
                        className={
                            'rounded-full border px-2.5 py-1 text-xs transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
                            (Number(value) === tahun * 12
                                ? 'border-lime-500 bg-lime-softBg text-lime-500'
                                : 'border-border-strong text-text-muted hover:text-text-primary')
                        }
                    >
                        {tahun} thn
                    </button>
                ))}
            </div>

            {value ? <p className="mt-2 text-xs text-text-muted">= {formatDuration(value)}</p> : null}

            <InputError className="mt-1.5" message={error} />
        </div>
    );
}

/** Angka utama hasil, bergaris aksen di kiri. */
export function HeadlineResult({ label, value, note }) {
    return (
        <div className="mt-5 rounded-lg border-l-2 border-lime-500 bg-bg-cardAlt p-5">
            <p className="text-xs font-medium uppercase tracking-wider text-text-secondary">{label}</p>
            <p className="num-tabular mt-1 text-4xl font-bold leading-tight text-lime-500">{value}</p>
            {note && <p className="mt-2 text-xs text-text-muted">{note}</p>}
        </div>
    );
}

/** Daftar baris "label — angka", dengan keterangan opsional per baris. */
export function ResultRows({ rows }) {
    return (
        <dl className="mt-5 space-y-3">
            {rows.map((row) => (
                <div key={row.label}>
                    <div className="flex items-baseline justify-between gap-3">
                        <dt className="text-sm text-text-secondary">{row.label}</dt>
                        <dd className="num-tabular text-sm font-medium text-text-primary">{row.value}</dd>
                    </div>
                    {row.hint && <p className="mt-0.5 text-xs text-text-muted">{row.hint}</p>}
                </div>
            ))}
        </dl>
    );
}

/** Keadaan sebelum ada yang dihitung. */
export function EmptyResult({ children }) {
    return (
        <div className="flex min-h-[16rem] flex-col items-center justify-center text-center">
            <p className="max-w-xs text-sm leading-relaxed text-text-muted">{children}</p>
        </div>
    );
}

/**
 * Galat validasi untuk ditampilkan di bawah isian.
 *
 * `form.errors` dari useForm hanya terisi sesudah form DIKIRIM. Kalau yang
 * ditolak adalah tautan yang dibuka langsung — dimuat ulang, dibagikan, atau
 * dari versi lama — server mengarahkan ke alamat bersih dengan galat di
 * `usePage().props.errors`, dan tanpa ini halamannya tampil kosong tanpa
 * penjelasan (lihat ValidatesCalculatorQuery).
 */
export function useGalatKalkulator(form) {
    const galatHalaman = usePage().props.errors ?? {};

    return Object.keys(form.errors).length ? form.errors : galatHalaman;
}

/** Buang isian kosong sebelum dikirim, supaya kolom opsional tidak ditolak `numeric`. */
export const tanpaIsianKosong = (data) =>
    Object.fromEntries(Object.entries(data).filter(([, v]) => v !== '' && v !== null));
