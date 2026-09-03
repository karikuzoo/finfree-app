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
 * Proyeksi total aset SATU tujuan (yang sedang dipilih di GoalHeroCard),
 * bisa ditampilkan HARIAN atau BULANAN lewat prop `granularity`
 * (DashboardSummaryService::summarizeGoal — key `asset_growth_series`
 * berisi `{ monthly: [...], daily: [...] }`, Dashboard.jsx yang memilih
 * mana yang dikirim ke sini lewat toggle-nya sendiri).
 *
 * - `monthly`: titik `{ month: "2026-03", cumulative_amount }`, sejak
 *   tujuan dibuat sampai bulan berjalan.
 * - `daily`: titik `{ date: "2026-03-05", cumulative_amount }`, dibatasi
 *   30 hari terakhir (goalAssetGrowthSeriesDaily) — jendela lebih pendek
 *   supaya tetap terbaca, bukan ratusan titik untuk goal lama.
 *
 * Satu garis akumulatif — beda dari ProjectionChart.jsx yang memisahkan
 * setoran vs hasil pengembangan. Warna & gaya grid tetap mengikuti bahasa
 * visual yang sama di kedua granularitas.
 */
export default function AssetGrowthChart({ series, granularity = 'monthly' }) {
    if (!series?.length) return null;

    const isDaily = granularity === 'daily';
    const data = series.map((point) => ({
        label: isDaily ? shortDayLabel(point.date) : shortMonthLabel(point.month),
        value: point.cumulative_amount,
    }));

    return (
        <ResponsiveContainer width="100%" height={260}>
            <AreaChart data={data} margin={{ top: 8, right: 8, bottom: 4, left: 8 }}>
                <defs>
                    <linearGradient id="assetGrowthFill" x1="0" y1="0" x2="0" y2="1">
                        <stop offset="0%" stopColor="#CFF04A" stopOpacity={0.35} />
                        <stop offset="100%" stopColor="#CFF04A" stopOpacity={0.05} />
                    </linearGradient>
                </defs>

                <CartesianGrid stroke="#282C28" vertical={false} />

                <XAxis
                    dataKey="label"
                    stroke="#282C28"
                    tick={{ fill: '#8B917F', fontSize: 11 }}
                    tickLine={false}
                    // Harian = sampai 30 titik, gampang berdesakan — lompati
                    // sebagian label otomatis, biarkan recharts yang atur.
                    interval={isDaily ? 'preserveStartEnd' : 0}
                />
                <YAxis
                    tickFormatter={formatCompactRupiah}
                    stroke="#282C28"
                    tick={{ fill: '#8B917F', fontSize: 11 }}
                    tickLine={false}
                    width={72}
                />

                <Tooltip
                    contentStyle={{
                        background: '#16181A',
                        border: '1px solid #666D61',
                        borderRadius: 10,
                        fontSize: 12,
                    }}
                    labelStyle={{ color: '#A3A99E', marginBottom: 4 }}
                    itemStyle={{ padding: 0, color: '#CFF04A' }}
                    formatter={(value) => [formatRupiah(value), 'Total aset']}
                />

                <Area
                    type="monotone"
                    dataKey="value"
                    stroke="#CFF04A"
                    strokeWidth={2}
                    fill="url(#assetGrowthFill)"
                />
            </AreaChart>
        </ResponsiveContainer>
    );
}
