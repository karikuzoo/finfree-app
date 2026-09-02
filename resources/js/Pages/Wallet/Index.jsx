import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import { Head, Link } from "@inertiajs/react";
import { formatRupiah } from "@/utils/format";

export default function WalletIndex({ totalAssets = 0, goals = [] }) {
    const activeGoals = goals.filter((g) => g.current_amount > 0);

    return (
        <AuthenticatedLayout>
            <Head title="Dompet" />

            <div className="mx-auto max-w-4xl px-4 py-10 sm:px-6 lg:px-8">
                <div className="flex flex-wrap items-start justify-between gap-4">
                    <div>
                        <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                            Dompet
                        </h1>
                        <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                            Rincian dana yang telah Anda kumpulkan dan saran penempatannya untuk masing-masing tujuan.
                        </p>
                    </div>
                </div>

                {/* Total Kekayaan */}
                <div className="mt-8 rounded-card border border-border bg-bg-card p-6 sm:p-8 text-center">
                    <p className="text-sm font-semibold uppercase tracking-wide text-text-muted">
                        Total Uang Terkumpul
                    </p>
                    <p className="mt-3 text-4xl sm:text-5xl font-bold text-text-primary num-tabular">
                        {formatRupiah(totalAssets)}
                    </p>
                </div>

                {totalAssets > 0 ? (
                    <div className="mt-8 space-y-6">
                        {activeGoals.map((goal) => (
                            <div key={goal.id} className="rounded-card border border-border bg-bg-card p-5 sm:p-6">
                                <div className="flex items-center justify-between border-b border-border pb-4">
                                    <h2 className="text-lg font-bold text-text-primary">{goal.name}</h2>
                                    <p className="text-xl font-bold text-text-primary num-tabular">
                                        {formatRupiah(goal.current_amount)}
                                    </p>
                                </div>
                                
                                <div className="mt-4">
                                    <p className="mb-3 text-sm font-semibold text-text-secondary">Saran Penyimpanan</p>
                                    <div className="grid grid-cols-1 gap-4 sm:grid-cols-2 lg:grid-cols-4">
                                        {goal.suggested_allocation?.map((alloc) => {
                                            const amount = goal.current_amount * (alloc.percentage / 100);
                                            return (
                                                <div key={alloc.instrument} className="rounded-lg bg-bg-cardAlt p-3">
                                                    <p className="text-xs text-text-muted">
                                                        {alloc.instrument} <span className="font-semibold text-text-secondary">({alloc.percentage}%)</span>
                                                    </p>
                                                    <p className="mt-1 text-sm font-bold text-text-primary">
                                                        {formatRupiah(amount)}
                                                    </p>
                                                </div>
                                            );
                                        })}
                                    </div>
                                </div>
                            </div>
                        ))}
                    </div>
                ) : (
                    <div className="mt-8 rounded-card border border-border bg-bg-card px-6 py-14 text-center">
                        <svg className="mx-auto h-14 w-14 text-text-muted" viewBox="0 0 32 32" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round" aria-hidden="true">
                            <path d="M6 10a2 2 0 012-2h16a2 2 0 012 2v13a2 2 0 01-2 2H8a2 2 0 01-2-2V10z" />
                            <path d="M6 13h20" />
                            <circle cx="22.5" cy="19" r="1.6" fill="currentColor" stroke="none" />
                        </svg>

                        <h2 className="mt-5 text-lg font-semibold text-text-primary">
                            Dompet Masih Kosong
                        </h2>
                        <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                            Mulai catat setoran pada tujuan finansial Anda, dan pantau rincian uang yang sudah terkumpul di halaman ini.
                        </p>

                        <div className="mt-7">
                            <Link
                                href={route("goals.index")}
                                className="inline-block rounded-lg bg-lime-500 px-5 py-3 text-sm font-semibold text-onPrimary transition hover:bg-lime-400"
                            >
                                Buat Tujuan Baru
                            </Link>
                        </div>
                    </div>
                )}
            </div>
        </AuthenticatedLayout>
    );
}
