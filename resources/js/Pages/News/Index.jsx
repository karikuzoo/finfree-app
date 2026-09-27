import PublicLayout from '@/Layouts/PublicLayout';
import { formatRelativeTime } from '@/utils/timezone';
import { Head, Link } from '@inertiajs/react';
import { useState } from 'react';

/**
 * Berita finansial (PRD FR-16, FR-17, keputusan D-16).
 *
 * Seluruh isinya datang jadi dari NewsController, yang membaca cache —
 * halaman ini tidak pernah memanggil sumber berita sendiri, dan kunci API
 * tidak pernah sampai ke browser.
 *
 * Yang ditampilkan: judul, ringkasan, sumber, waktu terbit, foto artikel,
 * dan tautan ke artikel aslinya. Artikel dibaca di situs penerbitnya — Arus
 * tidak menyalin isinya, dan fotonya dimuat langsung dari server penerbit
 * (PRD D-16).
 */
export default function NewsIndex({ articles, categories, activeCategory, lastUpdated, stale }) {
    const adaBerita = articles.data.length > 0;
    const totalSemua = categories.reduce((n, k) => n + k.count, 0);

    return (
        <PublicLayout>
            <Head title="Berita" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <h1 className="text-3xl font-bold tracking-tight text-text-primary">
                    Berita &amp; Analisis Keuangan
                </h1>
                <p className="mt-3 max-w-2xl text-base leading-relaxed text-text-secondary">
                    Perkembangan ekonomi Indonesia — kebijakan moneter, pasar
                    saham, properti, dan investasi — dihimpun dari media
                    pemberitaan sebagai konteks saat Anda mengambil keputusan
                    finansial.
                </p>

                <nav aria-label="Kategori berita" className="mt-6 flex flex-wrap gap-2">
                    <Chip href={route('news.index')} aktif={activeCategory === null} jumlah={totalSemua}>
                        Semua
                    </Chip>
                    {categories.map((k) => (
                        <Chip
                            key={k.slug}
                            href={route('news.index', { kategori: k.slug })}
                            aktif={activeCategory === k.slug}
                            jumlah={k.count}
                        >
                            {k.label}
                        </Chip>
                    ))}
                </nav>

                {stale && totalSemua > 0 && <BannerBasi lastUpdated={lastUpdated} />}

                {/*
                    Dua kolom mulai layar sedang: satu kolom membuat 20 kartu
                    memanjang empat layar. Kartunya sengaja pendek — judul
                    maks. 3 baris, ringkasan 2 — karena yang dicari di sini
                    adalah judul, isinya dibaca di situs penerbit.
                */}
                {adaBerita ? (
                    <ul className="mt-5 grid grid-cols-1 gap-3 md:grid-cols-2">
                        {articles.data.map((a) => (
                            <KartuBerita key={a.id} artikel={a} tampilkanKategori={activeCategory === null} />
                        ))}
                    </ul>
                ) : (
                    <Kosong adaBeritaLain={totalSemua > 0} />
                )}

                {(articles.prev_page_url || articles.next_page_url) && (
                    <div className="mt-8 flex items-center justify-between">
                        {articles.prev_page_url ? (
                            <Link
                                href={articles.prev_page_url}
                                className="rounded-lg border border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition hover:bg-bg-cardAlt"
                            >
                                ← Lebih baru
                            </Link>
                        ) : (
                            <span />
                        )}

                        <p className="text-xs text-text-muted">
                            Halaman {articles.current_page} dari {articles.last_page}
                        </p>

                        {articles.next_page_url ? (
                            <Link
                                href={articles.next_page_url}
                                className="rounded-lg border border-border-strong px-4 py-2 text-sm font-medium text-text-primary transition hover:bg-bg-cardAlt"
                            >
                                Lebih lama →
                            </Link>
                        ) : (
                            <span />
                        )}
                    </div>
                )}

                {/*
                    Disclaimer permanen, bukan sekali tampil (NFR-9): berita
                    yang dihimpun otomatis mudah terbaca sebagai rekomendasi.
                */}
                <p className="mt-8 border-t border-border pt-5 text-xs leading-relaxed text-text-muted">
                    Berita dihimpun otomatis dari media pemberitaan melalui
                    NewsData.io dan diperbarui tiap jam. Arus tidak menulis,
                    menyunting, atau memverifikasi isinya — baca selengkapnya
                    di situs penerbitnya. Bukan nasihat investasi.
                </p>
            </div>
        </PublicLayout>
    );
}

function Chip({ href, aktif, jumlah, children }) {
    return (
        <Link
            href={href}
            preserveScroll
            aria-current={aktif ? 'page' : undefined}
            className={
                'rounded-full border px-3 py-1.5 text-xs font-medium transition focus:outline-none focus:ring-2 focus:ring-lime-500 ' +
                (aktif
                    ? 'border-lime-500 bg-lime-softBg text-lime-500'
                    : 'border-border text-text-secondary hover:border-border-strong hover:text-text-primary')
            }
        >
            {children}
            <span className="num-tabular ml-1.5 text-text-muted">{jumlah}</span>
        </Link>
    );
}

function KartuBerita({ artikel, tampilkanKategori }) {
    // Foto yang gagal dimuat (dihapus penerbit, diblokir) disembunyikan,
    // bukan dibiarkan jadi kotak rusak.
    const [fotoGagal, setFotoGagal] = useState(false);
    const adaFoto = Boolean(artikel.image) && !fotoGagal;

    return (
        <li className="flex gap-3 rounded-card border border-border bg-bg-card p-4 transition hover:border-border-strong">
            <div className="flex min-w-0 flex-1 flex-col">
                <div className="flex flex-wrap items-center gap-x-2 gap-y-1 text-xs text-text-muted">
                    {tampilkanKategori && artikel.category_label && (
                        <>
                            <span className="font-semibold text-lime-500">{artikel.category_label}</span>
                            <span aria-hidden="true">·</span>
                        </>
                    )}
                    <span className="font-medium text-text-secondary">{artikel.source}</span>
                    <span aria-hidden="true">·</span>
                    <time dateTime={artikel.published_at}>{formatRelativeTime(artikel.published_at)}</time>
                </div>

                <h2 className="mt-1.5 line-clamp-3 text-[15px] font-semibold leading-snug text-text-primary">
                    {/*
                        Tab baru, karena artikelnya di situs lain dan pembaca
                        kembali ke daftar ini. `noopener noreferrer` supaya situs
                        penerbit tidak bisa mengendalikan tab Arus lewat
                        window.opener.
                    */}
                    <a
                        href={artikel.url}
                        target="_blank"
                        rel="noopener noreferrer"
                        className="hover:text-lime-500 focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        {artikel.title}
                        <span className="sr-only"> (buka di tab baru)</span>
                    </a>
                </h2>

                {/*
                    Disembunyikan di ponsel: di layar sempit dua baris ringkasan
                    hampir menggandakan tinggi kartu, dan judul sudah cukup untuk
                    memilih mana yang dibuka. Pembungkusnya yang disembunyikan,
                    bukan <p>-nya — `line-clamp` butuh display-nya sendiri.
                */}
                {artikel.summary && (
                    <div className="hidden sm:block">
                        <p className="mt-1.5 line-clamp-2 text-sm leading-relaxed text-text-secondary">
                            {artikel.summary}
                        </p>
                    </div>
                )}
            </div>

            {/*
                Foto artikel dari penerbitnya (PRD D-16): dimuat langsung dari
                server penerbit, tidak disalin ke Arus. Di SAMPING tulisan,
                bukan di atas — foto di atas menambah tinggi setiap kartu,
                padahal halaman ini baru saja diringkas supaya tidak panjang.

                alt="" karena dekoratif: judul di sebelahnya sudah menjelaskan
                beritanya, dan pembaca layar tidak perlu mendengar keduanya.
                `no-referrer` karena sebagian server gambar penerbit menolak
                permintaan yang membawa alamat situs lain.
            */}
            {adaFoto && (
                <img
                    src={artikel.image}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    referrerPolicy="no-referrer"
                    onError={() => setFotoGagal(true)}
                    className="h-16 w-20 shrink-0 self-start rounded-lg bg-bg-cardAlt object-cover sm:h-24 sm:w-32"
                />
            )}
        </li>
    );
}

/**
 * Cache yang lama tidak diperbarui tetap DITAMPILKAN — berita kemarin lebih
 * berguna daripada halaman kosong — tetapi disebut terus terang, supaya
 * pembaca tidak mengira itu berita terbaru.
 */
function BannerBasi({ lastUpdated }) {
    return (
        <div role="status" className="mt-6 rounded-lg border border-state-warning/40 bg-bg-cardAlt px-4 py-3 text-sm leading-relaxed text-text-secondary">
            {lastUpdated
                ? `Berita belum diperbarui sejak ${formatRelativeTime(lastUpdated)}. Yang tampil di bawah adalah hasil pengambilan terakhir.`
                : 'Berita belum pernah berhasil diperbarui. Yang tampil di bawah adalah hasil pengambilan terakhir.'}
        </div>
    );
}

function Kosong({ adaBeritaLain }) {
    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card px-6 py-14 text-center">
            <svg
                className="mx-auto h-14 w-14 text-text-muted"
                viewBox="0 0 32 32"
                fill="none"
                stroke="currentColor"
                strokeWidth="1.5"
                strokeLinecap="round"
                strokeLinejoin="round"
                aria-hidden="true"
            >
                <rect x="4" y="6" width="24" height="20" rx="2.5" />
                <path d="M9 12h8M9 17h14M9 21h10" />
            </svg>

            {adaBeritaLain ? (
                <>
                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Belum ada berita di kategori ini
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Coba kategori lain, atau kembali lagi nanti — berita
                        diperbarui tiap jam.
                    </p>
                </>
            ) : (
                <>
                    <h2 className="mt-5 text-lg font-semibold text-text-primary">
                        Berita belum tersedia
                    </h2>
                    <p className="mx-auto mt-2 max-w-md text-sm leading-relaxed text-text-secondary">
                        Berita sedang dihimpun dan akan muncul di sini setelah
                        pengambilan pertama selesai.
                    </p>
                </>
            )}
        </div>
    );
}
