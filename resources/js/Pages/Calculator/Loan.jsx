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
import TextInput from '@/Components/TextInput';
import { formatRupiah } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Kalkulator Pinjaman / KPR (FR-41). Publik (FR-44).
 *
 * Bunga dihitung r/12 seperti bank, BUKAN konversi efektif seperti kalkulator
 * tujuan — lihat docblock GoalCalculatorService::calculateLoan(). Halaman ini
 * menyebutkannya terbuka di bawah hasil, karena itulah yang menjelaskan
 * kenapa angkanya cocok dengan brosur bank.
 *
 * Tidak ada tombol "Jadikan Tujuan": pinjaman bukan tabungan yang dikejar.
 */
export default function CalculatorLoan({ input, result }) {
    const form = useForm({
        principal: input?.principal ?? '',
        annual_interest_rate: input?.annual_interest_rate ?? '',
        months: input?.months ?? '',
    });

    function submit(e) {
        e.preventDefault();
        // transform() terpisah, tidak dirantai — lihat Calculator/Goal.jsx.
        form.transform(tanpaIsianKosong);
        form.get(route('calculator.loan'), { preserveScroll: true, preserveState: true });
    }

    return (
        <CalculatorFrame
            title="Kalkulator Pinjaman / KPR"
            subtitle="Berapa angsuran tiap bulan, dan berapa yang habis untuk bunga sampai lunas."
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

                    <div>
                        <InputLabel htmlFor="annual_interest_rate" value="Suku bunga (% / tahun)" />
                        <TextInput
                            id="annual_interest_rate"
                            type="number"
                            step="0.01"
                            min="0"
                            max="50"
                            className="num-tabular mt-1.5 block w-full"
                            placeholder="10"
                            value={form.data.annual_interest_rate}
                            onChange={(e) => form.setData('annual_interest_rate', e.target.value)}
                        />
                        <p className="mt-1.5 text-xs text-text-muted">
                            Bunga tetap selama tenor. KPR dengan bunga promo lalu mengambang
                            perlu dihitung per periodenya.
                        </p>
                        <InputError className="mt-1.5" message={form.errors.annual_interest_rate} />
                    </div>

                    <TenorField
                        label="Tenor (bulan)"
                        value={form.data.months}
                        onChange={(v) => form.setData('months', v)}
                        error={form.errors.months}
                        max="360"
                        presets={[1, 5, 10, 15, 20, 30]}
                    />
                </>
            }
            result={
                result ? (
                    <HasilPinjaman result={result} />
                ) : (
                    <EmptyResult>
                        Isi pokok pinjaman, suku bunga, dan tenor, lalu tekan Hitung Sekarang.
                    </EmptyResult>
                )
            }
            below={result && <TabelTahunan result={result} />}
        />
    );
}

function HasilPinjaman({ result }) {
    const bedaTerakhir = result.last_installment !== result.monthly_installment;

    return (
        <>
            <HeadlineResult
                label="Angsuran bulanan"
                value={formatRupiah(result.monthly_installment)}
                note={
                    bedaTerakhir
                        ? `Angsuran terakhir ${formatRupiah(result.last_installment)} — menyerap selisih pembulatan supaya pinjaman lunas tepat nol.`
                        : null
                }
            />

            <ResultRows
                rows={[
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
