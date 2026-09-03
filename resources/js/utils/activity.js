import { formatRupiah } from '@/utils/format';

/**
 * Satu kalimat deskripsi untuk 1 baris UserActivity (`type`: 'goal_created'
 * | 'goal_deleted' | 'contribution_recorded' — lihat migrasi
 * create_user_activities_table). Dipakai RecentActivityList.jsx (Dashboard,
 * dibatasi 10 item) dan History/Index.jsx (daftar penuh) — SATU tempat
 * supaya kalimatnya tidak diam-diam berbeda antara dua halaman yang
 * menampilkan data yang sama.
 */
export function describeActivity(activity) {
    if (activity.type === 'contribution_recorded') {
        return `Nabung ${formatRupiah(activity.amount)} untuk ${activity.goal_name}`;
    }
    if (activity.type === 'goal_deleted') {
        return `Menghapus tujuan ${activity.goal_name}`;
    }
    return `Membuat tujuan ${activity.goal_name}`;
}
