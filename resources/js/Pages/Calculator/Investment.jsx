import CalculatorFrame, {
    EmptyResult,
    HeadlineResult,
    ResultRows,
    TenorField,
    tanpaIsianKosong,
    useGalatKalkulator,
} from '@/Components/CalculatorFrame';
import CurrencyInput from '@/Components/CurrencyInput';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import JadikanTujuan from '@/Components/JadikanTujuan';
import ProjectionChart from '@/Components/ProjectionChart';
import TextInput from '@/Components/TextInput';
import { formatDuration, formatRupiah } from '@/utils/format';
import { projectionSeries } from '@/utils/goalCalculator';
import { useForm } from '@inertiajs/react';

/**
 * Kalkulator Investasi (FR-42) — kebalikan arah kalkulator tujuan: setorannya
 * diketahui, nilai akhirnya dicari. Publik (FR-44).
 *
 * Angka hasil dari GoalCalculatorService::projectInvestment(); grafiknya dari
 * projectionSeries(), salinan JS rumus yang sama yang diuji terhadap test
 * vector bersama (CLAUDE.md §6.6) — grafik yang sama dengan kalkulator tujuan.
 */
export default function CalculatorInvestment({ input, result }) {
    const form = useForm({
        initial_amount: input?.initial_amount ?? '',
        monthly_contribution: input?.monthly_contribution ?? '',
        months: input?.months ?? '',
        annual_return_rate: input?.annual_return_rate ?? '',
    });
    const galat = useGalatKalkulator(form);

    function submit(e) {
        e.preventDefault();
        // transform() terpisah, tidak dirantai — lihat Calculator/Goal.jsx.
        form.transform(tanpaIsianKosong);
        form.get(route('calculator.investment'), { preserveScroll: true, preserveState: true });
    }

    return (
        <CalculatorFrame
            title="Kalkulator Investasi"
            subtitle="Jadi berapa uang Anda nanti bila disisihkan rutin tiap bulan."
            onSubmit={submit}
            processing={form.processing}
            resetHref={result ? route('calculator.investment') : null}
            fields={
                <>
                    <div>
                        <InputLabel htmlFor="initial_amount" value="Dana awal" />
                        <CurrencyInput
                            id="initial_amount"
                            className="mt-1.5"
                            placeholder="0"
                            value={form.data.initial_amount}
                            onChange={(v) => form.setData('initial_amount', v)}
                        />
                        <p className="mt-1.5 text-xs text-text-muted">Opsional. Ikut berkembang sejak bulan pertama.</p>
                        <InputError className="mt-1.5" message={galat.initial_amount} />
                    </div>

                    <div>
                        <InputLabel htmlFor="monthly_contribution" value="Setoran bulanan" />
                        <CurrencyInput
                            id="monthly_contribution"
                            className="mt-1.5"
                            placeholder="1.000.000"
                            value={form.data.monthly_contribution}
                            onChange={(v) => form.setData('monthly_contribution', v)}
                        />
                        <InputError className="mt-1.5" message={galat.monthly_contribution} />
                    </div>

                    <TenorField
                        label="Jangka waktu (bulan)"
                        value={form.data.months}
                        onChange={(v) => form.setData('months', v)}
                        error={galat.months}
                        max="720"
                        presets={[1, 3, 5, 10, 20]}
                    />

                    <div>
                        <InputLabel htmlFor="annual_return_rate" value="Imbal hasil (% / tahun)" />
                        <TextInput
                            id="annual_return_rate"
                            type="number"
                            step="0.1"
                            min="0"
                            max="30"
                            className="num-tabular mt-1.5 block w-full"
                            placeholder="8"
                            value={form.data.annual_return_rate}
                            onChange={(e) => form.setData('annual_return_rate', e.target.value)}
                        />
                        <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                            Isi sesuai instrumen yang Anda rencanakan — deposito, obligasi,
                            reksa dana, dan saham punya kisaran yang berbeda jauh. Arus tidak
                            mengisikan angka untuk Anda karena angka inilah yang paling
                            menentukan hasilnya.
                        </p>
                        <InputError className="mt-1.5" message={galat.annual_return_rate} />
                    </div>
                </>
            }
            result={
                result ? (
                    <HasilInvestasi input={input} result={result} />
                ) : (
                    <EmptyResult>
                        Isi setoran bulanan, jangka waktu, dan imbal hasil, lalu tekan Hitung Sekarang.
                    </EmptyResult>
                )
            }
        />
    );
}

function HasilInvestasi({ input, result }) {
    const deret = projectionSeries({
        currentAmount: Number(input.initial_amount || 0),
        months: Number(input.months),
        monthlyContribution: Number(input.monthly_contribution),
        annualReturnRate: Number(input.annual_return_rate),
    });

    return (
        <>
            <HeadlineResult
                label={`Nilai akhir setelah ${formatDuration(result.months)}`}
                value={formatRupiah(result.final_value)}
            />

            <ResultRows
                rows={[
                    ...(result.initial_amount > 0 ? [{ label: 'Dana awal', value: formatRupiah(result.initial_amount) }] : []),
                    { label: 'Total setoran Anda', value: formatRupiah(result.total_contribution) },
                    {
                        label: 'Hasil pengembangan',
                        value: formatRupiah(result.investment_growth),
                        hint: 'Bagian yang datang dari imbal hasil, bukan dari kantong Anda.',
                    },
                ]}
            />

            <ProjectionChart data={deret} className="mt-6" />

            <div className="mt-5 border-t border-border pt-4">
                <p className="text-xs leading-relaxed text-text-muted">
                    Metode: anuitas efektif, setoran di akhir bulan — sama dengan
                    kalkulator tujuan. Imbal hasil tahunan dikonversi menjadi bulanan
                    secara majemuk, bukan dibagi dua belas. Nilai akhir dalam rupiah
                    masa depan, sebelum pajak dan biaya, belum dikurangi inflasi.
                </p>
                <p className="mt-3 text-xs leading-relaxed text-text-muted">
                    Angka di atas adalah simulasi berdasarkan asumsi yang Anda isi
                    sendiri, bukan saran investasi personal. Imbal hasil nyata
                    berfluktuasi dan tidak dijamin.
                </p>
            </div>

            {/*
                Nilai akhirnya menjadi target tujuan. Inflasi 0 karena kalkulator
                ini tidak memakainya — dengan imbal hasil yang sama, form tujuan
                akan menghitung setoran yang sama dengan yang diisi di sini.
                Tanpa setoran bulanan tidak ada yang dikejar, jadi tidak ditawarkan.
            */}
            {Number(input.monthly_contribution) > 0 && (
                <JadikanTujuan
                    params={{
                        target_amount: result.final_value,
                        initial_amount: result.initial_amount,
                        months: result.months,
                        estimated_return_rate: input.annual_return_rate,
                        estimated_inflation_rate: 0,
                    }}
                />
            )}
        </>
    );
}
