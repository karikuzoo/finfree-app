/**
 * Monogram Arus — huruf "a" satu lantai di atas ubin mint.
 *
 * Digambar sebagai LINGKARAN + GARIS, bukan sebagai <text>. Versi <text>
 * menyerahkan bentuk hurufnya kepada font yang kebetulan terpasang di mesin
 * pembaca, sehingga logonya berubah rupa di macOS, Linux, dan Android. Sebuah
 * merek tidak boleh bergantung pada itu.
 *
 * Warnanya sengaja dipatok, bukan memakai token Tailwind: ini lambang merek,
 * yang justru harus tetap sama walau paletnya suatu saat diganti.
 */
export default function ApplicationLogo({ className = "", ...props }) {
    return (
        <svg
            {...props}
            className={className}
            viewBox="0 0 36 36"
            xmlns="http://www.w3.org/2000/svg"
            role="img"
            aria-label="Arus"
        >
            <rect width="36" height="36" rx="11" fill="#98EDCE" />
            <g
                fill="none"
                stroke="#102C22"
                strokeWidth="3.2"
                strokeLinecap="round"
            >
                {/*
                    Tiang HARUS setinggi mangkuknya persis (12.6–23.4, sama
                    dengan sisi atas-bawah lingkaran). Begitu tiangnya menjulang
                    ke atas, hurufnya terbaca "d", bukan "a".
                */}
                <circle cx="18" cy="18" r="5.4" />
                <path d="M23.4 12.6V23.4" />
            </g>
        </svg>
    );
}
