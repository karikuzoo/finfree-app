import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import { formatDesimal, keteranganSatuan, parseDesimal } from '@/utils/format';
import { useState } from 'react';

/**
 * Jumlah satuan aset — gram emas, lot saham, unit reksa dana (PRD FR-51).
 *
 * Teks yang diketik disimpan SENDIRI di sini, terpisah dari angka yang
 * dikirim ke form. Kalau isian selalu diformat ulang dari angkanya, koma yang
 * baru diketik ("10,") langsung hilang karena "10," dibaca 10 — dan pecahan
 * gram tidak pernah bisa diketik.
 */
const TEKS = {
    gram: { label: 'Berat emas (gram)', contoh: 'mis. 10,5', catatan: null },
    lot: { label: 'Jumlah lot', contoh: 'mis. 12', catatan: '1 lot = 100 lembar saham.' },
    unit: { label: 'Jumlah unit penyertaan', contoh: 'mis. 1.234,5678', catatan: 'Tertera di laporan atau aplikasi reksa dana Anda.' },
};

/**
 * Valas: satuannya kode mata uang (USD, SGD, …), jadi teksnya dibentuk dari
 * kode itu, bukan dari daftar TEKS.
 */
const teksValas = (kode) => ({
    label: `Jumlah ${kode}`,
    contoh: 'mis. 1.500',
    catatan: 'Saldo dalam mata uang aslinya, sesuai buku tabungan atau aplikasi bank.',
});

export default function UnitsInput({ unit, value, onChange, error, id = 'units', catatanTambahan = null }) {
    const [teks, setTeks] = useState(() => formatDesimal(value));
    const t = TEKS[unit] ?? (/^[A-Z]{3}$/.test(unit ?? '') ? teksValas(unit) : null);

    if (!t) return null;

    return (
        <div>
            <InputLabel htmlFor={id} value={`${t.label} (opsional)`} />
            <input
                id={id}
                type="text"
                inputMode="decimal"
                autoComplete="off"
                value={teks}
                onChange={(e) => {
                    setTeks(e.target.value);
                    onChange(parseDesimal(e.target.value));
                }}
                onBlur={() => {
                    // Dirapikan saat ditinggalkan, bukan saat diketik.
                    const n = parseDesimal(teks);
                    setTeks(n === '' ? '' : formatDesimal(n));
                }}
                placeholder={t.contoh}
                className="num-tabular mt-1.5 block w-full rounded-lg border-border-strong bg-bg-base text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500"
            />
            <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                {[t.catatan, catatanTambahan ?? 'Hanya keterangan — nilai rupiah tetap dasar perhitungan.']
                    .filter(Boolean)
                    .join(' ')}
            </p>
            <InputError message={error} className="mt-2" />
        </div>
    );
}

/** "10,5 gram · ≈ Rp 1.450.000/gram" di bawah nilai kartu (PRD FR-51). */
export function KeteranganSatuan({ rekening, nilai }) {
    const k = keteranganSatuan({ units: rekening.units, unit: rekening.unit, value: nilai });

    if (!k) return null;

    return (
        <p className="num-tabular mt-1 text-xs text-text-secondary">
            {k.jumlah} <span className="text-text-muted">· {k.perSatuan}</span>
        </p>
    );
}
