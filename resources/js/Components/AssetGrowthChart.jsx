import { formatCompactRupiah, formatRupiah } from '@/utils/format';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

const MONTH_LABELS = [
    'Jan', 'Feb', 'Mar', 'Apr', 'Mei', 'Jun',
    'Jul', 'Agu', 'Sep', 'Okt', 'Nov', 'Des',
];

/** "2026-03" -> "Mar" */
function shortMonthLabel(ym) {
    const [, month] = ym.split('-');
    return MONTH_LABELS[Number(month) - 1] ?? ym;
}

/** "2026-09-03" -> "3 Sep" */
function shortDayLabel(ymd) {
    const [, month, day] = ymd.split('-');
    return `${Number(day)} ${MONTH_LABELS[Number(month) - 1] ?? month}`;
}

/**
 * Total kekayaan pengguna dari waktu ke waktu, HARIAN atau BULANAN lewat
 * prop `granularity` (DashboardSummaryService — `asset_growth_series` berisi
 * `{ monthly: [...], daily: [...] }`; Dashboard.jsx yang memilih mana yang
 * dikirim ke sini lewat toggle-nya sendiri).
 *
 * Kedua deret memakai kunci yang SAMA: `{ period, cumulative_amount }` —
 * "2026-03" untuk bulanan, "2026-03-05" untuk harian. Sebelumnya bulanan
 * memakai `month` dan harian memakai `date`, dan perbedaan itu persis yang
 * membuat grafik harian kosong begitu sumber datanya diganti: labelnya
 * undefined, tanpa error yang terlihat di layar. Satu nama untuk keduanya
 * menutup seluruh kelas kekeliruan itu.
 *
 * Bulanan membentang 12 bulan, harian 30 hari — jendela pendek supaya
 * labelnya tetap terbaca.
 *
 * Satu garis akumulatif, beda dari ProjectionChart.jsx yang memisahkan
 * setoran dari hasil pengembangan.
 */
export default function AssetGrowthChart({ series, granularity = 'monthly' }) {
    if (!series?.length) return null;

    const isDaily = granularity === 'daily';
    const data = series.map((point) => ({
        label: isDaily ? shortDayLabel(point.period) : shortMonthLabel(point.period),
        value: point.cumulative_amount,
    }));

    return (
        <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 4, left: 8 }}>
                <defs>
                    <linearGradient id="assetGrowthFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#98EDCE" stopOpacity={0.35} />
                        <stop offset="100%" stopColor="#98EDCE" stopOpacity={0.05} />
                    </linearGradient>
                </defs>

                <CartesianGrid stroke="#2C383B" vertical={false} />

                <XAxis
                    dataKey="label"
                    stroke="#2C383B"
                    tick={{ fill: '#A1B1B3', fontSize: 11 }}
                    tickLine={false}
                    // Harian = sampai 30 titik, gampang berdesakan — lompati
                    // sebagian label otomatis, biarkan recharts yang atur.
                    interval={isDaily ? 'preserveStartEnd' : 0}
                />
                <YAxis
                    tickFormatter={formatCompactRupiah}
                    stroke="#2C383B"
                    tick={{ fill: '#A1B1B3', fontSize: 11 }}
                    tickLine={false}
                    width={72}
                />

                <Tooltip
                    contentStyle={{
                        background: '#182124',
                        border: '1px solid #708780',
                        borderRadius: 10,
                        fontSize: 12,
                    }}
                    labelStyle={{ color: '#B2C2C3', marginBottom: 4 }}
                    itemStyle={{ padding: 0, color: '#98EDCE' }}
                    formatter={(value) => [formatRupiah(value), 'Total aset']}
                />

                <Area
                    type="monotone"
                    dataKey="value"
                    stroke="#98EDCE"
                    strokeWidth={2}
                    fill="url(#assetGrowthFill)"
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}
