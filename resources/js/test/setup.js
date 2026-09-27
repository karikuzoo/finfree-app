import '@testing-library/jest-dom/vitest';
import { cleanup } from '@testing-library/react';
import { afterEach, vi } from 'vitest';

afterEach(() => {
    cleanup();
});

/**
 * `route()` dari Ziggy adalah global yang disuntikkan Laravel ke halaman —
 * di test tidak ada. Pengganti ini cukup menghasilkan URL yang bisa
 * dicocokkan: `route('goals.set-aside', 7)` → "/goals.set-aside/7", dan
 * `route('news.index', { kategori: 'properti' })` → "/news.index?kategori=properti".
 */
globalThis.route = vi.fn((nama, param) => {
    if (param === undefined) return `/${nama}`;
    if (typeof param === 'object') return `/${nama}?${new URLSearchParams(param)}`;

    return `/${nama}/${param}`;
});

/**
 * Recharts memakai ResizeObserver untuk ResponsiveContainer; jsdom tidak
 * menyediakannya.
 */
globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
};
