import '../css/app.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/react';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createRoot } from 'react-dom/client';

const appName = import.meta.env.VITE_APP_NAME || 'Arus';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.jsx`,
            import.meta.glob('./Pages/**/*.jsx'),
        ),
    setup({ el, App, props }) {
        const root = createRoot(el);

        root.render(<App {...props} />);
    },
    // Garis tipis di tepi atas layar saat berpindah halaman — pasangan
    // penanda "Memuat…" di PageTransition.jsx. Warnanya aksen mint tema Arus.
    // Tundaannya disamakan (250 ms) supaya keduanya muncul bersamaan.
    progress: {
        color: '#98EDCE',
        delay: 250,
    },
});
