import { formatRupiah } from '@/utils/format';

/**
 * Pengingat sederhana di Dashboard — BUKAN notifikasi push/email (itu ada
 * di roadmap pasca-MVP PRD, butuh scheduler + queue + preferensi channel
 * yang belum dibangun). Ini murni banner in-app, dihitung dari data yang
 * sudah ada di summary, tanpa infrastruktur baru:
 *
 * - Belum ada setoran hari ini & goal berstatus "behind" → dorong
 *   mencatat setoran hari ini.
 * - Streak sedang jalan tapi belum ada setoran hari ini → dorong supaya
 *   streak tidak putus besok.
 *
 * Kalau tidak ada kondisi yang relevan, komponen ini tidak me-render apa
 * pun (bukan menampilkan pesan "semua aman" generik yang lama-lama
 * diabaikan pengguna). Tombolnya mengarah ke #catat-setoran — anchor id
 * yang kini dipasang pada kartu kalender aktivitas di Dashboard, bukan
 * navigasi halaman baru. Setoran dicatat dengan mengklik tanggal di kalender
 * (PRD FR-32); kartu form tersendiri sudah tidak ada.
 */
export default function DailyReminderBanner({ goal, streakDays, contributedToday }) {
    if (!goal || contributedToday) {
        return null;
    }

    const isBehind = goal.on_track?.status === 'behind';

    if (!isBehind && streakDays <= 0) {
        return null;
    }

    return (
        <div className="flex flex-wrap items-center justify-between gap-3 rounded-card border border-state-warning/30 bg-state-warning/10 px-5 py-4">
            <div>
                <p className="text-sm font-semibold text-text-primary">
                    {isBehind
                        ? `Progres "${goal.name}" tertinggal ${formatRupiah(goal.on_track.gap_amount)} dari rencana.`
                        : `Jangan putus — streak ${streakDays} hari Anda menanti setoran hari ini.`}
                </p>
                <p className="mt-0.5 text-xs text-text-secondary">
                    {isBehind
                        ? 'Catat setoran hari ini untuk mengejar kembali targetnya.'
                        : 'Catat setoran kecil pun cukup untuk menjaga kebiasaan menabung Anda.'}
                </p>
            </div>

            <a
                href="#catat-setoran"
                className="shrink-0 rounded-lg bg-state-warning px-4 py-2 text-sm font-semibold text-onPrimary transition hover:opacity-90"
            >
                Catat Setoran
            </a>
        </div>
    );
}
