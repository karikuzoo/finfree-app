import { fireEvent, render, screen, within } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import NewsIndex from './Index';

// Tata letak publik membaca pengguna login dari usePage(); yang diuji di sini
// isi halamannya, bukan bingkainya.
vi.mock('@/Layouts/PublicLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
}));

/**
 * Jam dibekukan di SIANG hari WIB. "3 jam lalu" dihitung dari tanggal WIB
 * (formatRelativeTime), jadi bila suite dijalankan pukul 00.00–05.00 WIB,
 * waktu 3–5 jam lalu jatuh ke kemarin dan tampil sebagai "kemarin" — test
 * gagal hanya karena jamnya (terjadi 4 Okt 2026). Hanya `Date` yang dipalsukan;
 * timer lain tetap asli supaya interaksi dan render tidak ikut membeku.
 */
beforeEach(() => {
    vi.useFakeTimers({ toFake: ['Date'] });
    vi.setSystemTime(new Date('2026-10-04T12:00:00+07:00'));
});

afterEach(() => {
    vi.useRealTimers();
});

const KATEGORI = [
    { slug: 'kebijakan-moneter', label: 'Kebijakan Moneter', count: 1 },
    { slug: 'pasar-saham', label: 'Pasar Saham', count: 2 },
    { slug: 'properti', label: 'Properti', count: 0 },
];

function artikel(ubah = {}) {
    return {
        id: 1,
        title: 'IHSG Diprediksi Sideways Cenderung Melemah',
        summary: 'Analis memperkirakan IHSG bergerak terbatas.',
        url: 'https://www.kontan.co.id/news/ihsg',
        source: 'Kontan Co Id',
        category: 'pasar-saham',
        category_label: 'Pasar Saham',
        published_at: new Date(Date.now() - 3 * 3600_000).toISOString(),
        ...ubah,
    };
}

function tampilkan(props = {}) {
    return render(
        <NewsIndex
            articles={{ data: [artikel()], current_page: 1, last_page: 1, prev_page_url: null, next_page_url: null }}
            categories={KATEGORI}
            activeCategory={null}
            lastUpdated={new Date().toISOString()}
            stale={false}
            {...props}
        />,
    );
}

describe('Halaman Berita', () => {
    it('menampilkan judul, sumber, waktu, dan ringkasan', () => {
        tampilkan();

        expect(screen.getByText('Kontan Co Id')).toBeInTheDocument();
        expect(screen.getByText('3 jam lalu')).toBeInTheDocument();
        expect(screen.getByText('Analis memperkirakan IHSG bergerak terbatas.')).toBeInTheDocument();
    });

    /**
     * Artikel dibaca di situs penerbitnya. `noopener` supaya situs itu tidak
     * bisa mengendalikan tab Arus lewat window.opener.
     */
    it('judul menaut ke artikel asli di tab baru dengan aman', () => {
        tampilkan();

        const tautan = screen.getByRole('link', { name: /IHSG Diprediksi Sideways/ });

        expect(tautan).toHaveAttribute('href', 'https://www.kontan.co.id/news/ihsg');
        expect(tautan).toHaveAttribute('target', '_blank');
        expect(tautan.getAttribute('rel')).toContain('noopener');
        expect(tautan).toHaveAccessibleName(/buka di tab baru/);
    });

    it('filter kategori menandai yang aktif dan menyebut jumlahnya', () => {
        tampilkan({ activeCategory: 'pasar-saham' });

        const nav = screen.getByRole('navigation', { name: 'Kategori berita' });
        const aktif = within(nav).getByRole('link', { current: 'page' });

        expect(aktif).toHaveTextContent('Pasar Saham2');
        expect(aktif).toHaveAttribute('href', '/news.index?kategori=pasar-saham');
        // "Semua" menjumlahkan seluruh kategori.
        expect(within(nav).getByRole('link', { name: /Semua/ })).toHaveTextContent('Semua3');
    });

    it('label kategori tampil di "Semua", tidak di dalam kategorinya sendiri', () => {
        const { unmount } = tampilkan({ activeCategory: null });
        expect(screen.getAllByText('Pasar Saham').length).toBeGreaterThan(1);
        unmount();

        tampilkan({ activeCategory: 'pasar-saham' });
        const kartu = screen.getByRole('listitem');
        expect(within(kartu).queryByText('Pasar Saham')).toBeNull();
    });

    /**
     * Cache basi tetap DITAMPILKAN — berita kemarin lebih berguna daripada
     * halaman kosong — tetapi disebut terus terang.
     */
    it('cache basi tetap tampil, dengan banner', () => {
        tampilkan({ stale: true, lastUpdated: new Date(Date.now() - 5 * 3600_000).toISOString() });

        expect(screen.getByRole('status')).toHaveTextContent('Berita belum diperbarui sejak 5 jam lalu');
        expect(screen.getByRole('link', { name: /IHSG Diprediksi Sideways/ })).toBeInTheDocument();
    });

    it('tanpa berita sama sekali: keterangan sedang dihimpun, tanpa banner basi', () => {
        tampilkan({
            articles: { data: [], current_page: 1, last_page: 1 },
            categories: KATEGORI.map((k) => ({ ...k, count: 0 })),
            stale: true,
            lastUpdated: null,
        });

        expect(screen.getByText('Berita belum tersedia')).toBeInTheDocument();
        expect(screen.queryByRole('status')).toBeNull();
    });

    it('kategori kosong menyarankan kategori lain', () => {
        tampilkan({ articles: { data: [], current_page: 1, last_page: 1 }, activeCategory: 'properti' });

        expect(screen.getByText('Belum ada berita di kategori ini')).toBeInTheDocument();
    });

    /**
     * Foto dimuat langsung dari server penerbit (D-16). Dekoratif — judul di
     * sebelahnya sudah menjelaskan beritanya — dan tanpa referrer, karena
     * sebagian server gambar penerbit menolak permintaan dari situs lain.
     */
    it('foto artikel tampil di samping tulisan, dimuat dari penerbit', () => {
        const { container } = tampilkan({
            articles: {
                data: [artikel({ image: 'https://img.kontan.co.id/ihsg.jpg' })],
                current_page: 1,
                last_page: 1,
            },
        });

        const foto = container.querySelector('li img');
        expect(foto).toHaveAttribute('src', 'https://img.kontan.co.id/ihsg.jpg');
        expect(foto).toHaveAttribute('alt', '');
        expect(foto).toHaveAttribute('referrerpolicy', 'no-referrer');
        expect(foto).toHaveAttribute('loading', 'lazy');
    });

    it('foto yang gagal dimuat diganti sampul kategori, bukan jadi kotak rusak', () => {
        const { container } = tampilkan({
            articles: {
                data: [artikel({ image: 'https://img.kontan.co.id/hilang.jpg' })],
                current_page: 1,
                last_page: 1,
            },
        });

        fireEvent.error(container.querySelector('li img'));

        expect(container.querySelector('li img')).toBeNull();
        expect(container.querySelector('[data-sampul-kategori]')).not.toBeNull();
        // Tulisannya tetap ada.
        expect(screen.getByRole('link', { name: /IHSG Diprediksi Sideways/ })).toBeInTheDocument();
    });

    /**
     * Foto dimatikan (NEWS_SHOW_IMAGES, D-16) atau artikelnya memang tanpa
     * foto: server mengirim null, dan kartu memakai sampul kategori buatan
     * sendiri dengan ukuran yang sama supaya daftar tidak meloncat.
     */
    it('tanpa foto, kartu memakai sampul kategori', () => {
        const { container } = tampilkan({
            articles: { data: [artikel({ image: null })], current_page: 1, last_page: 1 },
        });

        expect(container.querySelector('li img')).toBeNull();
        const sampul = container.querySelector('[data-sampul-kategori]');
        expect(sampul).toHaveAttribute('aria-hidden', 'true');
        expect(sampul).toHaveAttribute('data-sampul-kategori', 'pasar-saham');
    });

    /** NFR-9: disclaimer permanen, bukan sekali tampil. */
    it('selalu menyebut sumbernya dan bahwa ini bukan nasihat investasi', () => {
        tampilkan();

        expect(screen.getByText(/melalui NewsData\.io/)).toHaveTextContent('Bukan nasihat investasi.');
    });

    it('paginasi hanya muncul bila ada halaman lain', () => {
        const { unmount } = tampilkan();
        expect(screen.queryByText(/Halaman 1 dari/)).toBeNull();
        unmount();

        tampilkan({
            articles: {
                data: [artikel()],
                current_page: 1,
                last_page: 2,
                prev_page_url: null,
                next_page_url: '/berita?page=2',
            },
        });
        expect(screen.getByText('Halaman 1 dari 2')).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Lebih lama →' })).toHaveAttribute('href', '/berita?page=2');
    });
});
