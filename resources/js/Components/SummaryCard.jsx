/**
 * Kartu ringkasan Dashboard (DESIGN.md §5.2).
 *
 * Angka besar sengaja TIDAK berwarna lime — lime dipakai untuk aksi/hasil
 * kalkulator (lihat aturan pemakaian lime di DESIGN.md), bukan untuk setiap
 * angka finansial yang tampil di layar.
 */
export default function SummaryCard({ label, value, hint }) {
    return (
        <div className="rounded-card border border-border bg-bg-card p-5">
            <p className="text-xs font-medium text-text-secondary">{label}</p>
            <p className="num-tabular mt-2 text-2xl font-bold text-text-primary">
                {value}
            </p>
            {hint && <p className="mt-2 text-xs text-text-muted">{hint}</p>}
        </div>
    );
}
