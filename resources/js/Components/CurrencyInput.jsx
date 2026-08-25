import { formatNumber, parseNumber } from '@/utils/format';

/**
 * Input nominal rupiah.
 *
 * Yang dilihat pengguna sudah berpemisah ribuan ("1.500.000"), tetapi yang
 * disimpan ke state tetap angka murni (1500000). Pemisahnya dibuang saat
 * dibaca, jadi nilai yang dikirim ke backend selalu bersih.
 *
 * Kenapa `type="text"` dan bukan `type="number"`: input bertipe number tidak
 * mengizinkan titik sebagai pemisah ribuan, memunculkan tombol panah naik-turun
 * yang tidak berguna untuk nominal besar, dan mudah berubah tanpa sengaja saat
 * roda tetikus digulir di atasnya.
 */
export default function CurrencyInput({
    value,
    onChange,
    className = '',
    ...props
}) {
    return (
        <div className="relative">
            <span className="pointer-events-none absolute inset-y-0 left-0 flex items-center pl-3 text-sm text-text-muted">
                Rp
            </span>

            <input
                {...props}
                type="text"
                inputMode="numeric"
                value={formatNumber(value)}
                onChange={(e) => onChange(parseNumber(e.target.value))}
                className={
                    'num-tabular w-full rounded-lg border-border-strong bg-bg-base pl-10 text-text-primary placeholder:text-text-muted focus:border-lime-500 focus:ring-lime-500 ' +
                    className
                }
            />
        </div>
    );
}
