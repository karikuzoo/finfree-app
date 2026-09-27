import { router } from '@inertiajs/react';
import { useEffect, useState } from 'react';

/**
 * Apakah sedang berpindah HALAMAN — bukan menyimpan formulir, bukan memuat
 * ulang sebagian.
 *
 * Hanya kunjungan GET tanpa `only` yang dihitung. Menyimpan formulir sudah
 * punya penandanya sendiri di tombolnya ("Menyimpan…"), dan meredupkan
 * seluruh halaman untuk itu terasa seperti aplikasinya macet. Muat ulang
 * sebagian (`only: ['calendar']` saat menggeser bulan kalender) juga tidak:
 * sisa halamannya tidak berubah.
 *
 * Ditunda `tunda` milidetik. Perpindahan yang cepat — hampir semuanya di
 * jaringan lokal — tidak memunculkan apa pun; penanda yang berkedip sepersekian
 * detik lebih mengganggu daripada tidak ada penanda sama sekali.
 */
export function useSedangBerpindah(tunda = 250) {
    const [aktif, setAktif] = useState(false);

    useEffect(() => {
        let timer = null;

        const lepasMulai = router.on('start', (e) => {
            const kunjungan = e.detail.visit;

            if (kunjungan.method !== 'get' || kunjungan.only?.length || kunjungan.prefetch) {
                return;
            }

            clearTimeout(timer);
            timer = setTimeout(() => setAktif(true), tunda);
        });

        const lepasSelesai = router.on('finish', () => {
            clearTimeout(timer);
            setAktif(false);
        });

        return () => {
            clearTimeout(timer);
            lepasMulai();
            lepasSelesai();
        };
    }, [tunda]);

    return aktif;
}

/**
 * Pembungkus isi halaman: animasi masuk tiap kali halaman berganti, dan
 * redup + penanda "Memuat…" selama perpindahan yang lambat.
 *
 * Animasinya berjalan saat komponen DIPASANG. Inertia memasang ulang halaman
 * pada setiap kunjungan GET biasa (kunci halamannya berganti), jadi pindah
 * menu selalu beranimasi — sedangkan kiriman formulir dengan
 * `preserveState` tidak, dan memang tidak seharusnya.
 *
 * Sidebar dan topbar berada di luar pembungkus ini: bingkai yang diam sambil
 * isinya berganti membuat perpindahan terasa sebagai "pindah menu", bukan
 * "memuat ulang aplikasi".
 */
export default function PageTransition({ children }) {
    const berpindah = useSedangBerpindah();

    return (
        <>
            <div
                aria-busy={berpindah}
                className={
                    'animate-masuk-halaman transition-opacity duration-200 motion-reduce:animate-none ' +
                    (berpindah ? 'pointer-events-none opacity-50' : 'opacity-100')
                }
            >
                {children}
            </div>

            {berpindah && <IndikatorMemuat />}
        </>
    );
}

/** Pil kecil di tengah atas layar — terlihat, tanpa menutupi isi halaman. */
export function IndikatorMemuat() {
    return (
        <div
            role="status"
            aria-live="polite"
            className="pointer-events-none fixed left-1/2 top-24 z-40 flex -translate-x-1/2 items-center gap-2 rounded-full border border-border-strong bg-bg-card px-4 py-2 text-sm font-medium text-text-primary shadow-lg"
        >
            <svg
                className="h-4 w-4 animate-spin text-lime-500 motion-reduce:animate-none"
                viewBox="0 0 24 24"
                fill="none"
                aria-hidden="true"
            >
                <circle cx="12" cy="12" r="9" stroke="currentColor" strokeOpacity="0.25" strokeWidth="3" />
                <path d="M21 12a9 9 0 0 0-9-9" stroke="currentColor" strokeWidth="3" strokeLinecap="round" />
            </svg>
            Memuat…
        </div>
    );
}
