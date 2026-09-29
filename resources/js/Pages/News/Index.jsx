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
    // Foto yang gagal dimuat (dihapus penerbit, diblokir) diganti sampul
    // kategori, bukan dibiarkan jadi kotak rusak.
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
                server penerbit, tidak disalin ke Arus. Hanya dikirim server
                bila NEWS_SHOW_IMAGES menyala; selain itu sampul kategori. Di SAMPING tulisan,
                bukan di atas — foto di atas menambah tinggi setiap kartu,
                padahal halaman ini baru saja diringkas supaya tidak panjang.

                alt="" karena dekoratif: judul di sebelahnya sudah menjelaskan
                beritanya, dan pembaca layar tidak perlu mendengar keduanya.
                `no-referrer` karena sebagian server gambar penerbit menolak
                permintaan yang membawa alamat situs lain.
            */}
            {adaFoto ? (
                <img
                    src={artikel.image}
                    alt=""
                    loading="lazy"
                    decoding="async"
                    referrerPolicy="no-referrer"
                    onError={() => setFotoGagal(true)}
                    className="h-16 w-20 shrink-0 self-start rounded-lg bg-bg-cardAlt object-cover sm:h-24 sm:w-32"
                />
            ) : (
                <SampulKategori category={artikel.category} />
            )}
        </li>
    );
}

/** Garis ikon per kategori config/news.php — viewBox 24, stroke. */
const IKON_KATEGORI = {
    // Gedung bank sentral.
    'kebijakan-moneter': <path d="M3 9.5 12 4l9 5.5M5 10v7m4.667-7v7m4.666-7v7M19 10v7M3.5 20h17" />,
    // Garis harga naik.
    'pasar-saham': <path d="M3.5 19.5h17M5 15.5l4.5-4.5 3.5 3 6-6.5m0 0h-4m4 0v4" />,
    // Rumah.
    properti: <path d="M4 11 12 4.5l8 6.5M6 9.5v10h12v-10M10 19.5v-5h4v5" />,
    // Tumpukan koin.
    investasi: <path d="M12 8c3.866 0 7-1.12 7-2.5S15.866 3 12 3 5 4.12 5 5.5 8.134 8 12 8Zm-7-2.5v4C5 10.88 8.134 12 12 12s7-1.12 7-2.5v-4m-14 4v4C5 14.88 8.134 16 12 16s7-1.12 7-2.5v-4m-14 4v4C5 18.88 8.134 20 12 20s7-1.12 7-2.5v-4" />,
    // Bola lampu.
    'tips-keuangan': <path d="M9.5 18h5M10 21h4M12 3a6 6 0 0 0-3.5 10.9c.6.44 1 1.1 1 1.85V16h5v-.25c0-.75.4-1.41 1-1.85A6 6 0 0 0 12 3Z" />,
};

/**
 * Pengganti foto artikel (PRD D-16): ikon kategori buatan sendiri, dengan
 * ukuran yang sama persis dengan fotonya supaya kartu tidak meloncat antara
 * yang berfoto dan yang tidak.
 *
 * Muncul bila foto dimatikan (`NEWS_SHOW_IMAGES`), artikelnya memang tanpa
 * foto, atau fotonya gagal dimuat. Dekoratif — kategori sudah tertulis di
 * baris atas kartu — jadi disembunyikan dari pembaca layar.
 */
function SampulKategori({ category }) {
    return (
        <div
            aria-hidden="true"
            data-sampul-kategori={category}
            className="flex h-16 w-20 shrink-0 items-center justify-center self-start rounded-lg bg-lime-softBg text-lime-500 sm:h-24 sm:w-32"
        >
            <svg className="h-7 w-7 sm:h-9 sm:w-9" viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="1.5" strokeLinecap="round" strokeLinejoin="round">
                {IKON_KATEGORI[category] ?? <path d="M5 4.5h11.5A2.5 2.5 0 0 1 19 7v12.5H7.5A2.5 2.5 0 0 1 5 17V4.5Zm3.5 4h7m-7 3.5h7m-7 3.5h4" />}
            </svg>
        </div>
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
