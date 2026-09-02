import { formatRupiah } from "@/utils/format";

function relativeTime(iso) {
    const diffMs = Date.now() - new Date(iso).getTime();
    const diffHours = Math.round(diffMs / (1000 * 60 * 60));
    if (diffHours < 1) return "Baru saja";
    if (diffHours < 24) return `${diffHours} jam lalu`;
    return `${Math.round(diffHours / 24)} hari lalu`;
}

function describe(activity) {
    if (activity.type === "contribution_recorded") {
        return `Nabung ${formatRupiah(activity.amount)} untuk ${activity.goal_name}`;
    }
    if (activity.type === "goal_deleted") {
        return `Menghapus tujuan ${activity.goal_name}`;
    }
    return `Membuat tujuan ${activity.goal_name}`;
}

/**
 * Gabungan setoran + kalkulasi terbaru (FR-15), dikirim sudah terurut oleh
 * DashboardSummaryService — komponen ini murni menampilkan, tidak mengurutkan
 * ulang.
 */
export default function RecentActivityList({ activities }) {
    if (activities.length === 0) {
        return (
            <p className="py-3 text-sm text-text-muted">Belum ada aktivitas.</p>
        );
    }

    return (
        <div className="divide-y divide-border">
            {activities.map((activity, index) => (
                <div key={index} className="py-3">
                    <p className="text-sm text-text-primary">
                        {describe(activity)}
                    </p>
                    <p className="mt-1 text-xs text-text-muted">
                        {relativeTime(activity.occurred_at)}
                    </p>
                </div>
            ))}
        </div>
    );
}
