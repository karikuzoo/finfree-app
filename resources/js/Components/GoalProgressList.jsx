import { formatRupiah } from '@/utils/format';

/**
 * Satu baris progress tujuan (DESIGN.md §5.7) — label kiri, nilai kanan,
 * fill lime, track border, badge persentase di ujung.
 */
/**
 * Persentase yang dibulatkan ke bilangan bulat, KECUALI saat hasilnya
 * membulatkan progres nyata menjadi nol.
 *
 * Rp 3.000.000 dari target Rp 2.000.000.000 adalah 0,15% — dan `toFixed(0)`
 * menjadikannya "0%", tidak terbedakan dari tujuan yang benar-benar belum
 * disentuh sama sekali. Uang yang sudah disisihkan tidak boleh tampil seolah
 * tidak ada.
 */
function labelPersen(n) {
    if (n > 0 && n < 1) {
        return '<1%';
    }

    if (n > 99 && n < 100) {
        return '>99%';
    }

    return `${n.toFixed(0)}%`;
}

function GoalProgressRow({ goal }) {
    const percentage = Math.min(100, Math.max(0, goal.progress_percentage));

    // Batang selebar 0,15% tidak tergambar sama sekali. Diberi lebar minimum
    // supaya progres yang kecil tetap KELIHATAN kecil, bukan hilang.
    const lebar = percentage > 0 ? Math.max(1.5, percentage) : 0;

    return (
        <div className="py-3">
            <div className="flex items-center justify-between gap-3 text-sm">
                <span className="truncate font-medium text-text-primary">
                    {goal.name}
                </span>
                <span className="num-tabular shrink-0 text-text-secondary">
                    {formatRupiah(goal.current_amount)} / {formatRupiah(goal.target_amount)}
                </span>
            </div>
            <div className="mt-2 flex items-center gap-3">
                <div className="h-2 flex-1 overflow-hidden rounded-full bg-border">
                    <div
                        className="h-full rounded-full bg-lime-500 transition-[width] duration-500 ease-out motion-reduce:transition-none"
                        style={{ width: `${lebar}%` }}
                    />
                </div>
                <span className="num-tabular w-14 shrink-0 text-right text-xs font-semibold text-text-primary">
                    {labelPersen(percentage)}
                </span>
            </div>
        </div>
    );
}

export default function GoalProgressList({ goals }) {
    if (goals.length === 0) {
        return <p className="py-3 text-sm text-text-muted">Belum ada tujuan aktif.</p>;
    }

    return (
        <div className="divide-y divide-border">
            {goals.map((goal) => (
                <GoalProgressRow key={goal.id} goal={goal} />
            ))}
        </div>
    );
}
