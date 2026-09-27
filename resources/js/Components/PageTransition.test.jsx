import { act, render, screen } from '@testing-library/react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import PageTransition from './PageTransition';

/**
 * Router Inertia menyiarkan kunjungan lewat event DOM `inertia:start` dan
 * `inertia:finish` di `document`. Test ini menyiarkannya sendiri, tanpa
 * kunjungan sungguhan.
 */
function siarkan(nama, visit = {}) {
    act(() => {
        document.dispatchEvent(
            new CustomEvent(`inertia:${nama}`, {
                detail: { visit: { method: 'get', only: [], prefetch: false, ...visit } },
            }),
        );
    });
}

function tunggu(ms) {
    act(() => {
        vi.advanceTimersByTime(ms);
    });
}

describe('PageTransition', () => {
    beforeEach(() => vi.useFakeTimers());
    afterEach(() => vi.useRealTimers());

    it('isi halaman beranimasi masuk', () => {
        render(<PageTransition><p>Isi</p></PageTransition>);

        expect(screen.getByText('Isi').parentElement).toHaveClass('animate-masuk-halaman');
    });

    it('perpindahan lambat memunculkan "Memuat…" dan meredupkan isi', () => {
        render(<PageTransition><p>Isi</p></PageTransition>);

        siarkan('start');
        tunggu(300);

        expect(screen.getByRole('status')).toHaveTextContent('Memuat…');
        expect(screen.getByText('Isi').parentElement).toHaveAttribute('aria-busy', 'true');

        siarkan('finish');

        expect(screen.queryByRole('status')).toBeNull();
        expect(screen.getByText('Isi').parentElement).toHaveAttribute('aria-busy', 'false');
    });

    /** Penanda yang berkedip sepersekian detik lebih mengganggu daripada tidak ada. */
    it('perpindahan cepat tidak memunculkan apa pun', () => {
        render(<PageTransition><p>Isi</p></PageTransition>);

        siarkan('start');
        tunggu(100);
        siarkan('finish');
        tunggu(500);

        expect(screen.queryByRole('status')).toBeNull();
    });

    /** Menyimpan formulir sudah punya penandanya sendiri di tombolnya. */
    it('kiriman formulir tidak meredupkan halaman', () => {
        render(<PageTransition><p>Isi</p></PageTransition>);

        siarkan('start', { method: 'post' });
        tunggu(1000);

        expect(screen.queryByRole('status')).toBeNull();
    });

    /** Menggeser bulan kalender (`only: ['calendar']`) tidak mengganti halaman. */
    it('muat ulang sebagian tidak meredupkan halaman', () => {
        render(<PageTransition><p>Isi</p></PageTransition>);

        siarkan('start', { only: ['calendar'] });
        tunggu(1000);

        expect(screen.queryByRole('status')).toBeNull();
    });
});
