import { Link, usePage } from '@inertiajs/react';

/**
 * "Jadikan Tujuan" (FR-43): membawa hasil sebuah kalkulator ke form Buat
 * Tujuan, yang terisi lewat GoalController::prefillFromCalculator().
 *
 * Lewat query string, bukan state: tamu diarahkan ke halaman masuk lebih
 * dulu, dan Laravel mengembalikannya ke URL yang dituju beserta query-nya,
 * jadi angkanya tidak hilang di tengah jalan. Tidak ada yang tersimpan
 * sebelum pengguna memberi nama dan menekan simpan di form itu.
 *
 * `params` wajib berbentuk yang diterima prefillFromCalculator:
 * target_amount wajib; name, initial_amount, months, estimated_return_rate,
 * estimated_inflation_rate opsional. Pemanggil mengisinya dari `input` hasil validasi
 * server — nilai yang benar-benar menghasilkan angka di panel hasil — bukan
 * dari isian form yang mungkin sudah diubah tanpa dihitung ulang.
 */
export default function JadikanTujuan({ params, label = 'Jadikan Tujuan', note = null }) {
    const masuk = Boolean(usePage().props.auth?.user);

    return (
        <div className="mt-5">
            <Link
                href={route('goals.create', params)}
                className="inline-flex w-full items-center justify-center rounded-lg border border-border-strong px-4 py-2 text-xs font-semibold uppercase tracking-widest text-text-primary transition hover:border-lime-500 hover:text-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
            >
                {label}
            </Link>
            <p className="mt-2 text-center text-xs text-text-muted">
                {note && <>{note} </>}
                {masuk
                    ? 'Angka di atas ikut terisi — tinggal beri nama tujuannya.'
                    : 'Masuk dulu untuk memantau progresnya — angka di atas ikut terbawa.'}
            </p>
        </div>
    );
}
