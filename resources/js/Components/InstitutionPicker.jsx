import AccountBadge from '@/Components/AccountBadge';
import TextInput from '@/Components/TextInput';
import { LEMBAGA, cariLembaga } from '@/utils/institutions';
import { useState } from 'react';

/**
 * Pilihan bank / dompet digital untuk rekening berjenis bank.
 *
 * Nilai yang dipertukarkan tetap TEKS nama lembaga ("BCA") — kolom
 * `institution` tidak berubah, jadi isian lama yang ditulis bebas tetap
 * sah. Bank yang tidak ada di daftar ditulis lewat "Lainnya".
 */
export default function InstitutionPicker({ value, onChange, id = 'institution' }) {
    const dikenal = cariLembaga(value);

    // "Lainnya" terbuka bila isian lama tidak dikenali, atau dipilih pengguna.
    const [lainnya, setLainnya] = useState(Boolean(value) && !dikenal);

    const bank = LEMBAGA.filter((l) => !l.dompet);
    const dompet = LEMBAGA.filter((l) => l.dompet);

    const pilih = (lembaga) => {
        setLainnya(false);
        onChange(lembaga.nama);
    };

    return (
        <div>
            <div role="radiogroup" aria-labelledby={`${id}-label`} className="space-y-3">
                <Kelompok judul="Bank" daftar={bank} terpilih={!lainnya && dikenal?.id} onPilih={pilih} />
                <Kelompok judul="Dompet digital" daftar={dompet} terpilih={!lainnya && dikenal?.id} onPilih={pilih}>
                    <button
                        type="button"
                        role="radio"
                        aria-checked={lainnya}
                        onClick={() => {
                            setLainnya(true);
                            if (dikenal) onChange('');
                        }}
                        className={kelasPilihan(lainnya)}
                    >
                        <AccountBadge rekening={{ kind: 'bank' }} size="sm" />
                        <span className="truncate">Lainnya</span>
                    </button>
                </Kelompok>
            </div>

            {lainnya && (
                <TextInput
                    id={id}
                    className="mt-3 block w-full"
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={100}
                    placeholder="Nama bank, mis. Bank DKI"
                    autoFocus
                />
            )}
        </div>
    );
}

function Kelompok({ judul, daftar, terpilih, onPilih, children }) {
    return (
        <div>
            <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted">{judul}</p>
            <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                {daftar.map((l) => (
                    <button
                        key={l.id}
                        type="button"
                        role="radio"
                        aria-checked={terpilih === l.id}
                        onClick={() => onPilih(l)}
                        className={kelasPilihan(terpilih === l.id)}
                    >
                        <AccountBadge rekening={{ kind: 'bank', institution: l.nama }} size="sm" />
                        <span className="truncate">{l.label ?? l.nama}</span>
                    </button>
                ))}
                {children}
            </div>
        </div>
    );
}

const kelasPilihan = (aktif) =>
    // text-xs: di modal selebar md, tiga kolom text-sm memotong "CIMB Niaga"
    // dan "ShopeePay".
    'flex min-w-0 items-center gap-2 rounded-lg border px-2 py-1.5 text-left text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
    (aktif
        ? 'border-lime-500 bg-lime-softBg text-text-primary'
        : 'border-border text-text-secondary hover:border-border-strong hover:text-text-primary');
