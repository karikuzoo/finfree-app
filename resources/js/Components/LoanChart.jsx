import { formatCompactRupiah, formatDuration, formatRupiah } from '@/utils/format';
import { CartesianGrid, Line, LineChart, ResponsiveContainer, Tooltip, XAxis, YAxis } from 'recharts';

/**
 * Grafik amortisasi (FR-41): sisa pokok yang turun berhadapan dengan bunga
 * yang terus bertambah. Dua garis, bukan area bertumpuk — keduanya bukan
 * bagian dari satu total, dan titik temunya justru yang menarik: di tahun
 * ke berapa bunga yang sudah dibayar menyamai pokok yang masih tersisa.
 *
 * `series` datang jadi dari GoalCalculatorService::calculateLoan(), satu titik
 * per tahun plus bulan terakhir. Tidak ada yang dihitung ulang di sini.
 *
 * Warna mengikuti ProjectionChart: mint untuk yang utama (sisa pokok), ungu
 * untuk pembanding (bunga). Merah sengaja tidak dipakai untuk bunga — bunga
 * adalah biaya yang disepakati, bukan kesalahan.
 */
export default function LoanChart({ series, className = '' }) {
    if (!series?.length) return null;

    return (
        <div className={className}>
            <ResponsiveContainer width="100%" height={260}>
                <LineChart data={series} margin={{ top: 8, right: 8, bottom: 4, left: 8 }}>
                    <CartesianGrid stroke="#2C383B" vertical={false} />
                    <XAxis
                        dataKey="month"
                        tickFormatter={(m) => (m % 12 === 0 ? `${m / 12} th` : '')}
                        stroke="#2C383B"
                        tick={{ fill: '#A1B1B3', fontSize: 11 }}
                        tickLine={false}
                        interval="preserveStartEnd"
                        minTickGap={16}
                    />
                    <YAxis
                        tickFormatter={formatCompactRupiah}
                        stroke="#2C383B"
                        tick={{ fill: '#A1B1B3', fontSize: 11 }}
                        tickLine={false}
                        width={72}
                    />
                    <Tooltip
                        contentStyle={{ background: '#182124', border: '1px solid #708780', borderRadius: 10, fontSize: 12 }}
                        labelStyle={{ color: '#B2C2C3', marginBottom: 4 }}
                        itemStyle={{ padding: 0 }}
                        labelFormatter={(m) => (m === 0 ? 'Awal' : formatDuration(m))}
                        formatter={(value, name) => [formatRupiah(value), name]}
                    />
                    <Line type="monotone" dataKey="balance" name="Sisa pokok" stroke="#98EDCE" strokeWidth={2} dot={false} />
                    <Line type="monotone" dataKey="cumulative_interest" name="Bunga dibayar" stroke="#B29BFA" strokeWidth={2} dot={false} />
                </LineChart>
            </ResponsiveContainer>

            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-text-secondary">
                <span className="flex items-center gap-2">
                    <span className="inline-block h-0.5 w-4 rounded bg-lime-500" />
                    Sisa pokok
                </span>
                <span className="flex items-center gap-2">
                    <span className="inline-block h-0.5 w-4 rounded bg-[#B29BFA]" />
                    Akumulasi bunga dibayar
                </span>
            </div>
        </div>
    );
}
