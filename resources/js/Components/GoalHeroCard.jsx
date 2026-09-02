import { formatRupiah } from "@/utils/format";
import Dropdown from "@/Components/Dropdown";

export default function GoalHeroCard({
    goals = [],
    selectedGoal,
    onGoalChange,
    streakDays = 0,
    todayContributionAmount = 0,
    placeholder = false,
}) {
    const goal = selectedGoal;

    if (!goal) {
        return null;
    }

    const progressWidth = Math.min(100, goal.progress_percentage);

    return (
        <div className="relative scroll-mt-24 rounded-card border border-border bg-bg-card p-6">
            {placeholder && (
                <span className="absolute right-6 top-6 rounded-full bg-bg-cardAlt px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-text-muted">
                    Contoh tampilan
                </span>
            )}

            <div className="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        {placeholder ? (
                            <div className="flex h-8 items-center rounded-lg border-2 border-transparent text-sm font-bold text-text-primary">
                                {goal.name}
                            </div>
                        ) : (
                            <Dropdown>
                                <Dropdown.Trigger>
                                    <button
                                        type="button"
                                        className="group relative inline-flex items-center rounded-lg border-2 border-border-strong bg-bg-cardAlt py-1.5 pl-3 pr-9 text-sm font-bold text-text-primary shadow-sm transition hover:border-lime-500 focus:border-lime-500 focus:outline-none focus:ring-0"
                                    >
                                        <span className="truncate max-w-[200px]">{goal.name}</span>
                                        <div className="pointer-events-none absolute right-2.5 text-text-muted transition group-hover:text-lime-500">
                                            <svg className="h-4 w-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth="2.5" d="M19 9l-7 7-7-7"></path>
                                            </svg>
                                        </div>
                                    </button>
                                </Dropdown.Trigger>

                                <Dropdown.Content align="left" width="none" contentClasses="py-1 bg-bg-card min-w-[200px] whitespace-nowrap">
                                    {goals.map((g) => (
                                        <button
                                            key={g.id}
                                            type="button"
                                            onClick={() => onGoalChange && onGoalChange(g.id)}
                                            className={`block w-full px-4 py-2 text-left text-sm font-semibold transition ${
                                                g.id === goal.id
                                                    ? "bg-lime-500 text-onPrimary"
                                                    : "text-text-secondary hover:bg-lime-500 hover:text-onPrimary"
                                            }`}
                                        >
                                            {g.name}
                                        </button>
                                    ))}
                                </Dropdown.Content>
                            </Dropdown>
                        )}

                        {!placeholder && streakDays > 0 && (
                            <span className="inline-flex items-center gap-1 rounded-full bg-bg-cardAlt px-2 py-0.5 text-[11px] font-semibold text-text-secondary">
                                🔥 {streakDays} hari beruntun
                            </span>
                        )}
                    </div>

                    <p className="mt-2 text-3xl font-bold text-text-primary">
                        {formatRupiah(goal.target_amount)}
                    </p>
                    <p className="mt-1 text-sm text-text-secondary">
                        Terkumpul {formatRupiah(goal.current_amount)}
                    </p>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {goal.on_track && (
                            <span
                                className={
                                    "inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold " +
                                    (goal.on_track.status === "on_track"
                                        ? "bg-state-success/15 text-state-success"
                                        : "bg-state-warning/15 text-state-warning")
                                }
                            >
                                {goal.on_track.status === "on_track"
                                    ? "Sesuai rencana"
                                    : `Tertinggal ${formatRupiah(goal.on_track.gap_amount)}`}
                            </span>
                        )}

                        {goal.days_remaining !== null && (
                            <span className="inline-flex items-center rounded-full bg-bg-cardAlt px-2.5 py-1 text-[11px] font-medium text-text-secondary">
                                {goal.days_remaining > 0
                                    ? `${goal.days_remaining} hari lagi`
                                    : "Sudah jatuh tempo"}
                            </span>
                        )}
                    </div>
                </div>

                <div className="text-right">
                    <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                        Progres
                    </p>
                    <p className="mt-2 text-2xl font-bold text-text-primary">
                        {progressWidth.toFixed(1)}%
                    </p>
                </div>
            </div>

            <div className="mt-6 h-2 w-full overflow-hidden rounded-full bg-bg-cardAlt">
                <div
                    className="h-full rounded-full bg-lime-500 transition-all"
                    style={{ width: `${progressWidth}%` }}
                />
            </div>
        </div>
    );
}
