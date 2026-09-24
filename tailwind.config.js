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
                sans: ['Segoe UI', ...defaultTheme.fontFamily.sans],
                // JetBrains Mono dikeluarkan dari tumpukan: fontnya tidak lagi
                // diunduh (lihat app.blade.php), jadi menyebutnya di sini hanya
                // menjanjikan sesuatu yang tidak pernah tiba.
                mono: [...defaultTheme.fontFamily.mono],
            },

            // Tema Arus — lihat docs/ARUS-REDESIGN.md.
            // Nama token lime dipertahankan agar seluruh komponen memakai aksen mint.
            colors: {
                bg: {
                    base: '#101719', // latar halaman
                    surface: '#131C1F', // topbar, sidebar
                    card: '#182124',
                    cardAlt: '#253235', // hover, panel hasil, skeleton
                },
                border: {
                    DEFAULT: '#2C383B', // garis pemisah dekoratif
                    strong: '#708780', // batas input & tombol outline
                },
                lime: {
                    400: '#B0F6DC', // hover
                    500: '#98EDCE', // aksen utama
                    600: '#72D4B1', // active
                    softBg: '#223932', // chip, badge, banner
                },
                onPrimary: '#102C22', // teks di atas fill lime
                text: {
                    primary: '#EFF5F3',
                    secondary: '#B2C2C3',
                    muted: '#A1B1B3',
                    disabled: '#647578', // hanya elemen disabled
                },
                state: {
                    success: '#9BECCE', // mint — sengaja beda hue dari lime
                    danger: '#FFACB8',
                    warning: '#F1CC80',
                    info: '#9AC9FB',
                },
            },

            borderRadius: {
                card: '16px',
            },
        },
    },

    plugins: [forms],
};
