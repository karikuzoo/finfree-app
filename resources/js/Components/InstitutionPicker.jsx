import AccountBadge from '@/Components/AccountBadge';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import TextInput from '@/Components/TextInput';
import { DAFTAR_PER_JENIS, cariLembaga } from '@/utils/institutions';
import { useState } from 'react';

/**
 * Judul, kelompok, dan contoh isian "Lainnya" per jenis rekening.
 * Tunai tidak ada di sini: ia tidak punya lembaga untuk dipilih.
 */
const PER_JENIS = {
    bank: {
        judul: 'Bank atau dompet digital',
        kelompok: [
            { judul: 'Bank', pilih: (l) => !l.dompet },
            { judul: 'Dompet digital', pilih: (l) => l.dompet },
        ],
        contohLain: 'Nama bank, mis. Bank DKI',
    },
    stock: {
        judul: 'Sekuritas atau aplikasi',
        kelompok: [{ judul: 'Sekuritas & aplikasi saham', pilih: () => true }],
        contohLain: 'Nama sekuritas, mis. Phintraco',
    },
    fund: {
        judul: 'Aplikasi reksa dana',
        kelompok: [{ judul: 'Agen penjual reksa dana', pilih: () => true }],
        contohLain: 'Nama aplikasi atau manajer investasi',
    },
    gold: {
        judul: 'Tempat menyimpan emas',
        kelompok: [{ judul: 'Emas fisik & tabungan emas', pilih: () => true }],
        contohLain: 'mis. UBS, Galeri 24',
    },
};

/**
 * Bidang "lembaga" lengkap untuk form rekening dan form investasi: pemilih
 * berlencana untuk bank, saham, reksa dana, dan emas; isian teks biasa untuk
 * tunai.
 *
 * `key={kind}` pada pemilihnya: ganti jenis berarti daftar lain, dan status
 * "Lainnya terbuka" dari daftar sebelumnya tidak boleh ikut terbawa.
 */
export function InstitutionField({ kind, value, onChange, error, id = 'institution' }) {
    if (!(kind in PER_JENIS)) {
        return (
            <div>
                <InputLabel htmlFor={id} value="Lembaga (opsional)" />
                <TextInput
                    id={id}
                    className="mt-1.5 block w-full"
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={100}
                    placeholder="mis. Dompet, Brankas"
                />
                <InputError message={error} className="mt-2" />
            </div>
        );
    }

    return (
        <div>
            <p id={`${id}-label`} className="mb-2 block text-sm font-medium text-text-secondary">
                {PER_JENIS[kind].judul} (opsional)
            </p>
            <InstitutionPicker key={kind} kind={kind} id={id} value={value} onChange={onChange} />
            <InputError message={error} className="mt-2" />
        </div>
    );
}

/**
 * Pilihan lembaga untuk rekening: bank & dompet digital, sekuritas, aplikasi
 * reksa dana, atau tempat emas — tergantung `kind`.
 *
 * Nilai yang dipertukarkan tetap TEKS nama lembaga ("BCA", "Stockbit") —
 * kolom `institution` tidak berubah, jadi isian lama yang ditulis bebas tetap
 * sah. Lembaga yang tidak ada di daftar ditulis lewat "Lainnya".
 */
export default function InstitutionPicker({ kind = 'bank', value, onChange, id = 'institution' }) {
    const aturan = PER_JENIS[kind] ?? PER_JENIS.bank;
    const daftar = DAFTAR_PER_JENIS[kind] ?? [];
    const dikenal = cariLembaga(value, kind);

    // "Lainnya" terbuka bila isian lama tidak dikenali, atau dipilih pengguna.
    const [lainnya, setLainnya] = useState(Boolean(value) && !dikenal);
    const terpilih = !lainnya && dikenal?.id;

    const pilih = (lembaga) => {
        setLainnya(false);
        onChange(lembaga.nama);
    };

    const kelompok = aturan.kelompok.map((k) => ({ ...k, daftar: daftar.filter(k.pilih) }));

    return (
        <div>
            <div role="radiogroup" aria-labelledby={`${id}-label`} className="space-y-3">
                {kelompok.map((k, i) => (
                    <div key={k.judul}>
                        <p className="mb-1.5 text-[11px] font-semibold uppercase tracking-wide text-text-muted">{k.judul}</p>
                        <div className="grid grid-cols-2 gap-1.5 sm:grid-cols-3">
                            {k.daftar.map((l) => (
                                <button
                                    key={l.id}
                                    type="button"
                                    role="radio"
                                    aria-checked={terpilih === l.id}
                                    onClick={() => pilih(l)}
                                    className={kelasPilihan(terpilih === l.id)}
                                >
                                    <AccountBadge lembaga={l} size="sm" />
                                    <span className="truncate">{l.label ?? l.nama}</span>
                                </button>
                            ))}

                            {/* "Lainnya" di ujung kelompok terakhir. */}
                            {i === kelompok.length - 1 && (
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
                                    <AccountBadge rekening={{ kind }} size="sm" />
                                    <span className="truncate">Lainnya</span>
                                </button>
                            )}
                        </div>
                    </div>
                ))}
            </div>

            {lainnya && (
                <TextInput
                    id={id}
                    className="mt-3 block w-full"
                    value={value ?? ''}
                    onChange={(e) => onChange(e.target.value)}
                    maxLength={100}
                    placeholder={aturan.contohLain}
                    autoFocus
                />
            )}
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
