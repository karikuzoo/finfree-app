import { formatCompactRupiah } from "@/utils/format";

// Indeks sesuai Date.getDay() bawaan JS (0 = Minggu ... 6 = Sabtu) —
// dipakai langsung tanpa perlu menukar urutan, beda dari leadingBlanks
// di bawah yang memang perlu penukaran karena grid dimulai dari Senin.
const SHORT_DAY_BY_INDEX = ["Min", "Sen", "Sel", "Rab", "Kam", "Jum", "Sab"];

/**
 * Kalender "Aktivitas Bulan Ini" — grid harian bulan berjalan, tiap sel
 * menampilkan total setoran (`goal_contributions.amount`) di tanggal itu
 * dari `summary.contribution_calendar` (DashboardSummaryService).
 *
 * Nama hari ditampilkan DI DALAM tiap sel, di sebelah angka tanggal
 * (bukan baris header terpisah seperti sebelumnya) — supaya tidak
 * bergantung pada lebar layar (`sm:` Tailwind itu breakpoint viewport,
 * bukan lebar kartu, jadi gampang salah ukuran begitu kalender ini
 * ditaruh sebaris dengan kartu lain lewat grid, seperti sekarang).
 *
 * Grid bulan (jumlah hari, hari pertama jatuh di kolom mana) dihitung di
 * sini pakai Date bawaan JS, bukan dikirim backend — datanya sendiri
 * (nominal per tanggal) tetap murni dari server.
 *
 * `placeholder=true` dipakai saat pengguna belum punya goal — `calendar`
 * di kondisi ini berisi data contoh/dummy, diberi label "Contoh" supaya
 * tidak disangka riwayat setoran asli.
 */
export default function ActivityCalendar({ calendar, placeholder = false }) {
    const amountByDate = Object.fromEntries(
        (calendar ?? []).map((item) => [item.date, item.amount]),
    );

    const now = new Date();
    const year = now.getFullYear();
    const month = now.getMonth();
    const firstDay = new Date(year, month, 1);
    const daysInMonth = new Date(year, month + 1, 0).getDate();
    const todayStr = now.toISOString().slice(0, 10);

    // JS: Minggu = 0 ... Sabtu = 6. Ditukar supaya Senin jadi kolom pertama.
    const leadingBlanks = (firstDay.getDay() + 6) % 7;

    const cells = Array.from({ length: leadingBlanks }, () => null);

    for (let day = 1; day <= daysInMonth; day++) {
        const dateStr = `${year}-${String(month + 1).padStart(2, "0")}-${String(day).padStart(2, "0")}`;
        cells.push({
            day,
            dateStr,
            dayLabel: SHORT_DAY_BY_INDEX[new Date(year, month, day).getDay()],
            amount: amountByDate[dateStr] ?? 0,
        });
    }

    const monthLabel = firstDay.toLocaleDateString("id-ID", {
        month: "long",
        year: "numeric",
    });

    return (
        <div>
            <div className="flex items-center justify-between">
                <h2 className="text-base font-semibold text-text-primary">
                    Aktivitas Bulan Ini
                </h2>
                <span className="flex items-center gap-2 text-xs uppercase tracking-wide text-text-muted">
                    {monthLabel}
                    {placeholder && (
                        <span className="rounded-full bg-bg-cardAlt px-2 py-0.5 text-[10px] normal-case tracking-normal text-text-muted">
                            Contoh
                        </span>
                    )}
                </span>
            </div>

            <div className="mt-4 grid grid-cols-7 gap-1.5">
                {cells.map((cell, index) =>
                    cell === null ? (
                        <div key={`blank-${index}`} />
                    ) : (
                        <div
                            key={cell.dateStr}
                            className={
                                "flex h-16 flex-col justify-between rounded-lg border p-1.5 text-left " +
                                (cell.dateStr === todayStr
                                    ? "border-lime-500"
                                    : "border-border/60") +
                                " " +
                                (cell.amount > 0
                                    ? "bg-lime-softBg"
                                    : "bg-bg-cardAlt")
                            }
                        >
                            <span className="flex items-baseline gap-1 text-[11px] text-text-secondary">
                                <span>{cell.day}</span>
                                <span className="text-[9px] uppercase text-text-muted">
                                    {cell.dayLabel}
                                </span>
                            </span>
                            {cell.amount > 0 && (
                                <span className="truncate text-[10px] font-semibold text-lime-500">
                                    +{formatCompactRupiah(cell.amount)}
                                </span>
                            )}
                        </div>
                    ),
                )}
            </div>
        </div>
    );
}
