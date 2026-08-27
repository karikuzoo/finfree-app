import { formatRupiah } from '@/utils/format';

/**
 * Satu baris progress tujuan (DESIGN.md §5.7) — label kiri, nilai kanan,
 * fill lime, track border, badge persentase di ujung.
 */
function GoalProgressRow({ goal }) {
    const percentage = Math.min(100, Math.max(0, goal.progress_percentage));

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
                        style={{ width: `${percentage}%` }}
                    />
                </div>
                <span className="num-tabular w-12 shrink-0 text-right text-xs font-semibold text-text-primary">
                    {percentage.toFixed(0)}%
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
