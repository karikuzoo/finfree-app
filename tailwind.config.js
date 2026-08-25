import defaultTheme from 'tailwindcss/defaultTheme';
import forms from '@tailwindcss/forms';

/** @type {import('tailwindcss').Config} */
export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.jsx',
    ],

    theme: {
        extend: {
            fontFamily: {
                sans: ['Plus Jakarta Sans', ...defaultTheme.fontFamily.sans],
                mono: ['JetBrains Mono', ...defaultTheme.fontFamily.mono],
            },

            // Tema "Malam" — lihat claude/DESIGN.md §2.
            // Seluruh pasangan warna di bawah sudah diverifikasi terhadap
            // WCAG AA; jangan mengubah nilainya tanpa mengukur ulang.
            colors: {
                bg: {
                    base: '#0B0C0B', // latar halaman
                    surface: '#101210', // topbar, sidebar
                    card: '#16181A',
                    cardAlt: '#252825', // hover, panel hasil, skeleton
                },
                border: {
                    DEFAULT: '#282C28', // garis pemisah dekoratif SAJA (1.26:1)
                    strong: '#666D61', // WAJIB untuk batas input & tombol outline (3.33:1)
                },
                lime: {
                    400: '#DEF76F', // hover
                    500: '#CFF04A', // aksen utama
                    600: '#A8C531', // active
                    softBg: '#1E2610', // chip, badge, banner
                },
                onPrimary: '#10130A', // teks di atas fill lime
                text: {
                    primary: '#F0F1EC',
                    secondary: '#A3A99E',
                    muted: '#8B917F',
                    disabled: '#5A6057', // hanya elemen disabled — 2.75:1, bukan teks aktif
                },
                state: {
                    success: '#5FD69A', // mint — sengaja beda hue dari lime
                    danger: '#F0705F',
                    warning: '#F2B950',
                    info: '#7CB8E8',
                },
            },

            borderRadius: {
                card: '16px',
            },
        },
    },

    plugins: [forms],
};
