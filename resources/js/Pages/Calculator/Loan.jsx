import CalculatorFrame, {
    EmptyResult,
    HeadlineResult,
    ResultRows,
    TenorField,
    tanpaIsianKosong,
    useGalatKalkulator,
} from '@/Components/CalculatorFrame';
import CurrencyInput from '@/Components/CurrencyInput';
import JadikanTujuan from '@/Components/JadikanTujuan';
import InputError from '@/Components/InputError';
import InputLabel from '@/Components/InputLabel';
import LoanChart from '@/Components/LoanChart';
import LoanHealthCard from '@/Components/LoanHealthCard';
import TierInput, { jenjangAwal } from '@/Components/TierInput';
import TextInput from '@/Components/TextInput';
import { formatRupiah } from '@/utils/format';
import { useForm } from '@inertiajs/react';
import { useState } from 'react';

const JENIS_BUNGA = [
    { value: 'fixed', label: 'Tetap', hint: 'Satu bunga sepanjang tenor.' },
    {
        value: 'tiered',
        label: 'Berjenjang',
        hint: 'Bunga berubah di tahun-tahun tertentu — termasuk bunga promo lalu mengambang, bentuk paling umum di KPR.',
    },
    { value: 'floating', label: 'Mengambang', hint: 'Bisa berubah kapan saja. Diuji dengan bunga bila naik.' },
];

const DATA_KEUANGAN = ['monthly_income', 'income_growth', 'other_installments', 'monthly_expenses', 'annual_taxes'];

/** Isian mode "dari harga rumah" — tidak dikirim saat pokok diisi langsung. */
const ISIAN_HARGA = ['property_price', 'down_payment', 'down_payment_unit', 'closing_costs'];

const CARA_POKOK = [
    { value: 'price', label: 'Dari harga rumah' },
    { value: 'direct', label: 'Pokok langsung' },
];

/**
 * Pratinjau pokok dari harga dan DP, untuk dibaca sambil mengetik. Hanya
 * tampilan — pokok yang dihitung tetap dari server
 * (UtilityCalculatorController::purchase), dengan pembulatan yang sama.
 */
export function pratinjauPokok({ property_price, down_payment, down_payment_unit }) {
    const harga = Number(property_price) || 0;
    if (!(harga > 0)) return null;

    const isi = Number(down_payment) || 0;
    const dp = down_payment_unit === 'percent' ? Math.round((harga * isi) / 100) : isi;

    return { dp, persen: (dp / harga) * 100, pokok: harga - dp };
}

const tahunKe = (bulan) => Math.floor((bulan - 1) / 12) + 1;

/** "Tahun 1", "Tahun 2–4", "Tahun 11–20" dari rentang bulan sebuah jenjang. */
export function labelRentang({ from_month, to_month }) {
    const dari = tahunKe(from_month);
    const sampai = tahunKe(to_month);

    return dari === sampai ? `Tahun ${dari}` : `Tahun ${dari}–${sampai}`;
}

/**
 * Kalkulator Pinjaman / KPR (FR-41, FR-86). Publik (FR-44).
 *
 * Bunga dihitung r/12 seperti bank, BUKAN konversi efektif seperti kalkulator
 * tujuan — lihat docblock GoalCalculatorService::calculateLoan(). Halaman ini
 * menyebutkannya terbuka di bawah hasil, karena itulah yang menjelaskan
 * kenapa angkanya cocok dengan brosur bank.
 *
 * Jenis bunga (tetap / berjenjang / mengambang) dan cek kesehatan cicilan
 * dijelaskan di UtilityCalculatorController::loan(). Semua angka, termasuk
 * angsuran tiap jenjang dan label sehat/waspada/berisiko, datang dari server;
 * halaman ini hanya menampilkan.
 *
 * Pokok bisa diisi langsung, atau dari harga rumah dikurangi uang muka
 * (`principal_mode`). Formulir baru mulai dari harga rumah — itulah yang
 * diketahui orang yang membuka kalkulator KPR — sedangkan tautan lama tanpa
 * `principal_mode` tetap dibuka sebagai pokok langsung.
 *
 * "Jadikan Tujuan" hanya untuk UANG TUNAI saat akad (DP + biaya akad), bukan
 * pinjamannya: pinjaman bukan tabungan yang dikejar, tetapi DP-nya iya.
 */
export default function CalculatorLoan({ input, result, stress = null, health = null, purchase = null }) {
    const form = useForm({
        principal_mode: input ? (input.principal_mode ?? 'direct') : 'price',
        principal: input?.principal ?? '',
        property_price: input?.property_price ?? '',
        down_payment: input?.down_payment ?? '',
        down_payment_unit: input?.down_payment_unit ?? 'amount',
        closing_costs: input?.closing_costs ?? '',
        annual_interest_rate: input?.annual_interest_rate ?? '',
        months: input?.months ?? '',
        rate_type: input?.rate_type ?? 'fixed',
        tiers: input?.tiers?.length
            ? input.tiers.map((t) => ({ until_year: t.until_year ?? '', rate: t.rate ?? '', floating: ['1', 1, true, 'true'].includes(t.floating) }))
            : jenjangAwal(),
        floating_rate: input?.floating_rate ?? '',
        // Data keuangan selalu mulai kosong: ia tidak pernah datang dari alamat
        // maupun dari server (lihat submit). Sesudah POST, isiannya bertahan
        // di layar karena preserveState.
        monthly_income: '',
        income_growth: '',
        other_installments: '',
        monthly_expenses: '',
        annual_taxes: '',
    });
    const galat = useGalatKalkulator(form);

    // Bagian cek kesehatan terbuka sendiri bila hasilnya ada. Data keuangan
    // tidak pernah datang dari alamat, jadi sesudah dimuat ulang ia tertutup.
    const [cekKesehatan, setCekKesehatan] = useState(Boolean(health));

    /** Isian pinjaman saja, siap jadi query alamat. */
    function isianPinjaman(data) {
        const kirim = { ...data };
        DATA_KEUANGAN.forEach((k) => delete kirim[k]);

        // Mode bawaan (`direct`) tidak ditulis ke alamat, supaya alamatnya
        // sama dengan tautan dari sebelum mode harga rumah ada.
        if (kirim.principal_mode === 'price') {
            delete kirim.principal;
        } else {
            ISIAN_HARGA.forEach((k) => delete kirim[k]);
            delete kirim.principal_mode;
        }

        if (kirim.rate_type === 'tiered') {
            delete kirim.annual_interest_rate;
            // Baris terakhir berlaku sampai tenor habis — batas tahunnya
            // tidak dikirim. `floating` sebagai 1/0: aturan `boolean`
            // Laravel tidak menerima string "true" dari query.
            kirim.tiers = kirim.tiers.map((t, k, semua) => {
                const baris = { rate: t.rate, floating: t.floating ? 1 : 0 };
                if (k < semua.length - 1) baris.until_year = t.until_year;
                return baris;
            });
        } else {
            delete kirim.tiers;
        }

        if (kirim.rate_type !== 'floating') delete kirim.floating_rate;

        return tanpaIsianKosong(kirim);
    }

    /**
     * Dua jalan, tergantung cek kesehatan:
     *
     * - Tanpa cek kesehatan: GET, semua isian pinjaman di alamat.
     * - Dengan cek kesehatan: POST ke alamat YANG SAMA (isian pinjaman tetap
     *   di query), data keuangan di BADAN permintaan. Data pribadi tidak
     *   pernah masuk alamat — tidak tersimpan di riwayat browser, tidak ikut
     *   tersalin saat tautan dibagikan, tidak tercatat di log akses server.
     *   Memuat ulang halaman menampilkan hitungan pinjamannya saja.
     *
     * transform() terpisah, tidak dirantai — lihat Calculator/Goal.jsx.
     */
    function submit(e) {
        e.preventDefault();

        const pinjaman = isianPinjaman(form.data);
        const opsi = {
            preserveScroll: true,
            preserveState: true,
            // `indices` membuat daftar jenjang terkirim sebagai tiers[0][rate]=…
            // yang dibaca PHP sebagai larik baris. Bawaannya (`brackets`,
            // tiers[][rate]) membuat PHP memecah tiap isian jadi baris sendiri.
            queryStringArrayFormat: 'indices',
        };

        if (cekKesehatan) {
            form.transform((data) => tanpaIsianKosong(Object.fromEntries(DATA_KEUANGAN.map((k) => [k, data[k]]))));
            form.post(route('calculator.loan.health', pinjaman), opsi);
        } else {
            form.transform(isianPinjaman);
            form.get(route('calculator.loan'), opsi);
        }
    }

    const jenis = form.data.rate_type;
    const dariHarga = form.data.principal_mode === 'price';
    const pratinjau = dariHarga ? pratinjauPokok(form.data) : null;

    return (
        <CalculatorFrame
            title="Kalkulator Pinjaman / KPR"
            subtitle="Berapa angsuran tiap bulan, berapa yang habis untuk bunga, dan apakah cicilannya sehat untuk keuangan Anda."
            onSubmit={submit}
            processing={form.processing}
            resetHref={result ? route('calculator.loan') : null}
            fields={
                <>
                    <fieldset>
                        <legend className="block text-sm font-medium text-text-secondary">Isi pinjaman lewat</legend>
                        <div className="mt-1.5 flex flex-wrap gap-1.5">
                            {CARA_POKOK.map((c) => (
                                <Pil
                                    key={c.value}
                                    aktif={form.data.principal_mode === c.value}
                                    onClick={() => form.setData('principal_mode', c.value)}
                                >
                                    {c.label}
                                </Pil>
                            ))}
                        </div>
                    </fieldset>

                    {dariHarga ? (
                        <>
                            <IsianRupiah
                                id="property_price"
                                label="Harga rumah"
                                placeholder="600.000.000"
                                value={form.data.property_price}
                                onChange={(v) => form.setData('property_price', v)}
                                error={galat.property_price}
                            />

                            <div>
                                <div className="flex flex-wrap items-center justify-between gap-2">
                                    <InputLabel htmlFor="down_payment" value="Uang muka (DP)" />
                                    <div className="flex gap-1" role="group" aria-label="Satuan uang muka">
                                        <Pil
                                            kecil
                                            aktif={form.data.down_payment_unit === 'amount'}
                                            onClick={() => form.setData((d) => ({ ...d, down_payment_unit: 'amount', down_payment: '' }))}
                                        >
                                            Rp
                                        </Pil>
                                        <Pil
                                            kecil
                                            aktif={form.data.down_payment_unit === 'percent'}
                                            onClick={() => form.setData((d) => ({ ...d, down_payment_unit: 'percent', down_payment: '' }))}
                                        >
                                            %
                                        </Pil>
                                    </div>
                                </div>
                                {form.data.down_payment_unit === 'percent' ? (
                                    <TextInput
                                        id="down_payment"
                                        type="number"
                                        step="0.1"
                                        min="0"
                                        max="100"
                                        className="num-tabular mt-1.5 block w-full"
                                        placeholder="20"
                                        value={form.data.down_payment}
                                        onChange={(e) => form.setData('down_payment', e.target.value)}
                                    />
                                ) : (
                                    <CurrencyInput
                                        id="down_payment"
                                        className="mt-1.5"
                                        placeholder="120.000.000"
                                        value={form.data.down_payment}
                                        onChange={(v) => form.setData('down_payment', v)}
                                    />
                                )}
                                <p className="mt-1.5 text-xs text-text-muted">
                                    Sudah dibayar atau akan dibayar saat akad. Kosongkan bila tanpa DP.
                                </p>
                                <InputError className="mt-1.5" message={galat.down_payment} />
                            </div>

                            <IsianRupiah
                                id="closing_costs"
                                label="Biaya akad & lainnya (opsional)"
                                hint="Provisi, appraisal, notaris, BPHTB, asuransi. Kosongkan bila ditanggung developer. Tidak menambah pinjaman — dibayar tunai bersama DP."
                                value={form.data.closing_costs}
                                onChange={(v) => form.setData('closing_costs', v)}
                                error={galat.closing_costs}
                            />

                            {pratinjau && (
                                <p className="rounded-lg border-l-2 border-lime-500 bg-bg-cardAlt px-3 py-2 text-xs leading-relaxed text-text-secondary">
                                    Pokok pinjaman{' '}
                                    <span className="num-tabular font-semibold text-text-primary">
                                        {formatRupiah(Math.max(0, pratinjau.pokok))}
                                    </span>
                                    {pratinjau.dp > 0 && (
                                        <>
                                            {' '}— DP {formatRupiah(pratinjau.dp)} ({formatPersen(pratinjau.persen)} dari harga)
                                        </>
                                    )}
                                    .
                                </p>
                            )}
                        </>
                    ) : (
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
                            <InputError className="mt-1.5" message={galat.principal} />
                        </div>
                    )}

                    <TenorField
                        label="Tenor (bulan)"
                        value={form.data.months}
                        onChange={(v) => form.setData('months', v)}
                        error={galat.months}
                        max="360"
                        presets={[1, 5, 10, 15, 20, 30]}
                    />

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
                        <InputError className="mt-1.5" message={galat.rate_type} />
                    </fieldset>

                    {jenis === 'tiered' ? (
                        <TierInput
                            tiers={form.data.tiers}
                            onChange={(t) => form.setData('tiers', t)}
                            months={form.data.months}
                            errors={galat}
                        />
                    ) : (
                        <div className={jenis === 'floating' ? 'grid gap-5 sm:grid-cols-2' : ''}>
                            <IsianPersen
                                id="annual_interest_rate"
                                label={jenis === 'fixed' ? 'Suku bunga (% / tahun)' : 'Bunga sekarang (% / tahun)'}
                                placeholder="10"
                                value={form.data.annual_interest_rate}
                                onChange={(v) => form.setData('annual_interest_rate', v)}
                                error={galat.annual_interest_rate}
                            />
                            {jenis === 'floating' && (
                                <IsianPersen
                                    id="floating_rate"
                                    label="Bunga bila naik (% / tahun)"
                                    placeholder="12"
                                    value={form.data.floating_rate}
                                    onChange={(v) => form.setData('floating_rate', v)}
                                    error={galat.floating_rate}
                                />
                            )}
                        </div>
                    )}

                    {jenis === 'floating' && (
                        <p className="-mt-2 text-xs leading-relaxed text-text-muted">
                            Bunga mengambang tidak bisa diketahui sekarang. Perkiraan yang wajar
                            untuk uji: bunga sekarang ditambah 2–3 poin.
                        </p>
                    )}

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
                                    error={galat.monthly_income}
                                />
                                <div>
                                    <IsianPersen
                                        id="income_growth"
                                        label="Estimasi kenaikan gaji per tahun (%)"
                                        placeholder="0"
                                        max="30"
                                        step="0.1"
                                        value={form.data.income_growth}
                                        onChange={(v) => form.setData('income_growth', v)}
                                        error={galat.income_growth}
                                    />
                                    <p className="mt-1.5 text-xs leading-relaxed text-text-muted">
                                        Opsional. Dipakai menilai jenjang bunga di tahun-tahun berikutnya.
                                        Kosongkan bila tidak yakin — penilaiannya jadi lebih hati-hati.
                                        Pengeluaran dianggap tetap, jadi angka yang terlalu tinggi membuat
                                        cicilan tampak lebih ringan dari kenyataan.
                                    </p>
                                </div>
                                <IsianRupiah
                                    id="other_installments"
                                    label="Cicilan lain per bulan"
                                    hint="Kendaraan, kartu kredit, paylater, pinjaman lain. Kosongkan bila tidak ada."
                                    value={form.data.other_installments}
                                    onChange={(v) => form.setData('other_installments', v)}
                                    error={galat.other_installments}
                                />
                                <IsianRupiah
                                    id="monthly_expenses"
                                    label="Pengeluaran rutin per bulan"
                                    hint="Makan, transportasi, listrik, sekolah, dan lain-lain — di luar cicilan."
                                    value={form.data.monthly_expenses}
                                    onChange={(v) => form.setData('monthly_expenses', v)}
                                    error={galat.monthly_expenses}
                                />
                                <IsianRupiah
                                    id="annual_taxes"
                                    label="Pajak tahunan"
                                    hint="Total pajak yang dibayar setahun sekali: PBB rumah, pajak kendaraan (STNK), dan lainnya. Dihitung per bulan di hasil."
                                    value={form.data.annual_taxes}
                                    onChange={(v) => form.setData('annual_taxes', v)}
                                    error={galat.annual_taxes}
                                />
                            </div>
                        )}
                    </div>
                </>
            }
            result={
                result ? (
                    <HasilPinjaman result={result} stress={stress} input={input} purchase={purchase} />
                ) : (
                    <EmptyResult>
                        Isi harga rumah dan uang muka (atau pokok pinjaman), tenor, dan suku bunga, lalu tekan Hitung Sekarang.
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

function IsianPersen({ id, label, placeholder, value, onChange, error, max = '50', step = '0.01' }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <TextInput
                id={id}
                type="number"
                step={step}
                min="0"
                max={max}
                className="num-tabular mt-1.5 block w-full"
                placeholder={placeholder}
                value={value}
                onChange={(e) => onChange(e.target.value)}
            />
            <InputError className="mt-1.5" message={error} />
        </div>
    );
}

function IsianRupiah({ id, label, hint, value, onChange, error, placeholder = '0' }) {
    return (
        <div>
            <InputLabel htmlFor={id} value={label} />
            <CurrencyInput id={id} className="mt-1.5" placeholder={placeholder} value={value} onChange={onChange} />
            {hint && <p className="mt-1.5 text-xs text-text-muted">{hint}</p>}
            <InputError className="mt-1.5" message={error} />
        </div>
    );
}

function Pil({ aktif, onClick, kecil = false, children }) {
    return (
        <button
            type="button"
            aria-pressed={aktif}
            onClick={onClick}
            className={
                'rounded-full border font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
                (kecil ? 'px-2.5 py-0.5 text-xs ' : 'px-3 py-1.5 text-xs ') +
                (aktif
                    ? 'border-lime-500 bg-lime-softBg text-lime-500'
                    : 'border-border-strong text-text-muted hover:text-text-primary')
            }
        >
            {children}
        </button>
    );
}

/** 20 -> "20%", 12.5 -> "12,5%". */
const formatPersen = (n) => `${String(Math.round(n * 10) / 10).replace('.', ',')}%`;

function HasilPinjaman({ result, stress, input, purchase }) {
    const berjenjang = result.tiers.length > 1;
    const bedaTerakhir = result.last_installment !== result.monthly_installment;

    return (
        <>
            <HeadlineResult
                label={berjenjang ? `Angsuran ${labelRentang(result.tiers[0]).toLowerCase()}` : 'Angsuran bulanan'}
                value={formatRupiah(result.monthly_installment)}
                note={
                    bedaTerakhir && !berjenjang
                        ? `Angsuran terakhir ${formatRupiah(result.last_installment)} — menyerap selisih pembulatan supaya pinjaman lunas tepat nol.`
                        : null
                }
            />

            {berjenjang && <TabelJenjang result={result} input={input} />}

            <ResultRows
                rows={[
                    ...(stress
                        ? [{
                              label: `Bila bunga naik ke ${input.floating_rate}%`,
                              value: formatRupiah(stress.monthly_installment),
                              hint: `Skenario uji: bunga naik sejak awal. Total bunganya ${formatRupiah(stress.total_interest)}.`,
                          }]
                        : []),
                    ...(purchase
                        ? [
                              { label: 'Harga rumah', value: formatRupiah(purchase.property_price) },
                              {
                                  label: 'Uang muka (DP)',
                                  value: formatRupiah(purchase.down_payment),
                                  hint: `${formatPersen(purchase.down_payment_percent)} dari harga rumah.`,
                              },
                          ]
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

            {purchase && <UangTunai purchase={purchase} />}

            <LoanChart series={result.series} className="mt-6" />

            <div className="mt-5 border-t border-border pt-4">
                <p className="text-xs leading-relaxed text-text-muted">
                    Metode: anuitas, angsuran tetap di akhir bulan. Bunga bulanan =
                    suku bunga tahunan dibagi dua belas — cara yang sama dengan
                    simulasi KPR bank, supaya angkanya bisa dicocokkan. Bunga tiap
                    bulan dihitung dari sisa pokok, jadi porsi bunga besar di awal dan
                    mengecil menjelang lunas.
                    {berjenjang && ' Di awal setiap jenjang, angsuran dihitung ulang dari sisa pokok, sisa tenor, dan bunga jenjang itu.'}
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
 * Uang tunai yang harus siap saat akad: DP ditambah biaya akad. Bila belum
 * terkumpul, itulah tabungan yang dikejar — jadi "Jadikan Tujuan" di sini
 * membawa nominal ITU, bukan pinjamannya. Kapan harus terkumpul tidak
 * diketahui kalkulator ini, jadi jangka waktunya dipilih di form tujuan.
 */
function UangTunai({ purchase }) {
    if (!(purchase.cash_needed > 0)) return null;

    return (
        <div className="mt-5 rounded-lg border border-border p-4">
            <div className="flex items-baseline justify-between gap-3">
                <p className="text-sm font-semibold text-text-primary">Uang tunai saat akad</p>
                <p className="num-tabular text-lg font-bold text-text-primary">{formatRupiah(purchase.cash_needed)}</p>
            </div>
            <p className="mt-1 text-xs leading-relaxed text-text-muted">
                {purchase.closing_costs > 0
                    ? `DP ${formatRupiah(purchase.down_payment)} + biaya akad ${formatRupiah(purchase.closing_costs)}.`
                    : 'Uang muka saja — biaya akad tidak diisi.'}
            </p>
            <JadikanTujuan
                label="Jadikan tujuan: DP rumah"
                note="Belum terkumpul?"
                params={{ name: 'DP rumah', target_amount: purchase.cash_needed }}
            />
        </div>
    );
}

/**
 * Angsuran per jenjang beserta kenaikannya dari jenjang sebelumnya. Kenaikan
 * itulah yang paling sering luput dari brosur: angsuran tahun pertama yang
 * ditonjolkan, padahal yang harus sanggup dibayar paling lama adalah angsuran
 * jenjang terakhir.
 */
function TabelJenjang({ result, input }) {
    const perkiraan = (k) => ['1', 1, true, 'true'].includes(input?.tiers?.[k]?.floating);
    const pertama = result.tiers[0].installment;
    const terakhir = result.tiers.at(-1).installment;

    return (
        <div className="mt-5">
            <table className="w-full text-sm">
                <thead>
                    <tr className="border-b border-border text-left text-xs text-text-muted">
                        <th className="py-2 pr-2 font-medium">Jenjang</th>
                        <th className="py-2 pr-2 text-right font-medium">Bunga</th>
                        <th className="py-2 text-right font-medium">Angsuran</th>
                    </tr>
                </thead>
                <tbody className="num-tabular">
                    {result.tiers.map((t, k) => {
                        const naik = k > 0 && result.tiers[k - 1].installment > 0
                            ? Math.round((t.installment / result.tiers[k - 1].installment - 1) * 100)
                            : null;

                        return (
                            <tr key={t.from_month} className="border-b border-border/60 last:border-0">
                                <td className="py-2 pr-2 text-text-secondary">{labelRentang(t)}</td>
                                <td className="py-2 pr-2 text-right text-text-primary">
                                    {String(t.rate).replace('.', ',')}%
                                    {perkiraan(k) && <span className="ml-1 text-xs text-text-muted">(perkiraan)</span>}
                                </td>
                                <td className="py-2 text-right text-text-primary">
                                    {formatRupiah(t.installment)}
                                    {naik !== null && naik !== 0 && (
                                        <span className={`ml-1.5 text-xs ${naik > 0 ? 'text-state-warning' : 'text-state-success'}`}>
                                            {naik > 0 ? '+' : ''}{naik}%
                                        </span>
                                    )}
                                </td>
                            </tr>
                        );
                    })}
                </tbody>
            </table>

            {terakhir > pertama && (
                <p className="mt-2 text-xs leading-relaxed text-text-muted">
                    Angsuran jenjang terakhir {Math.round((terakhir / pertama - 1) * 100)}% lebih besar dari
                    tahun pertama — pastikan angka inilah yang sanggup dibayar, bukan angsuran awalnya.
                </p>
            )}
        </div>
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
