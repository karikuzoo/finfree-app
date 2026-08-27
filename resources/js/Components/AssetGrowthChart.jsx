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

/**
 * Proyeksi total aset gabungan seluruh tujuan aktif, 12 bulan terakhir
 * (DashboardSummaryService::assetGrowthSeries, CLAUDE.md §6.9).
 *
 * Satu garis akumulatif — beda dari ProjectionChart.jsx yang memisahkan
 * setoran vs hasil pengembangan untuk SATU tujuan. Di sini yang ditunjukkan
 * total lintas tujuan, jadi pemisahan itu tidak relevan lagi. Warna & gaya
 * grid tetap mengikuti bahasa visual yang sama.
 */
export default function AssetGrowthChart({ series }) {
    if (!series?.length) return null;

    const data = series.map((point) => ({
        month: shortMonthLabel(point.month),
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
                    dataKey="month"
                    stroke="#282C28"
                    tick={{ fill: '#8B917F', fontSize: 11 }}
                    tickLine={false}
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
