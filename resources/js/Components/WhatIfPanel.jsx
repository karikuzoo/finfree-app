import ProjectionChart from '@/Components/ProjectionChart';
import SecondaryButton from '@/Components/SecondaryButton';
import {
    calculateMonthlyContribution,
    projectionSeries,
    solveMonths,
} from '@/utils/goalCalculator';
import { formatDuration, formatRupiah } from '@/utils/format';
import { useMemo, useState } from 'react';

/**
 * Skenario "bagaimana jika" (FR-8).
 *
 * Dua slider yang saling terkait: menggeser jangka waktu menghitung ulang
 * setoran, menggeser setoran menghitung ulang jangka waktu. Perhitungannya
 * berjalan di browser memakai salinan rumus yang sama dengan backend
 * (`utils/goalCalculator.js`, diuji terhadap test vector yang sama).
 *
 * Catatan penyimpangan dari DESIGN.md §9.5: dokumen menyarankan debounce
 * 150ms dan animasi count-up 300ms. Keduanya tidak dipakai di sini karena
 * perhitungannya sinkron dan selesai dalam hitungan mikrodetik — menunda atau
 * menganimasikannya justru membuat slider terasa tersendat, bukan lebih halus.
 */
export default function WhatIfPanel({ input, baseline }) {
    const baseMonths = Number(input.months);
    const basePmt = Number(baseline.monthly_contribution_required);

    const [months, setMonths] = useState(baseMonths);
    const [pmt, setPmt] = useState(basePmt);

    const shared = {
        targetAmount: Number(input.target_amount),
        currentAmount: Number(input.current_amount ?? 0),
        annualReturnRate: Number(input.annual_return_rate),
        annualInflationRate: Number(input.annual_inflation_rate ?? 0),
    };

    const ubahJangkaWaktu = (nilai) => {
        const n = Number(nilai);
        setMonths(n);

        const hasil = calculateMonthlyContribution({ ...shared, months: n });
        if (hasil) setPmt(hasil.monthly_contribution_required);
    };

    const ubahSetoran = (nilai) => {
        const nominal = Number(nilai);
        setPmt(nominal);

        const n = solveMonths({ ...shared, monthlyContribution: nominal });
        if (n) setMonths(n);
    };

    const reset = () => {
        setMonths(baseMonths);
        setPmt(basePmt);
    };

    const berubah = months !== baseMonths || pmt !== basePmt;

    const deret = useMemo(
        () =>
            projectionSeries({
                currentAmount: shared.currentAmount,
                months,
                monthlyContribution: pmt,
                annualReturnRate: shared.annualReturnRate,
            }),
        [months, pmt, shared.currentAmount, shared.annualReturnRate],
    );

    // Setoran yang mustahil dicapai membuat solveMonths mengembalikan null,
    // dan slider berhenti bergerak. Beri tahu alih-alih membiarkannya terasa
    // seperti kerusakan.
    const takTercapai =
        solveMonths({ ...shared, monthlyContribution: pmt }) === null;

    const maxPmt = Math.max(basePmt * 3, 1_000_000);

    return (
        <div className="rounded-card border border-border bg-bg-card p-6">
            <h2 className="text-base font-semibold text-text-primary">
                Bagaimana jika…
            </h2>
            <p className="mt-1 text-sm text-text-secondary">
                Geser salah satunya, yang lain menyesuaikan.
            </p>

            <div className="mt-6 grid gap-6 sm:grid-cols-2">
                <Angka
                    label="Setoran bulanan"
                    nilai={formatRupiah(pmt)}
                    semula={pmt !== basePmt ? formatRupiah(basePmt) : null}
                    utama
                />
                <Angka
                    label="Jangka waktu"
                    nilai={formatDuration(months)}
                    semula={
                        months !== baseMonths ? formatDuration(baseMonths) : null
                    }
                />
            </div>

            <div className="mt-6 space-y-5">
                <Slider
                    label="Jangka waktu"
                    nilai={months}
                    min={1}
                    max={480}
                    step={1}
                    onChange={ubahJangkaWaktu}
                    kiri="1 bulan"
                    kanan="40 tahun"
                />
                <Slider
                    label="Setoran bulanan"
                    nilai={Math.min(pmt, maxPmt)}
                    min={0}
                    max={maxPmt}
                    step={50_000}
                    onChange={ubahSetoran}
                    kiri="Rp 0"
                    kanan={formatRupiah(maxPmt)}
                />
            </div>

            {takTercapai && (
                <p className="mt-4 rounded-lg border-l-2 border-state-warning bg-bg-cardAlt p-3 text-xs leading-relaxed text-text-secondary">
                    Dengan setoran sebesar itu, target tidak tercapai dalam 40
                    tahun — inflasi menaikkan nominal targetnya lebih cepat
                    daripada dana Anda bertumbuh.
                </p>
            )}

            <ProjectionChart data={deret} className="mt-6" />

            {berubah && (
                <div className="mt-5 flex items-center gap-3">
                    <SecondaryButton onClick={reset}>
                        Kembalikan
                    </SecondaryButton>
                    <p className="text-xs text-text-muted">
                        Angka di panel Hasil tetap yang dihitung server.
                    </p>
                </div>
            )}
        </div>
    );
}

function Angka({ label, nilai, semula, utama = false }) {
    return (
        <div>
            <p className="text-xs font-medium uppercase tracking-wider text-text-secondary">
                {label}
            </p>
            <p
                className={
                    'num-tabular mt-1 font-mono font-bold leading-tight ' +
                    (utama
                        ? 'text-3xl text-lime-500'
                        : 'text-2xl text-text-primary')
                }
            >
                {nilai}
            </p>
            {semula && (
                <p className="mt-1 text-xs text-text-muted">semula {semula}</p>
            )}
        </div>
    );
}

function Slider({ label, nilai, min, max, step, onChange, kiri, kanan }) {
    return (
        <div>
            <label className="block text-sm font-medium text-text-secondary">
                {label}
            </label>
            <input
                type="range"
                min={min}
                max={max}
                step={step}
                value={nilai}
                onChange={(e) => onChange(e.target.value)}
                className="mt-2 w-full accent-lime-500"
            />
            <div className="mt-1 flex justify-between text-xs text-text-muted">
                <span>{kiri}</span>
                <span>{kanan}</span>
            </div>
        </div>
    );
}
