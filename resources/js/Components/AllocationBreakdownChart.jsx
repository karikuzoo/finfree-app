import {
    PieChart,
    Pie,
    Cell,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from "recharts";

/**
 * Palet kategorikal untuk potongan pie.
 *
 * Versi sebelumnya memakai warna state aplikasi (info biru pucat, warning
 * kuning tua, dan abu netral) — terbaca pastel dan potongan keempat nyaris
 * hilang karena abu-abu punya bobot visual paling rendah di latar gelap.
 *
 * Palet ini dipilih dengan tiga syarat: setiap warna cukup terang untuk
 * menampung label teks gelap di atasnya, hue-nya berjauhan supaya tetap
 * terbedakan berdampingan, dan tidak ada satu pun yang abu-abu. Lime tetap
 * di urutan pertama agar potongan terbesar selaras dengan warna merek.
 */
const COLORS = [
    "#CFF04A", // lime — warna merek
    "#38BDF8", // biru langit
    "#FB923C", // oranye
    "#C084FC", // ungu
    "#2DD4BF", // toska
    "#FB7185", // merah muda
];

const RADIAN = Math.PI / 180;

/**
 * Label persentase DI DALAM tiap potongan pie — dirender lewat prop
 * `label` milik <Pie>, diposisikan di ~60% jari-jari luar (bukan di
 * tengah pusat lingkaran, supaya tidak bertumpuk kalau nanti chart ini
 * dipakai dengan innerRadius > 0 lagi) dengan `labelLine={false}` supaya
 * recharts tidak menggambar garis penunjuk ke luar chart seperti
 * defaultnya.
 */
function renderInsideLabel({
    cx,
    cy,
    midAngle,
    innerRadius,
    outerRadius,
    value,
}) {
    const radius = innerRadius + (outerRadius - innerRadius) * 0.6;
    const x = cx + radius * Math.cos(-midAngle * RADIAN);
    const y = cy + radius * Math.sin(-midAngle * RADIAN);

    return (
        <text
            x={x}
            y={y}
            textAnchor="middle"
            dominantBaseline="central"
            fontSize={12}
            fontWeight={600}
            fill="#10130A"
        >
            {value}%
        </text>
    );
}

/**
 * Breakdown alokasi instrumen investasi (Saham/Obligasi/Deposito/Emas)
 * untuk goal terpilih — dari `goal.suggested_allocation`
 * (InvestmentAllocationService, lihat catatan ILUSTRATIF di service itu:
 * tabel rule-based sederhana, bukan hasil kajian produk yang sudah
 * direview).
 *
 * Pie chart penuh (innerRadius 0) — bukan donut, dengan label persentase
 * DI DALAM tiap potongan (renderInsideLabel). Tidak ada wrapper kartu
 * (border/bg/padding) di sini — dibungkus kartunya sendiri-sendiri di
 * Dashboard.jsx, ditaruh sebaris dengan ActivityCalendar lewat grid.
 *
 * Root elemen dibuat `h-full flex flex-col` supaya saat kartu ini
 * diregangkan grid mengikuti tinggi ActivityCalendar di sebelahnya (yang
 * biasanya lebih tinggi karena grid 5-6 minggu), ruang sisanya
 * dibagi rata di ATAS dan BAWAH pie+legend (lewat `flex-1
 * items-center`) — bukan menumpuk semua di bawah seperti kalau memakai
 * tinggi tetap.
 *
 * BUKAN breakdown "Kebutuhan/Keinginan/Tabungan" ala aplikasi budget
 * harian — itu butuh modul kategorisasi transaksi yang tidak ada di
 * data model FinGoal (goal-based saving, bukan pencatat pengeluaran
 * harian).
 */
export default function AllocationBreakdownChart({
    allocation,
    placeholder = false,
}) {
    if (!allocation || allocation.length === 0) {
        return null;
    }

    const chartData = allocation
        .filter((item) => item.percentage > 0)
        .map((item) => ({ name: item.instrument, value: item.percentage }));

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold text-text-primary">
                    Alokasi Instrumen yang Disarankan
                </h2>
                {placeholder && (
                    <span className="rounded-full bg-bg-cardAlt px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-text-muted">
                        Contoh
                    </span>
                )}
            </div>
            <p className="mt-1 text-xs text-text-muted">
                Ilustrasi berdasarkan jangka waktu dan profil risiko — bukan
                nasihat investasi.
            </p>

            <div className="flex flex-1 items-center justify-center">
                <div className="aspect-square w-full max-w-xs">
                    <ResponsiveContainer width="100%" height="100%">
                        <PieChart>
                            <Pie
                                data={chartData}
                                dataKey="value"
                                nameKey="name"
                                cx="50%"
                                cy="45%"
                                innerRadius={0}
                                outerRadius="85%"
                                label={renderInsideLabel}
                                labelLine={false}
                            >
                                {chartData.map((entry, index) => (
                                    <Cell
                                        key={entry.name}
                                        fill={COLORS[index % COLORS.length]}
                                    />
                                ))}
                            </Pie>
                            <Tooltip
                                formatter={(value) => `${value}%`}
                                contentStyle={{
                                    backgroundColor: "#16181A",
                                    border: "1px solid #282C28",
                                    borderRadius: 8,
                                    fontSize: 12,
                                }}
                                itemStyle={{ color: "#F0F1EC" }}
                            />
                            <Legend
                                verticalAlign="bottom"
                                height={36}
                                wrapperStyle={{
                                    fontSize: 12,
                                    color: "#A3A99E",
                                }}
                            />
                        </PieChart>
                    </ResponsiveContainer>
                    <div className="mt-4 flex justify-center">
                        <button
                            type="button"
                            className="rounded-lg bg-lime-500 px-5 py-3 text-sm font-semibold text-onPrimary items-center transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                        >
                            Details
                        </button>
                    </div>
                </div>
            </div>
        </div>
    );
}
