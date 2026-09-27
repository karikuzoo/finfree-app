import { lencanaRekening } from '@/utils/institutions';

/**
 * Lencana rekening: singkatan lembaga di atas warna khasnya, atau ikon jenis
 * aset (tunai, saham, reksa dana, emas). Lihat utils/institutions.js untuk
 * alasan lencananya dibuat sendiri, bukan logo resmi.
 */
const UKURAN = {
    sm: { kotak: 'h-8 w-8 rounded-md', ikon: 'h-4 w-4', teks: ['text-[11px]', 'text-[9px]'] },
    md: { kotak: 'h-10 w-10 rounded-lg', ikon: 'h-5 w-5', teks: ['text-xs', 'text-[10px]'] },
};

/**
 * Warna ikon jenis — nada lembut di atas kartu gelap, satu keluarga dengan
 * warna state tema (tailwind.config.js), supaya lencana aset tidak bersaing
 * dengan lencana bank yang memang berwarna cerah.
 */
const IKON = {
    bank: { label: 'Bank', latar: '#253235', warna: '#B2C2C3' },
    cash: { label: 'Tunai', latar: '#223932', warna: '#98EDCE' },
    stock: { label: 'Saham', latar: '#1D3345', warna: '#9AC9FB' },
    fund: { label: 'Reksa dana', latar: '#2E2A45', warna: '#C4B5FD' },
    gold: { label: 'Emas', latar: '#3A3118', warna: '#F1CC80' },
};

/**
 * `rekening`: lencana diturunkan dari jenis + lembaganya (kartu rekening).
 * `lembaga`: lencana lembaga/platform itu sendiri (pemilih lembaga), apa pun
 * jenis rekeningnya.
 */
export default function AccountBadge({ rekening, lembaga: lembagaLangsung, size = 'md', className = '' }) {
    const lencana = lembagaLangsung
        ? { jenis: 'lembaga', lembaga: lembagaLangsung }
        : lencanaRekening(rekening ?? {});
    const u = UKURAN[size] ?? UKURAN.md;

    if (lencana.jenis === 'lembaga') {
        const { lembaga } = lencana;

        return (
            <span
                role="img"
                aria-label={lembaga.nama}
                title={lembaga.nama}
                className={`inline-flex shrink-0 select-none items-center justify-center font-bold leading-none tracking-tight ${u.kotak} ${lembaga.singkatan.length > 3 ? u.teks[1] : u.teks[0]} ${className}`}
                style={{ backgroundColor: lembaga.warna, color: lembaga.teks }}
            >
                {lembaga.singkatan}
            </span>
        );
    }

    const ikon = IKON[lencana.ikon] ?? IKON.bank;

    return (
        <span
            role="img"
            aria-label={ikon.label}
            title={ikon.label}
            className={`inline-flex shrink-0 items-center justify-center ${u.kotak} ${className}`}
            style={{ backgroundColor: ikon.latar, color: ikon.warna }}
        >
            <IkonJenis jenis={lencana.ikon} className={u.ikon} />
        </span>
    );
}

function IkonJenis({ jenis, className }) {
    const umum = {
        className,
        viewBox: '0 0 24 24',
        fill: 'none',
        stroke: 'currentColor',
        strokeWidth: 1.75,
        strokeLinecap: 'round',
        strokeLinejoin: 'round',
        'aria-hidden': 'true',
    };

    switch (jenis) {
        case 'cash':
            // Lembar uang kertas.
            return (
                <svg {...umum}>
                    <rect x="2.5" y="6" width="19" height="12" rx="2" />
                    <circle cx="12" cy="12" r="2.5" />
                    <path d="M6 9.5v5M18 9.5v5" />
                </svg>
            );
        case 'stock':
            // Grafik naik.
            return (
                <svg {...umum}>
                    <path d="M3.5 19.5h17" />
                    <path d="M4.5 15.5l4.5-4.5 3.5 3.5 6.5-7" />
                    <path d="M14.5 7.5H19V12" />
                </svg>
            );
        case 'fund':
            // Diagram lingkaran: dana yang dikelola dan dibagi ke banyak aset.
            return (
                <svg {...umum}>
                    <path d="M12 3.5a8.5 8.5 0 1 0 8.5 8.5H12z" />
                    <path d="M15 3.9A8.5 8.5 0 0 1 20.1 9H15z" />
                </svg>
            );
        case 'gold':
            // Emas batangan.
            return (
                <svg {...umum}>
                    <path d="M7 9.5h10l3 8.5H4z" />
                    <path d="M9.5 9.5 11 5.5h2l1.5 4" />
                </svg>
            );
        default:
            // Gedung bank.
            return (
                <svg {...umum}>
                    <path d="M3.5 9.5 12 4.5l8.5 5" />
                    <path d="M5.5 10v7M9.5 10v7M14.5 10v7M18.5 10v7" />
                    <path d="M3.5 19.5h17" />
                </svg>
            );
    }
}
