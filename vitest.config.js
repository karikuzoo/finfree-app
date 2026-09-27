import { defineConfig } from 'vitest/config';
import react from '@vitejs/plugin-react';
import { fileURLToPath } from 'node:url';

/**
 * Test komponen React (`npm run test:ui`).
 *
 * Terpisah dari vite.config.js: plugin Laravel di sana hanya berguna untuk
 * build dan dev server, dan di lingkungan test ia mencari manifest yang tidak
 * ada.
 *
 * Hanya `*.test.jsx`. Berkas `*.test.mjs` adalah test fungsi utilitas yang
 * dijalankan `node --test` (`npm run test:js`) — memakai `node:test`, bukan
 * vitest, jadi tidak boleh ikut terambil di sini.
 */
export default defineConfig({
    plugins: [react()],
    resolve: {
        alias: {
            '@': fileURLToPath(new URL('./resources/js', import.meta.url)),
        },
    },
    test: {
        environment: 'jsdom',
        include: ['resources/js/**/*.test.jsx'],
        setupFiles: ['resources/js/test/setup.js'],
        restoreMocks: true,
    },
});
