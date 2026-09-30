import InputError from '@/Components/InputError';
import TextInput from '@/Components/TextInput';

const MAKS_JENJANG = 10;

/** Dua baris kosong — bentuk paling umum: bunga promo, lalu mengambang. */
export const jenjangAwal = () => [
    { until_year: '', rate: '', floating: false },
    { until_year: '', rate: '', floating: true },
];

/**
 * Isian bunga berjenjang (PRD FR-86): tiap baris "bunga X% sampai tahun
 * ke-N". Tahun MULAI tiap baris tidak diketik — ia selalu satu tahun sesudah
 * batas baris sebelumnya — dan baris terakhir otomatis berlaku sampai tenor
 * habis. Dengan begitu tidak ada tahun yang terlewat atau tumpang tindih;
 * yang tersisa untuk diperiksa server hanya batas yang mundur atau
 * melewati tenor (UtilityCalculatorController::tiersToMonths).
 *
 * "Perkiraan" menandai bunga yang belum pasti — biasanya bunga mengambang
 * sesudah masa promo. Hanya keterangan di hasil; hitungannya sama.
 */
export default function TierInput({ tiers, onChange, months, errors = {} }) {
    const tahunTenor = Number(months) > 0 ? Math.ceil(Number(months) / 12) : null;

    const ubah = (k, kunci, nilai) => onChange(tiers.map((t, i) => (i === k ? { ...t, [kunci]: nilai } : t)));

    const tambah = () => {
        // Baris baru disisipkan SEBELUM baris terakhir: baris terakhir tetap
        // yang berlaku sampai akhir tenor, dan biasanya itu bunga mengambang.
        const baru = { until_year: '', rate: '', floating: false };
        onChange([...tiers.slice(0, -1), baru, tiers.at(-1)]);
    };

    const hapus = (k) => onChange(tiers.filter((_, i) => i !== k));

    return (
        <fieldset>
            <legend className="block text-sm font-medium text-text-secondary">Jenjang bunga</legend>

            <ol className="mt-2 space-y-2">
                {tiers.map((t, k) => {
                    const terakhir = k === tiers.length - 1;
                    const dari = k === 0 ? 1 : Number(tiers[k - 1].until_year) + 1 || null;
                    const galatBatas = errors[`tiers.${k}.until_year`];
                    const galatBunga = errors[`tiers.${k}.rate`];

                    return (
                        <li key={k} className="rounded-lg border border-border bg-bg-cardAlt p-3">
                            <div className="flex flex-wrap items-center gap-x-2 gap-y-2 text-sm text-text-secondary">
                                <span className="num-tabular">{dari ? `Tahun ${dari}` : 'Lalu'}</span>
                                {terakhir ? (
                                    <span>
                                        sampai {tahunTenor ? `tahun ${tahunTenor}` : 'akhir tenor'}
                                    </span>
                                ) : (
                                    <label className="flex items-center gap-2">
                                        sampai tahun
                                        <TextInput
                                            type="number"
                                            min={dari ?? 1}
                                            max="30"
                                            aria-label={`Jenjang ${k + 1}: sampai tahun ke`}
                                            className="num-tabular w-16 py-1.5 text-sm"
                                            value={t.until_year}
                                            onChange={(e) => ubah(k, 'until_year', e.target.value)}
                                        />
                                    </label>
                                )}

                                <label className="ml-auto flex items-center gap-2">
                                    bunga
                                    <TextInput
                                        type="number"
                                        step="0.01"
                                        min="0"
                                        max="50"
                                        aria-label={`Jenjang ${k + 1}: bunga (% per tahun)`}
                                        className="num-tabular w-20 py-1.5 text-sm"
                                        value={t.rate}
                                        onChange={(e) => ubah(k, 'rate', e.target.value)}
                                    />
                                    %
                                </label>
                            </div>

                            <div className="mt-2 flex flex-wrap items-center justify-between gap-2">
                                <label className="flex items-center gap-2 text-xs text-text-muted">
                                    <input
                                        type="checkbox"
                                        checked={Boolean(t.floating)}
                                        onChange={(e) => ubah(k, 'floating', e.target.checked)}
                                        className="rounded border-border-strong bg-bg-card text-lime-500 focus:ring-lime-500"
                                    />
                                    Perkiraan (bunga mengambang)
                                </label>

                                {tiers.length > 2 && (
                                    <button
                                        type="button"
                                        onClick={() => hapus(k)}
                                        aria-label={`Hapus jenjang ${k + 1}`}
                                        className="rounded-md text-xs font-medium text-text-muted transition hover:text-state-danger focus:outline-none focus:ring-2 focus:ring-lime-500"
                                    >
                                        Hapus
                                    </button>
                                )}
                            </div>

                            <InputError className="mt-1.5" message={galatBatas || galatBunga} />
                        </li>
                    );
                })}
            </ol>

            {tiers.length < MAKS_JENJANG && (
                <button
                    type="button"
                    onClick={tambah}
                    className="mt-2 rounded-md text-xs font-semibold text-lime-500 transition hover:text-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    + Tambah jenjang
                </button>
            )}

            <InputError className="mt-1.5" message={errors.tiers} />

            <p className="mt-2 text-xs leading-relaxed text-text-muted">
                Salin dari brosur bank. Untuk bunga yang belum pasti sesudah masa promo,
                perkiraan yang wajar adalah bunga promo ditambah 2–3 poin.
            </p>
        </fieldset>
    );
}
