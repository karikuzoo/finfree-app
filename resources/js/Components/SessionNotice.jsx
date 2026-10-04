import { usePage } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Pemberitahuan sekali tampil dari server (prop bersama `notice`) — saat ini
 * hanya "kiriman tadi belum tersimpan" sesudah token kedaluwarsa (419, lihat
 * bootstrap/app.php). Dipasang di bingkai halaman, jadi berlaku di halaman
 * mana pun pengguna dikembalikan.
 */
export default function SessionNotice({ className = 'mx-auto mt-4 max-w-5xl' }) {
    const notice = usePage().props.notice;
    const [tutup, setTutup] = useState(false);

    // Pemberitahuan baru (kunjungan berikutnya) tampil lagi walau yang lama
    // sudah ditutup.
    useEffect(() => setTutup(false), [notice]);

    if (!notice || tutup) return null;

    return (
        <div
            role="status"
            className={`flex items-start justify-between gap-3 rounded-lg border border-state-warning/50 bg-bg-cardAlt px-4 py-3 text-sm text-text-secondary ${className}`}
        >
            <p>{notice}</p>
            <button
                type="button"
                onClick={() => setTutup(true)}
                aria-label="Tutup pemberitahuan"
                className="shrink-0 rounded px-1 text-text-muted hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
            >
                ×
            </button>
        </div>
    );
}
