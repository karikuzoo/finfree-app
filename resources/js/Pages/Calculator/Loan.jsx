import CalculatorFrame, {
    EmptyResult,
    HeadlineResult,
    ResultRows,
    TenorField,
    tanpaIsianKosong,
} from '@/Components/CalculatorFrame';
import CurrencyInput from '@/Components/CurrencyInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LoanChart from '@/Components/LoanChart';
import LoanHealthCard from '@/Components/LoanHealthCard';
import TextInput from '@/Components/TextInput';
import { formatRupiah } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

const JENIS_BUNGA = [
    { value: 'fixed', label: 'Tetap', hint: 'Satu bunga sepanjang tenor.' },
    { value: 'fix_float', label: 'Tetap lalu mengambang', hint: 'Bunga promo beberapa tahun, lalu mengikuti bunga pasar — paling umum di KPR.' },
    { value: 'floating', label: 'Mengambang', hint: 'Bisa berubah kapan saja. Diuji dengan bunga bila naik.' },
];

/**
 * Kalkulator Pinjaman / KPR (FR-41). Publik (FR-44).
 *
 * Bunga dihitung r/12 seperti bank, BUKAN konversi efektif seperti kalkulator
 * tujuan — lihat docblock GoalCalculatorService::calculateLoan(). Halaman ini
 * menyebutkannya terbuka di bawah hasil, karena itulah yang menjelaskan
 * kenapa angkanya cocok dengan brosur bank.
 *
 * Jenis bunga (tetap / tetap lalu mengambang / mengambang) dan cek kesehatan
 * cicilan dijelaskan di UtilityCalculatorController::loan(). Semua angka,
 * termasuk label sehat/waspada/berisiko, datang dari server; halaman ini
 * hanya menampilkan.
 *
 * Tidak ada tombol "Jadikan Tujuan": pinjaman bukan tabungan yang dikejar.
 */
export default function CalculatorLoan({ input, result, stress = null, health = null }) {
    const form = useForm({
        principal: input?.principal ?? '',
        annual_interest_rate: input?.annual_interest_rate ?? '',
        months: input?.months ?? '',
        rate_type: input?.rate_type ?? 'fixed',
        fixed_years: input?.fixed_years ?? '',
        floating_rate: input?.floating_rate ?? '',
        monthly_income: input?.monthly_income ?? '',
        other_installments: input?.other_installments ?? '',
        monthly_expenses: input?.monthly_expenses ?? '',
        annual_taxes: input?.annual_taxes ?? '',
    });

    // Bagian cek kesehatan terbuka sendiri bila pendapatan sudah pernah diisi
    // (tautan yang dibagikan, atau sesudah menekan Hitung).
    const [cekKesehatan, setCekKesehatan] = useState(Boolean(input?.monthly_income));

    function submit(e) {
        e.preventDefault();
        // transform() terpisah, tidak dirantai — lihat Calculator/Goal.jsx.
        // Isian yang tidak berlaku untuk jenis bunganya dibuang, supaya URL
        // hasilnya tidak membawa angka yang tidak dipakai.
        form.transform((data) => {
            const kirim = { ...data };
            if (kirim.rate_type === 'fixed') {
                delete kirim.fixed_years;
                delete kirim.floating_rate;
            }
            if (kirim.rate_type === 'floating') delete kirim.fixed_years;
            if (!cekKesehatan) {
                for (const k of ['monthly_income', 'other_installments', 'monthly_expenses', 'annual_taxes']) delete kirim[k];
            }
            return tanpaIsianKosong(kirim);
        });
        form.get(route('calculator.loan'), { preserveScroll: true, preserveState: true });
    }

    const jenis = form.data.rate_type;

    return (
        <CalculatorFrame
            title="Kalkulator Pinjaman / KPR"
            subtitle="Berapa angsuran tiap bulan, berapa yang habis untuk bunga, dan apakah cicilannya sehat untuk keuangan Anda."
            onSubmit={submit}
            processing={form.processing}
            resetHref={result ? route('calculator.loan') : null}
            fields={
                <>
                    <div>
                        <InputLabel htmlFor="principal" value="Pokok pinjaman" />
                        <CurrencyInput
                            id="principal"
                            className="mt-1.5"
                            placeholder="500.000.000"
                            value={form.data.principal}
                            onChange={(v) => form.setData('principal', v)}
                        />
                        <p className="mt-1.5 text-xs text-text-muted">
                            Harga dikurangi uang muka — jumlah yang benar-benar dipinjam.
                        </p>
                        <InputError className="mt-1.5" message={form.errors.principal} />
                    </div>

                    <fieldset>
                        <legend className="block text-sm font-medium text-text-secondary">Jenis bunga</legend>
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            {JENIS_BUNGA.map((j) => (
                                <button
                                    key={j.value}
                                    type="button"
                                    aria-pressed={jenis === j.value}
                                    onClick={() => form.setData('rate_type', j.value)}
                                    className={
                                        'rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
                                        (jenis === j.value
                                            ? 'border-lime-500 bg-lime-softBg text-lime-500'
                                            : 'border-border-strong text-text-muted hover:text-text-primary')
                                    }
                                >
                                    {j.label}
                                </button>
                            ))}
                        </div>
                        <p className="mt-1.5 text-xs text-text-muted">
                            {JENIS_BUNGA.find((j) => j.value === jenis)?.hint}
                        </p>
                    </fieldset>

                    <div className={jenis === 'fixed' ? '' : 'grid gap-5 sm:grid-cols-2'}>
                        <IsianPersen
                            id="annual_interest_rate"
                            label={jenis === 'fixed' ? 'Suku bunga (% / tahun)' : jenis === 'fix_float' ? 'Bunga tetap (% / tahun)' : 'Bunga sekarang (% / tahun)'}
                            placeholder={jenis === 'fix_float' ? '5' : '10'}
                            value={form.data.annual_interest_rate}
                            onChange={(v) => form.setData('annual_interest_rate', v)}
                            error={form.errors.annual_interest_rate}
                        />

                        {jenis !== 'fixed' && (
                            <IsianPersen
                                id="floating_rate"
                                label={jenis === 'fix_float' ? 'Bunga mengambang (% / tahun)' : 'Bunga bila naik (% / tahun)'}
                                placeholder="11"
                                value={form.data.floating_rate}
                                onChange={(v) => form.setData('floating_rate', v)}
                                error={form.errors.floating_rate}
                            />
                        )}
                    </div>

                    {jenis === 'fix_float' && (
                        <div>
                            <InputLabel htmlFor="fixed_years" value="Lama bunga tetap (tahun)" />
                            <TextInput
                                id="fixed_years"
                                type="number"
                                min="1"
                                max="30"
                                className="num-tabular mt-1.5 block w-full"
                                placeholder="3"
                                value={form.data.fixed_years}
                                onChange={(e) => form.setData('fixed_years', e.target.value)}
                            />
                            <InputError className="mt-1.5" message={form.errors.fixed_years} />
                        </div>
                    )}

                    {jenis !== 'fixed' && (
                        <p className="-mt-2 text-xs leading-relaxed text-text-muted">
                            Bunga mengambang tidak bisa diketahui sekarang. Bila brosurnya tidak
                            menyebut, perkiraan yang wajar adalah bunga tetap ditambah 2–3 poin.
                        </p>
                    )}

                    <TenorField
                        label="Tenor (bulan)"
                        value={form.data.months}
                        onChange={(v) => form.setData('months', v)}
                        error={form.errors.months}
                        max="360"
                        presets={[1, 5, 10, 15, 20, 30]}
                    />

                    <div className="rounded-lg border border-border p-4">
                        <div className="flex flex-wrap items-center justify-between gap-2">
                            <div>
                                <p className="text-sm font-semibold text-text-primary">Cek kesehatan cicilan</p>
                                <p className="mt-0.5 text-xs text-text-muted">Opsional — apakah angsurannya tertanggung penghasilan Anda.</p>
                            </div>
                            <button
                                type="button"
                                onClick={() => setCekKesehatan((b) => !b)}
                                aria-expanded={cekKesehatan}
                                className="rounded-md text-xs font-semibold text-lime-500 transition hover:text-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                            >
                                {cekKesehatan ? 'Lewati' : 'Isi data keuangan'}
                            </button>
                        </div>

                        {cekKesehatan && (
                            <div className="mt-4 space-y-4">
                                <IsianRupiah
                                    id="monthly_income"
                                    label="Pendapatan bersih per bulan"
                                    hint="Gaji dan penghasilan tetap lain yang diterima, setelah potongan. Bila berdua, gabungkan."
                                    value={form.data.monthly_income}
                                    onChange={(v) => form.setData('monthly_income', v)}
                                    error={form.errors.monthly_income}
                                />
                                <IsianRupiah
                                    id="other_installments"
                                    label="Cicilan lain per bulan"
                                    hint="Kendaraan, kartu kredit, paylater, pinjaman lain. Kosongkan bila tidak ada."
                                    value={form.data.other_installments}
                                    onChange={(v) => form.setData('other_installments', v)}
                                    error={form.errors.other_installments}
                                />
                                <IsianRupiah
                                    id="monthly_expenses"
                                    label="Pengeluaran rutin per bulan"
                                    hint="Makan, transportasi, listrik, sekolah, dan lain-lain — di luar cicilan."
                                    value={form.data.monthly_expenses}
                                    onChange={(v) => form.setData('monthly_expenses', v)}
                                    error={form.errors.monthly_expenses}
                                />
                                <IsianRupiah
                                    id="annual_taxes"
                                    label="Pajak tahunan"
                                    hint="Total pajak yang dibayar setahun sekali: PBB rumah, pajak kendaraan (STNK), dan lainnya. Dihitung per bulan di hasil."
                                    value={form.data.annual_taxes}
                                    onChange={(v) => form.setData('annual_taxes', v)}
                                    error={form.errors.annual_taxes}
                                />
                            </div>
                        )}
                    </div>
                </>
            }
            result={
                result ? (
                    <HasilPinjaman result={result} stress={stress} input={input} />
                ) : (
                    <EmptyResult>
                        Isi pokok pinjaman, suku bunga, dan tenor, lalu tekan Hitung Sekarang.
                    </EmptyResult>
                )
            }
            below={
                result && (
                    <div className="space-y-6">
                        {health && <LoanHealthCard health={health} />}
                        <TabelTahunan result={result} />
                    </div>
                )
            }
        />
    );
}

function IsianPersen({ id, label, placeholder, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                type="number"
                step="0.01"
                min="0"
                max="50"
                className="num-tabular mt-1.5 block w-full"
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            <InputError className="mt-1.5" message={error} />
        </div>
    );
}

function IsianRupiah({ id, label, hint, value, onChange, error }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <CurrencyInput id={id} className="mt-1.5" placeholder="0" value={value} onChange={onChange} />
            {hint && <p className="mt-1.5 text-xs text-text-muted">{hint}</p>}
            <InputError className="mt-1.5" message={error} />
        </div>
    );
}

function HasilPinjaman({ result, stress, input }) {
    const bedaTerakhir = result.last_installment !== result.monthly_installment;
    const tahapKedua = result.installment_after_float !== null && result.installment_after_float !== undefined;

    return (
        <>
            <HeadlineResult
                label={tahapKedua ? `Angsuran ${result.fixed_months / 12} tahun pertama` : 'Angsuran bulanan'}
                value={formatRupiah(result.monthly_installment)}
                note={
                    bedaTerakhir && !tahapKedua
                        ? `Angsuran terakhir ${formatRupiah(result.last_installment)} — menyerap selisih pembulatan supaya pinjaman lunas tepat nol.`
                        : null
                }
            />

            <ResultRows
                rows={[
                    ...(tahapKedua
                        ? [{
                              label: `Angsuran setelah bunga mengambang (${input.floating_rate}%)`,
                              value: formatRupiah(result.installment_after_float),
                              hint: `Mulai bulan ke-${result.fixed_months + 1}, dihitung ulang dari sisa pokok. Naik ${formatRupiah(result.installment_after_float - result.monthly_installment)} per bulan.`,
                          }]
                        : []),
                    ...(stress
                        ? [{
                              label: `Bila bunga naik ke ${input.floating_rate}%`,
                              value: formatRupiah(stress.monthly_installment),
                              hint: `Skenario uji: bunga naik sejak awal. Total bunganya ${formatRupiah(stress.total_interest)}.`,
                          }]
                        : []),
                    { label: 'Pokok pinjaman', value: formatRupiah(result.principal) },
                    {
                        label: 'Total bunga',
                        value: formatRupiah(result.total_interest),
                        hint: 'Biaya meminjam — di luar provisi, asuransi, dan biaya lain dari bank.',
                    },
                    { label: 'Total pembayaran', value: formatRupiah(result.total_payment) },
                ]}
            />

            <LoanChart series={result.series} className="mt-6" />

            <div className="mt-5 border-t border-border pt-4">
                <p className="text-xs leading-relaxed text-text-muted">
                    Metode: anuitas, angsuran tetap di akhir bulan. Bunga bulanan =
                    suku bunga tahunan dibagi dua belas — cara yang sama dengan
                    simulasi KPR bank, supaya angkanya bisa dicocokkan. Bunga tiap
                    bulan dihitung dari sisa pokok, jadi porsi bunga besar di awal dan
                    mengecil menjelang lunas.
                    {tahapKedua && ' Saat bunga tetap berakhir, angsuran dihitung ulang dari sisa pokok dan sisa tenor.'}
                </p>
                <p className="mt-3 text-xs leading-relaxed text-text-muted">
                    Angka di atas adalah simulasi, bukan penawaran pinjaman. Angsuran
                    sebenarnya mengikuti perjanjian dengan pemberi pinjaman.
                </p>
            </div>
        </>
    );
}


/**
 * Rincian per tahun. Tertutup secara bawaan: untuk tenor 30 tahun tabelnya
 * 30 baris, dan kebanyakan orang cukup dengan angsuran dan grafiknya.
 */
function TabelTahunan({ result }) {
    const [terbuka, setTerbuka] = useState(false);

    return (
        <div className="rounded-card border border-border bg-bg-card p-6">
            <div className="flex flex-wrap items-center justify-between gap-3">
                <h2 className="text-base font-semibold text-text-primary">Rincian per tahun</h2>
                <button
                    type="button"
                    onClick={() => setTerbuka((t) => !t)}
                    aria-expanded={terbuka}
                    className="rounded-md text-sm font-semibold text-lime-500 transition hover:text-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500"
                >
                    {terbuka ? 'Sembunyikan' : 'Tampilkan'}
                </button>
            </div>

            {terbuka && (
                <div className="mt-4 overflow-x-auto">
                    <table className="w-full min-w-[28rem] text-sm">
                        <thead>
                            <tr className="border-b border-border text-left text-xs text-text-muted">
                                <th className="py-2 pr-3 font-medium">Tahun</th>
                                <th className="py-2 pr-3 text-right font-medium">Pokok dibayar</th>
                                <th className="py-2 pr-3 text-right font-medium">Bunga dibayar</th>
                                <th className="py-2 text-right font-medium">Sisa pokok</th>
                            </tr>
                        </thead>
                        <tbody className="num-tabular">
                            {result.yearly.map((baris) => (
                                <tr key={baris.year} className="border-b border-border/60 last:border-0">
                                    <td className="py-2 pr-3 text-text-secondary">{baris.year}</td>
                                    <td className="py-2 pr-3 text-right text-text-primary">{formatRupiah(baris.principal_paid)}</td>
                                    <td className="py-2 pr-3 text-right text-text-primary">{formatRupiah(baris.interest_paid)}</td>
                                    <td className="py-2 text-right text-text-primary">{formatRupiah(baris.balance)}</td>
                                </tr>
                            ))}
                        </tbody>
                    </table>
                </div>
            )}
        </div>
    );
}
