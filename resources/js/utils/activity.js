import { formatRupiah } from '@/utils/format';

/** Jenis transaksi → kata kerja yang dipakai di kalimat riwayat. */
const KATA_TRANSAKSI = {
    income: 'Menerima',
    expense: 'Membayar',
    transfer: 'Memindahkan',
    adjustment: 'Menyesuaikan nilai',
    payment: 'Membayar pokok',
};

/**
 * Satu kalimat deskripsi untuk satu baris riwayat.
 *
 * Dua sumber bercampur di sini (lihat HistoryController):
 *
 * - Peristiwa tujuan dari `user_activities` — `goal_created`, `goal_deleted`,
 *   `goal_updated`, `contribution_recorded`. Yang terakhir sudah tidak pernah
 *   dibuat lagi sejak pencatatan setoran dipensiunkan, tetapi barisnya masih
 *   ada di basis data pengguna lama dan tetap harus bisa dibaca.
 * - Transaksi, bertanda awalan `transaction:` — dibentuk saat digabungkan,
 *   bukan disimpan seperti itu.
 *
 * SATU tempat untuk kedua halaman yang menampilkannya (RecentActivityList di
 * Dashboard dan History/Index), supaya kalimatnya tidak diam-diam berbeda.
 */
export function describeActivity(activity) {
    if (activity.type?.startsWith('transaction:')) {
        return jelaskanTransaksi(activity);
    }

    const nama = activity.label ?? activity.goal_name;

    if (activity.type === 'contribution_recorded') {
        return `Nabung ${formatRupiah(activity.amount)} untuk ${nama}`;
    }
    if (activity.type === 'goal_deleted') {
        return `Menghapus tujuan ${nama}`;
    }
    if (activity.type === 'goal_updated') {
        return `Mengubah tujuan ${nama}`;
    }
    return `Membuat tujuan ${nama}`;
}

function jelaskanTransaksi(activity) {
    const jenis = activity.type.slice('transaction:'.length);
    const kata = KATA_TRANSAKSI[jenis] ?? 'Mencatat';
    const nominal = formatRupiah(Math.abs(activity.amount ?? 0));

    return `${kata} ${nominal} — ${activity.label}`;
}

/** Nilainya naik (hijau) atau turun. Dipakai mewarnai nominal di riwayat. */
export function activityIsPositive(activity) {
    if (activity.type === 'contribution_recorded') {
        return true;
    }

    if (!activity.type?.startsWith('transaction:')) {
        return false;
    }

    const jenis = activity.type.slice('transaction:'.length);

    return jenis === 'income' || (jenis === 'adjustment' && (activity.amount ?? 0) > 0);
}

/** Baris ini membawa nominal yang layak ditampilkan di sebelah kanan. */
export function activityHasAmount(activity) {
    return (
        activity.amount !== null &&
        activity.amount !== undefined &&
        (activity.type === 'contribution_recorded' ||
            Boolean(activity.type?.startsWith('transaction:')))
    );
}
