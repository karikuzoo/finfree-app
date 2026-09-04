import {
    PieChart,
    Pie,
    Cell,
    Tooltip,
    Legend,
    ResponsiveContainer,
} from "recharts";
import { Link } from "@inertiajs/react";
import { formatRupiah } from "@/utils/format";

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
    comparison = null,
    placeholder = false,
}) {
    if (!allocation || allocation.length === 0) {
        return null;
    }

    // Alokasi NYATA dipakai bila pengguna sudah mencatatnya di Dompet.
    // Selama belum, yang tergambar tetap saran — pai kosong terbaca sebagai
    // kerusakan, bukan sebagai "belum diisi".
    const adaNyata = Boolean(comparison?.has_actual);
    const baris = comparison?.rows ?? [];

    const chartData = adaNyata
        ? baris
              .filter((r) => r.actual_percentage > 0)
              .map((r) => ({ name: r.instrument, value: r.actual_percentage }))
        : allocation
              .filter((item) => item.percentage > 0)
              .map((item) => ({ name: item.instrument, value: item.percentage }));

    // Penyimpangan terbesar saja yang disebut. Enam penanda merah sekaligus
    // menuntut perhatian yang sama besar dan akhirnya diabaikan semua.
    const terbesar = baris
        .filter((r) => r.off_track)
        .sort((a, b) => Math.abs(b.delta) - Math.abs(a.delta))[0];

    return (
        <div className="flex h-full flex-col">
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold text-text-primary">
                    {adaNyata
                        ? "Alokasi Aset Anda"
                        : "Alokasi Instrumen yang Disarankan"}
                </h2>
                {placeholder && (
                    <span className="rounded-full bg-bg-cardAlt px-2 py-0.5 text-[10px] font-semibold uppercase tracking-wide text-text-muted">
                        Contoh
                    </span>
                )}
            </div>
            <p className="mt-1 text-xs text-text-muted">
                {adaNyata
                    ? "Dari alokasi yang Anda catat di Dompet, disandingkan dengan saran."
                    : "Ilustrasi berdasarkan jangka waktu dan profil risiko — bukan nasihat investasi."}
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
                </div>
            </div>

            {!placeholder && baris.length > 0 && (
                <Perbandingan
                    baris={baris}
                    adaNyata={adaNyata}
                    total={comparison?.total_actual ?? 0}
                    terbesar={terbesar}
                />
            )}
        </div>
    );
}

/**
 * Tabel saran vs nyata (PRD FR-52..FR-56).
 *
 * Selisih ditulis dalam POIN PERSEN, bukan persen dari persen: 70% yang
 * menjadi 25% adalah selisih 45 poin, bukan turun 64%. Menuliskannya sebagai
 * persen membuat angkanya terbaca jauh lebih dramatis daripada kenyataannya.
 */
function Perbandingan({ baris, adaNyata, total, terbesar }) {
    if (!adaNyata) {
        return (
            <p className="mt-4 rounded-lg border border-border bg-bg-cardAlt px-3 py-2.5 text-xs leading-relaxed text-text-secondary">
                Catat penempatan dana Anda di halaman{" "}
                <Link
                    href={route("wallet.index")}
                    className="font-semibold text-lime-500 hover:text-lime-400"
                >
                    Dompet
                </Link>{" "}
                untuk melihat sejauh mana ia sudah mendekati saran ini.
            </p>
        );
    }

    return (
        <div className="mt-4 border-t border-border pt-3">
            <table className="w-full text-xs">
                <thead>
                    <tr className="text-text-muted">
                        <th className="pb-1.5 text-left font-semibold">
                            Instrumen
                        </th>
                        <th className="pb-1.5 text-right font-semibold">Saran</th>
                        <th className="pb-1.5 text-right font-semibold">Nyata</th>
                        <th className="pb-1.5 text-right font-semibold">
                            Selisih
                        </th>
                    </tr>
                </thead>
                <tbody>
                    {baris.map((r) => (
                        <tr key={r.instrument}>
                            <td className="py-1 text-text-secondary">
                                {r.instrument}
                            </td>
                            <td className="num-tabular py-1 text-right text-text-muted">
                                {r.suggested_percentage}%
                            </td>
                            <td className="num-tabular py-1 text-right font-semibold text-text-primary">
                                {r.actual_percentage}%
                            </td>
                            <td
                                className={
                                    "num-tabular py-1 text-right font-semibold " +
                                    (r.off_track
                                        ? r.delta > 0
                                            ? "text-state-warning"
                                            : "text-state-info"
                                        : "text-text-muted")
                                }
                            >
                                {r.delta > 0 ? "+" : ""}
                                {r.delta}
                            </td>
                        </tr>
                    ))}
                </tbody>
            </table>

            <p className="num-tabular mt-2 text-[11px] text-text-muted">
                Total dialokasikan {formatRupiah(total)}
            </p>

            {terbesar && (
                <p className="mt-2 text-[11px] leading-relaxed text-state-warning">
                    Penyimpangan terbesar: {terbesar.instrument}{" "}
                    {terbesar.delta > 0 ? "melebihi" : "kurang dari"} saran
                    sebanyak {Math.abs(terbesar.delta)} poin persen.
                </p>
            )}
        </div>
    );
}
