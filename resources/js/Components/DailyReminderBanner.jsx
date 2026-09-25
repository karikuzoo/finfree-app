import { Link } from "@inertiajs/react";

/**
 * Dorongan di atas Dashboard saat sebuah tujuan tertinggal dari rencananya.
 *
 * Murni IN-APP, bukan notifikasi push atau email — ia hanya muncul saat
 * pengguna membuka dashboard (PRD FR-30).
 *
 * Dulu banner ini berdiri di atas hari beruntun dan setoran harian: "jangan
 * putus streak-mu". Keduanya hilang bersama pencatatan setoran — dana tujuan
 * kini ditandai dari saldo rekening, bukan disetor sedikit demi sedikit tiap
 * hari, sehingga tidak ada lagi kebiasaan harian yang bisa putus.
 *
 * Yang tersisa justru dorongan yang lebih berguna: memberi tahu bahwa
 * targetnya tertinggal, lalu mengantar ke tempat yang bisa memperbaikinya.
 * Tombolnya mengarah ke Rencana menabung, karena di sanalah prioritas dan
 * alokasi disesuaikan — bukan ke form yang sudah tidak ada.
 */
export default function DailyReminderBanner({ goal }) {
    if (goal?.on_track?.status !== "behind") {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-4 rounded-card border border-border bg-lime-softBg px-5 py-4">
            <div className="min-w-0">
                <p className="text-sm font-semibold text-text-primary">
                    {goal.name} sedang tertinggal dari rencananya.
                </p>
                <p className="mt-1 text-sm leading-relaxed text-text-secondary">
                    Naikkan prioritasnya, tambah dana yang terkumpul, atau
                    mundurkan tenggatnya — mana pun yang paling masuk akal.
                </p>
            </div>

            <Link
                href={route("savings-plan.index")}
                className="shrink-0 rounded-lg bg-lime-500 px-4 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
            >
                Buka rencana
            </Link>
        </div>
    );
}
