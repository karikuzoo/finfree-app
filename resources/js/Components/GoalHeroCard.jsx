import { useForm } from '@inertiajs/react';
import { formatRupiah } from '@/utils/format';
import GoalContributionForm from '@/Components/GoalContributionForm';

/**
 * Kartu utama Dashboard (di atas semua kartu lain) — menampilkan target
 * goal tertua pengguna (DashboardSummaryService::summarizeGoal via
 * primary_goal) dan progress bar gabungan `current_amount` (initial +
 * setoran, murni dari backend) dengan `daily_savings_target` (janji
 * harian yang diisi manual lewat form di kartu ini) sebagai proyeksi —
 * BUKAN sebagai nilai tersimpan baru.
 *
 * Angka nominal besar sengaja tidak diwarnai lime, mengikuti aturan warna
 * di SummaryCard — lime di sini hanya dipakai untuk progress bar dan
 * tombol aksi. Badge status memakai warna semantik `state.*`
 * (success/danger), bukan lime, supaya beda maksud dengan aksen lime.
 *
 * `placeholder=true` dipakai saat pengguna belum punya goal sama sekali —
 * `goal` di kondisi ini adalah data contoh/dummy (bukan dari backend),
 * jadi form pengaturan & catat setoran disembunyikan dan diberi label
 * "Contoh" supaya tidak disangka data asli.
 */
export default function GoalHeroCard({ goal, streakDays = 0, placeholder = false }) {
    const { data, setData, patch, processing, recentlySuccessful, errors } =
        useForm({
            daily_savings_target: goal?.daily_savings_target ?? 0,
        });

    if (!goal) {
        return null;
    }

    const submit = (e) => {
        e.preventDefault();
        patch(route('goals.daily-savings-target.update', goal.id), {
            preserveScroll: true,
        });
    };

    const progressWidth = Math.min(100, goal.projected_progress_percentage);

    return (
        <div
            id="catat-setoran"
            className="relative scroll-mt-24 rounded-card border border-border bg-bg-card p-6"
        >
            {placeholder && (
                <span className="absolute right-6 top-6 rounded-full bg-bg-cardAlt px-2.5 py-1 text-[10px] font-semibold uppercase tracking-wide text-text-muted">
                    Contoh tampilan
                </span>
            )}

            <div className="flex flex-wrap items-start justify-between gap-6">
                <div>
                    <div className="flex flex-wrap items-center gap-2">
                        <p className="text-xs font-semibold uppercase tracking-wide text-text-muted">
                            Target Utama · {goal.name}
                        </p>

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
                        {goal.daily_savings_target > 0 && (
                            <>
                                {' '}
                                + {formatRupiah(goal.daily_savings_target)}{' '}
                                rencana hari ini
                            </>
                        )}
                    </p>

                    <div className="mt-3 flex flex-wrap items-center gap-2">
                        {goal.on_track && (
                            <span
                                className={
                                    'inline-flex items-center rounded-full px-2.5 py-1 text-[11px] font-semibold ' +
                                    (goal.on_track.status === 'on_track'
                                        ? 'bg-state-success/15 text-state-success'
                                        : 'bg-state-warning/15 text-state-warning')
                                }
                            >
                                {goal.on_track.status === 'on_track'
                                    ? 'Sesuai rencana'
                                    : `Tertinggal ${formatRupiah(goal.on_track.gap_amount)}`}
                            </span>
                        )}

                        {goal.days_remaining !== null && (
                            <span className="inline-flex items-center rounded-full bg-bg-cardAlt px-2.5 py-1 text-[11px] font-medium text-text-secondary">
                                {goal.days_remaining > 0
                                    ? `${goal.days_remaining} hari lagi`
                                    : 'Sudah jatuh tempo'}
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

            {placeholder ? (
                <p className="mt-5 text-xs text-text-muted">
                    Form pengaturan nominal harian dan catat setoran akan
                    muncul di sini setelah Anda membuat tujuan finansial
                    pertama.
                </p>
            ) : (
                <div className="mt-5 space-y-4">
                    <form
                        onSubmit={submit}
                        className="flex flex-wrap items-center gap-3"
                    >
                        <label
                            htmlFor="daily_savings_target"
                            className="text-sm text-text-secondary"
                        >
                            Sisihkan per hari
                        </label>
                        <span className="text-sm text-text-muted">Rp</span>
                        <input
                            id="daily_savings_target"
                            type="number"
                            min="0"
                            step="1000"
                            value={data.daily_savings_target}
                            onChange={(e) =>
                                setData('daily_savings_target', e.target.value)
                            }
                            className="w-32 rounded-lg border border-border bg-bg-cardAlt px-3 py-1.5 text-sm text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                        />
                        <button
                            type="submit"
                            disabled={processing}
                            className="rounded-lg bg-lime-500 px-3 py-1.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60"
                        >
                            Simpan
                        </button>
                        {recentlySuccessful && (
                            <span className="text-xs text-lime-500">
                                Tersimpan.
                            </span>
                        )}
                        {errors.daily_savings_target && (
                            <span className="text-xs text-state-danger">
                                {errors.daily_savings_target}
                            </span>
                        )}
                    </form>

                    <GoalContributionForm goalId={goal.id} />
                </div>
            )}
        </div>
    );
}
