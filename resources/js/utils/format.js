/**
 * Pemformatan angka untuk tampilan.
 *
 * Backend selalu mengirim uang sebagai angka mentah, bukan string terformat
 * (CLAUDE.md §10.1). Merapikannya adalah tugas frontend, dan seluruhnya
 * dikerjakan di berkas ini supaya formatnya seragam di semua halaman.
 */

const idNumber = new Intl.NumberFormat('id-ID');

/** 1500000 -> "1.500.000" */
export function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '';

    return idNumber.format(Math.round(Number(value)));
}

/** 1500000 -> "Rp 1.500.000" */
export function formatRupiah(value) {
    if (value === null || value === undefined || value === '') return '';

    return `Rp ${formatNumber(value)}`;
}

/** "1.500.000" atau "Rp 1.500.000" -> 1500000 */
export function parseNumber(text) {
    const digits = String(text).replace(/[^\d]/g, '');

    return digits === '' ? '' : Number(digits);
}

/**
 * 126 -> "10 tahun 6 bulan". Dipakai untuk menerjemahkan input jangka waktu
 * yang satuannya bulan menjadi kalimat yang lebih mudah dibayangkan.
 */
export function formatDuration(months) {
    const n = Number(months);
    if (!n || n < 1) return '';

    const years = Math.floor(n / 12);
    const rest = n % 12;

    if (years === 0) return `${rest} bulan`;
    if (rest === 0) return `${years} tahun`;

    return `${years} tahun ${rest} bulan`;
}
