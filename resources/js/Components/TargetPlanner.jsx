import CurrencyInput from "@/Components/CurrencyInput";
import InputError from "@/Components/InputError";
import InputLabel from "@/Components/InputLabel";
import PrimaryButton from "@/Components/PrimaryButton";
import SecondaryButton from "@/Components/SecondaryButton";
import TextInput from "@/Components/TextInput";
import { formatDuration, formatRupiah } from "@/utils/format";
import { TARGET_PLANNERS } from "@/utils/targetPlanners";
import { useState } from "react";

/**
 * Shell penentu target (PRD FR-20..22). Menggambar isian dan hasil dari
 * strategi mana pun di utils/targetPlanners.js — komponen ini tidak tahu apa
 * itu pensiun atau dana darurat, dan memang jangan sampai tahu (PRD §6.2.1).
 *
 * `konteks` = isian form yang dibutuhkan strategi (dana awal untuk perkiraan
 * waktu dana darurat). `onPakai(hasil, label)` menerima keluaran `compute` berstatus
 * 'siap'; form yang memutuskan cara mengisikannya.
 */
export default function TargetPlanner({ konteks = {}, onPakai, onTutup }) {
    const [aktif, setAktif] = useState(TARGET_PLANNERS[0].key);
    // Isian tiap penentu disimpan terpisah, supaya berpindah tab tidak
    // menghapus yang sudah diketik di tab lain.
    const [nilai, setNilai] = useState(() =>
        Object.fromEntries(TARGET_PLANNERS.map((p) => [p.key, { ...p.initial }])),
    );

    const penentu = TARGET_PLANNERS.find((p) => p.key === aktif);
    const isian = nilai[aktif];
    const hasil = penentu.compute(isian, konteks);
    const errors = hasil.status === "galat" ? hasil.errors : {};

    const ubah = (kolom, v) =>
        setNilai((semua) => ({ ...semua, [aktif]: { ...semua[aktif], [kolom]: v } }));

    return (
        <section
            // Panel ini berada DI DALAM form tujuan. Tanpa ini, Enter di
            // salah satu isiannya mengirim form tujuan setengah jadi.
            onKeyDown={(e) => {
                if (e.key === "Enter" && e.target.tagName === "INPUT") e.preventDefault();
            }}
            aria-label="Hitung nominal target"
            className="mt-3 space-y-4 rounded-lg border border-border-strong bg-bg-cardAlt p-4"
        >
            <div className="flex flex-wrap gap-2" role="group" aria-label="Jenis tujuan">
                {TARGET_PLANNERS.map((p) => (
                    <button
                        key={p.key}
                        type="button"
                        aria-pressed={p.key === aktif}
                        onClick={() => setAktif(p.key)}
                        className={
                            "rounded-full px-3.5 py-1.5 text-xs font-semibold transition focus:outline-none focus:ring-2 focus:ring-lime-500 " +
                            (p.key === aktif
                                ? "bg-lime-500 text-onPrimary"
                                : "bg-bg-card text-text-secondary hover:text-text-primary")
                        }
                    >
                        {p.label}
                    </button>
                ))}
            </div>

            <p className="text-xs leading-relaxed text-text-muted">{penentu.description}</p>

            <div className="grid gap-4 sm:grid-cols-2">
                {penentu.fields.map((f) => (
                    <Kolom
                        key={`${aktif}.${f.key}`}
                        id={`planner-${aktif}-${f.key}`}
                        field={f}
                        value={isian[f.key]}
                        onChange={(v) => ubah(f.key, v)}
                        error={errors[f.key]}
                    />
                ))}
            </div>

            {hasil.status === "siap" && (
                <div className="rounded-lg border-l-2 border-lime-500 bg-bg-card p-3">
                    <dl className="space-y-1.5 text-sm">
                        {hasil.rincian.map((r) => (
                            <div key={r.istilah} className="flex items-baseline justify-between gap-3">
                                <dt className="text-text-secondary">{r.istilah}</dt>
                                <dd className="num-tabular font-semibold text-text-primary">
                                    {r.jenis === "rupiah" ? formatRupiah(r.nilai) : formatDuration(r.nilai)}
                                </dd>
                            </div>
                        ))}
                    </dl>
                    {hasil.catatan && (
                        <p className="mt-2 text-xs leading-relaxed text-text-muted">{hasil.catatan}</p>
                    )}
                </div>
            )}

            <div className="flex flex-wrap gap-2">
                <PrimaryButton
                    type="button"
                    disabled={hasil.status !== "siap"}
                    onClick={() => onPakai(hasil, penentu.label)}
                >
                    Pakai angka ini
                </PrimaryButton>
                <SecondaryButton type="button" onClick={onTutup}>
                    Tutup
                </SecondaryButton>
            </div>
        </section>
    );
}

function Kolom({ id, field, value, onChange, error }) {
    if (field.type === "choice") {
        return (
            <fieldset className="sm:col-span-2">
                <legend className="block text-sm font-medium text-text-secondary">{field.label}</legend>
                <div className="mt-2 grid gap-2 sm:grid-cols-2">
                    {field.options.map((o) => (
                        <label
                            key={o.value}
                            className={
                                "flex cursor-pointer items-start gap-2 rounded-lg border px-3 py-2 text-sm transition " +
                                (value === o.value
                                    ? "border-lime-500 bg-lime-softBg text-text-primary"
                                    : "border-border-strong text-text-secondary hover:text-text-primary")
                            }
                        >
                            <input
                                type="radio"
                                name={id}
                                value={o.value}
                                checked={value === o.value}
                                onChange={() => onChange(o.value)}
                                className="mt-0.5 accent-lime-500"
                            />
                            <span>
                                {o.label}
                                {o.hint && <span className="block text-xs text-text-muted">{o.hint}</span>}
                            </span>
                        </label>
                    ))}
                </div>
                <InputError message={error} className="mt-2" />
            </fieldset>
        );
    }

    return (
        <div>
            <InputLabel htmlFor={id} value={field.label} />
            {field.type === "currency" ? (
                <CurrencyInput id={id} className="mt-1.5" value={value} onChange={onChange} />
            ) : (
                <div className="mt-1.5 flex items-center gap-2">
                    <TextInput
                        id={id}
                        type="number"
                        min="0"
                        max={field.max}
                        step={field.type === "percent" ? "0.1" : "1"}
                        className="num-tabular block w-full"
                        value={value}
                        onChange={(e) => onChange(e.target.value)}
                    />
                    {field.suffix && (
                        <span className="shrink-0 text-xs text-text-muted">{field.suffix}</span>
                    )}
                </div>
            )}
            {field.hint && <p className="mt-1.5 text-xs text-text-muted">{field.hint}</p>}
            <InputError message={error} className="mt-2" />
        </div>
    );
}
