import { formatCompactRupiah, formatDuration, formatRupiah } from '@/utils/format';
import {
    Area,
    AreaChart,
    CartesianGrid,
    ResponsiveContainer,
    Tooltip,
    XAxis,
    YAxis,
} from 'recharts';

/**
 * Proyeksi pertumbuhan dana sampai tanggal target.
 *
 * Sengaja area bertumpuk, bukan satu garis saldo: yang ingin ditunjukkan bukan
 * sekadar "dana Anda tumbuh", melainkan **berapa bagian yang datang dari
 * kantong Anda dan berapa dari imbal hasil**. Pada tujuan jangka panjang,
 * bagian imbal hasil sering melampaui total setoran — dan itulah alasan
 * terkuat untuk mulai lebih awal.
 *
 * Warna mengikuti DESIGN.md §5.5: lime untuk bagian utama, abu hangat untuk
 * pembanding, grid jauh lebih redup daripada garis data.
 */
export default function ProjectionChart({ data, className = '' }) {
    if (!data?.length) return null;

    return (
        <div className={className}>
            <ResponsiveContainer width="100%" height={260}>
                <AreaChart
                    data={data}
                    margin={{ top: 8, right: 8, bottom: 4, left: 8 }}
                >
                    <defs>
                        <linearGradient id="isiLime" x1="0" y1="0" x2="0" y2="1">
                            <stop offset="0%" stopColor="#CFF04A" stopOpacity={0.55} />
                            <stop offset="100%" stopColor="#CFF04A" stopOpacity={0.08} />
                        </linearGradient>
                    </defs>

                    <CartesianGrid stroke="#282C28" vertical={false} />

                    <XAxis
                        dataKey="month"
                        tickFormatter={(m) => (m % 12 === 0 ? `${m / 12} th` : '')}
                        stroke="#282C28"
                        tick={{ fill: '#8B917F', fontSize: 11 }}
                        tickLine={false}
                        interval="preserveStartEnd"
                        minTickGap={16}
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
                        itemStyle={{ padding: 0 }}
                        labelFormatter={(m) =>
                            m === 0 ? 'Awal' : formatDuration(m)
                        }
                        formatter={(value, name) => [formatRupiah(value), name]}
                    />

                    <Area
                        type="monotone"
                        dataKey="contributed"
                        name="Setoran Anda"
                        stackId="1"
                        stroke="#6E756A"
                        fill="#4A4F47"
                        fillOpacity={0.9}
                    />
                    <Area
                        type="monotone"
                        dataKey="growth"
                        name="Hasil pengembangan"
                        stackId="1"
                        stroke="#CFF04A"
                        strokeWidth={2}
                        fill="url(#isiLime)"
                    />
                </AreaChart>
            </ResponsiveContainer>

            <div className="mt-3 flex flex-wrap items-center gap-4 text-xs text-text-secondary">
                <span className="flex items-center gap-2">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-[#4A4F47]" />
                    Setoran Anda
                </span>
                <span className="flex items-center gap-2">
                    <span className="inline-block h-2.5 w-2.5 rounded-sm bg-lime-500" />
                    Hasil pengembangan
                </span>
            </div>
        </div>
    );
}
