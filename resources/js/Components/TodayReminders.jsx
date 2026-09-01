import { router } from "@inertiajs/react";

/**
 * Pengingat hari ini di Dashboard.
 *
 * Terpisah dari kalender karena menjawab hal berbeda: kalender mengikuti bulan
 * yang sedang dilihat, panel ini selalu soal hari ini — bahkan saat pengguna
 * sedang menengok bulan lalu.
 *
 * Ini pengingat DI DALAM APLIKASI. Ia tampil ketika pengguna membuka FinGoal,
 * dan tidak mengirim notifikasi ke perangkat saat aplikasi tertutup — itu
 * menuntut Web Push atau email terjadwal, yang belum dibangun. Teksnya sengaja
 * tidak menjanjikan lebih dari yang benar-benar dilakukan.
 */
export default function TodayReminders({ reminders }) {
    if (!reminders || reminders.length === 0) {
        return null;
    }

    const belumSelesai = reminders.filter((r) => !r.completed);

    const toggle = (id) =>
        router.patch(route("reminders.toggle", id), {}, { preserveScroll: true });

    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <div className="flex items-center justify-between gap-3">
                <h2 className="flex items-center gap-2 text-base font-semibold text-text-primary">
                    <svg
                        className="h-4 w-4 text-state-warning"
                        viewBox="0 0 16 16"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="1.5"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M8 2a4 4 0 0 0-4 4c0 3-1.2 4-1.2 4h10.4S12 9 12 6a4 4 0 0 0-4-4Z" />
                        <path d="M6.6 12.5a1.6 1.6 0 0 0 2.8 0" />
                    </svg>
                    Pengingat Hari Ini
                </h2>

                <span className="num-tabular text-xs text-text-muted">
                    {belumSelesai.length} dari {reminders.length}
                </span>
            </div>

            <ul className="mt-4 space-y-1.5">
                {reminders.map((r) => (
                    <li
                        key={r.id}
                        className="flex items-center gap-2.5 rounded-lg border border-border bg-bg-cardAlt px-3 py-2"
                    >
                        <input
                            type="checkbox"
                            checked={r.completed}
                            onChange={() => toggle(r.id)}
                            aria-label={`Tandai selesai: ${r.title}`}
                            className="h-4 w-4 shrink-0 rounded border-border-strong bg-bg-base text-lime-500 focus:ring-lime-500 focus:ring-offset-bg-cardAlt"
                        />

                        {/*
                            Jam yang sudah lewat tanpa ditandai selesai
                            ditonjolkan, bukan disembunyikan — justru itu yang
                            perlu dilihat pengguna.
                        */}
                        <span
                            className={
                                "num-tabular shrink-0 text-xs font-semibold " +
                                (r.completed
                                    ? "text-text-muted"
                                    : r.past
                                      ? "text-state-danger"
                                      : "text-state-warning")
                            }
                        >
                            {r.time}
                        </span>

                        <span
                            className={
                                "min-w-0 flex-1 truncate text-sm " +
                                (r.completed
                                    ? "text-text-muted line-through"
                                    : "text-text-primary")
                            }
                        >
                            {r.title}
                        </span>
                    </li>
                ))}
            </ul>

            <p className="mt-3 text-xs text-text-muted">
                Pengingat tampil saat Anda membuka FinGoal. Buat lewat kalender
                — klik tanggalnya.
            </p>
        </div>
    );
}
