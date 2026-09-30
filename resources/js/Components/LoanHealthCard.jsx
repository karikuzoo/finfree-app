import { formatRupiah } from '@/utils/format';

const TAMPILAN = {
    healthy: { label: 'Sehat', warna: 'text-state-success', garis: 'border-l-state-success', bingkai: 'ring-state-success/60' },
    caution: { label: 'Waspada', warna: 'text-state-warning', garis: 'border-l-state-warning', bingkai: 'ring-state-warning/60' },
    risky: { label: 'Berisiko', warna: 'text-state-danger', garis: 'border-l-state-danger', bingkai: 'ring-state-danger/60' },
};

const persen = (n) => `${String(n).replace('.', ',')}%`;

/**
 * Hasil cek kesehatan cicilan KPR — `health` dari LoanHealthService, apa
 * adanya. Label, angka, dan kalimat alasannya semua dihitung server; di sini
 * tidak ada ambang batas yang ditulis ulang (CLAUDE.md §6.9).
 *
 * Alasan ditampilkan sebagai daftar, bukan disembunyikan di balik label:
 * label saja hanya bisa dipercaya atau diabaikan, sedangkan alasannya bisa
 * ditindaklanjuti — memperpanjang tenor, menambah uang muka, melunasi
 * cicilan lain lebih dulu.
 */
export default function LoanHealthCard({ health }) {
    const t = TAMPILAN[health.status];
    const adaTerberat = Boolean(health.worst);
    const b = health.thresholds;
    // Pendapatan hanya disebut per keadaan bila memang berbeda-beda — yaitu
    // bila kenaikan gaji diisi. Tanpa itu, angkanya sama dengan isian pengguna.
    const naikGaji = (health.income_growth ?? 0) > 0;

    return (
        <section aria-labelledby="judul-kesehatan" className={`rounded-card border border-border border-l-2 ${t.garis} bg-bg-card p-6`}>
            {/*
                Label menempel pada JUDUL, bukan di ujung kanan baris. Dulu ia
                di kanan, tepat di atas kartu keadaan kedua, sehingga terbaca
                sebagai milik kartu itu — padahal ini penilaian keseluruhan.
                Kartu mana yang menjadi dasarnya ditandai di kartu itu sendiri.
            */}
            <div className="flex flex-wrap items-center gap-x-3 gap-y-1">
                <h2 id="judul-kesehatan" className="text-base font-semibold text-text-primary">
                    Kesehatan cicilan
                </h2>
                <span className={`rounded-full bg-bg-cardAlt px-3 py-1 text-sm font-bold ${t.warna}`}>{t.label}</span>
            </div>

            <div className={`mt-4 grid gap-3 ${adaTerberat ? 'sm:grid-cols-2' : ''}`}>
                <Keadaan judul="Angsuran sekarang" data={health.now} tampilkanPendapatan={naikGaji} dasar={!adaTerberat && t} />
                {adaTerberat && <Keadaan judul={kapital(health.worst.label)} data={health.worst} tampilkanPendapatan={naikGaji} dasar={t} />}
            </div>

            <ul className="mt-4 space-y-1.5 text-sm leading-relaxed text-text-secondary">
                {health.reasons.map((r) => (
                    <li key={r} className="flex gap-2">
                        <span aria-hidden="true" className="text-text-muted">•</span>
                        <span>{r}</span>
                    </li>
                ))}
            </ul>

            {health.monthly_taxes > 0 && (
                <p className="mt-3 text-xs text-text-muted">
                    Pajak tahunan dihitung {formatRupiah(health.monthly_taxes)} per bulan dan mengurangi sisa uang,
                    tetapi tidak masuk rasio cicilan — bank juga tidak memasukkannya.
                </p>
            )}

            {naikGaji && (
                <p className="mt-3 text-xs text-text-muted">
                    Pendapatan tiap jenjang dinaikkan {persen(health.income_growth)} per tahun. Pengeluaran,
                    cicilan lain, dan pajak dianggap tetap — bila biaya hidup ikut naik, keadaan sebenarnya
                    lebih berat dari angka di atas.
                </p>
            )}

            <p className="mt-4 border-t border-border pt-3 text-xs leading-relaxed text-text-muted">
                Patokan: rasio cicilan terhadap pendapatan sampai {b.dsr_healthy_max}% sehat,{' '}
                {b.dsr_healthy_max}–{b.dsr_caution_max}% waspada, di atas {b.dsr_caution_max}% berisiko;
                sisa uang di bawah {b.residual_min_percentage}% pendapatan dinilai waspada. Ini simulasi
                edukatif, bukan penilaian kredit — bank juga menilai riwayat kredit, pekerjaan, dan agunan.
            </p>
        </section>
    );
}

/**
 * Satu keadaan (sekarang, atau jenjang terberat). `dasar` diisi tampilan
 * status bila keadaan INI yang menentukan label — kartunya diberi bingkai
 * berwarna dan tanda "Dasar penilaian", supaya hubungan label dengan kartu
 * tidak bergantung pada letaknya.
 */
function Keadaan({ judul, data, tampilkanPendapatan = false, dasar = null }) {
    return (
        <div className={`rounded-lg bg-bg-cardAlt p-4 ${dasar ? `ring-1 ${dasar.bingkai}` : ''}`}>
            <div className="flex flex-wrap items-center justify-between gap-2">
                <p className="text-xs text-text-muted">{judul}</p>
                {dasar && (
                    <span className={`text-[11px] font-semibold uppercase tracking-wide ${dasar.warna}`}>
                        Dasar penilaian
                    </span>
                )}
            </div>
            <dl className="mt-2 space-y-1.5 text-sm">
                {tampilkanPendapatan && (
                    <div className="flex items-baseline justify-between gap-3">
                        <dt className="text-text-secondary">Pendapatan / bulan</dt>
                        <dd className="num-tabular text-text-primary">{formatRupiah(data.income)}</dd>
                    </div>
                )}
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-text-secondary">Rasio cicilan</dt>
                    <dd className="num-tabular font-semibold text-text-primary">{persen(data.dsr)}</dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-text-secondary">Total cicilan</dt>
                    <dd className="num-tabular text-text-primary">{formatRupiah(data.installments)}</dd>
                </div>
                <div className="flex items-baseline justify-between gap-3">
                    <dt className="text-text-secondary">Sisa uang / bulan</dt>
                    <dd className={`num-tabular font-semibold ${data.residual < 0 ? 'text-state-danger' : 'text-text-primary'}`}>
                        {data.residual < 0 ? '−' : ''}
                        {formatRupiah(Math.abs(data.residual))}
                    </dd>
                </div>
            </dl>
        </div>
    );
}

const kapital = (s) => s.charAt(0).toUpperCase() + s.slice(1);
