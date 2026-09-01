/**
 * Ikon menu sidebar — SVG inline tulisan tangan, BUKAN dari library
 * (lucide-react dkk belum ada di package.json project ini), supaya
 * konsisten dengan pola yang sudah dipakai di seluruh project (ikon
 * hamburger, chevron dropdown, ikon halaman kosong Dashboard/Goal/
 * Wallet/History semuanya inline SVG tulisan tangan) tanpa perlu
 * `npm install` tambahan.
 *
 * Semua ikon: viewBox 24x24, stroke 1.75, strokeLinecap/Linejoin round,
 * fill none — supaya seragam ukuran & ketebalan garisnya satu sama
 * lain, dan mewarisi warna teks link-nya lewat `currentColor` (jadi
 * otomatis ikut berubah warna saat menu aktif/hover, tanpa prop warna
 * terpisah). Pengecualian: WalletIcon (lihat catatan di bawah).
 */

const commonProps = {
    viewBox: "0 0 24 24",
    fill: "none",
    stroke: "currentColor",
    strokeWidth: 1.75,
    strokeLinecap: "round",
    strokeLinejoin: "round",
    "aria-hidden": "true",
};

export function DashboardIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <rect x="3.5" y="3.5" width="8" height="8" rx="1.5" />
            <rect x="12.5" y="3.5" width="8" height="5" rx="1.5" />
            <rect x="12.5" y="10.5" width="8" height="10" rx="1.5" />
            <rect x="3.5" y="13.5" width="8" height="7" rx="1.5" />
        </svg>
    );
}

export function GoalIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <circle cx="12" cy="12" r="8.5" />
            <circle cx="12" cy="12" r="4.5" />
            <circle cx="12" cy="12" r="1" fill="currentColor" stroke="none" />
        </svg>
    );
}

export function WalletIcon({ className }) {
    // Ikon dari Flaticon ("wallet-buyer") — beda gaya dari ikon lain di
    // file ini (solid/fill, bukan outline/stroke), karena SVG aslinya
    // memang digambar begitu. viewBox 24x24-nya kebetulan sudah cocok
    // dengan standar file ini, jadi tidak perlu penyesuaian ukuran.
    //
    // PENTING: className diterima dari pemanggil (AuthenticatedLayout
    // memberi "h-5 w-5 shrink-0") — JANGAN hardcode ukuran/warna di sini
    // seperti "h-14 w-14 text-text-muted", itu akan menimpa ukuran &
    // warna aktif/hover yang seharusnya datang dari sidebarLinkClass().
    return (
        <svg
            className={className}
            viewBox="0 0 24 24"
            fill="currentColor"
            aria-hidden="true"
        >
            <path d="m19,18c-1.379,0-2.5-1.121-2.5-2.5s1.121-2.5,2.5-2.5,2.5,1.121,2.5,2.5-1.121,2.5-2.5,2.5Zm4,6h-8c-.311,0-.604-.145-.793-.392-.189-.247-.253-.567-.172-.868.591-2.203,2.633-3.741,4.966-3.741s4.375,1.538,4.966,3.741c.081.3.017.621-.172.868-.189.247-.482.392-.793.392Zm1-8.5V7c0-1.654-1.346-3-3-3H5c-.859,0-1.671-.372-2.235-.999.55-.614,1.348-1.001,2.235-1.001h18c.552,0,1-.448,1-1s-.448-1-1-1H5C3.178,0,1.58.98.706,2.44c-.025.037-.047.076-.067.116-.407.723-.639,1.557-.639,2.444v10c0,2.757,2.243,5,5,5h8.162c.567-.795,1.297-1.453,2.133-1.955-.499-.725-.795-1.6-.795-2.545,0-2.481,2.019-4.5,4.5-4.5s4.5,2.019,4.5,4.5h.5Z" />
        </svg>
    );
}

export function HistoryIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <circle cx="12" cy="12" r="8.5" />
            <path d="M12 7v5l3.5 2" />
        </svg>
    );
}

export function CalculatorIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <rect x="5" y="3.5" width="14" height="17" rx="1.75" />
            <path d="M8 7.5h8" />
            <path d="M8.25 12h.01M12 12h.01M15.75 12h.01" />
            <path d="M8.25 15.5h.01M12 15.5h.01M15.75 15.5h.01" />
        </svg>
    );
}

export function NewsIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <rect x="3.5" y="5.5" width="17" height="13" rx="1.75" />
            <path d="M7 9.5h6" />
            <path d="M7 13h10" />
            <path d="M7 16h10" />
        </svg>
    );
}

export function UserIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <circle cx="12" cy="8.5" r="3.5" />
            <path d="M4.5 20c1.2-3.5 4-5.5 7.5-5.5s6.3 2 7.5 5.5" />
        </svg>
    );
}

export function LogoutIcon({ className }) {
    return (
        <svg className={className} {...commonProps}>
            <path d="M14.5 8V6a1.5 1.5 0 00-1.5-1.5H6.5A1.5 1.5 0 005 6v12a1.5 1.5 0 001.5 1.5H13a1.5 1.5 0 001.5-1.5v-2" />
            <path d="M9.5 12H20" />
            <path d="M17 8.5l3.5 3.5-3.5 3.5" />
        </svg>
    );
}
