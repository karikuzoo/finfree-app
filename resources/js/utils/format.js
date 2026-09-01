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

/**
 * Bentuk ringkas untuk sumbu grafik dan ruang sempit.
 * 1500000 -> "Rp 1,5 jt" ; 2750000000 -> "Rp 2,75 M"
 */
export function formatCompactRupiah(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);

    // Membuang nol di belakang koma saja: "2,0 jt" -> "2 jt", "1,50" -> "1,5".
    //
    // Versi sebelumnya memakai /\.?0+$/ — titiknya opsional, sehingga pola itu
    // juga memakan nol pada bilangan BULAT: 50 menjadi "5", dan 100 menjadi
    // "1". Akibatnya Rp 100.000 tampil sebagai "Rp 1 rb", meleset seratus kali
    // lipat. Titik kini wajib ada agar hanya bagian desimal yang tersentuh.
    const trim = (x, digits) =>
        x
            .toFixed(digits)
            .replace(/(\.\d*?)0+$/, '$1') // nol berlebih setelah titik
            .replace(/\.$/, '') // titik yang jadi menggantung
            .replace('.', ',');

    if (abs >= 1_000_000_000) return `Rp ${trim(n / 1_000_000_000, 2)} M`;
    if (abs >= 1_000_000) return `Rp ${trim(n / 1_000_000, 1)} jt`;
    if (abs >= 1_000) return `Rp ${trim(n / 1_000, 0)} rb`;

    return `Rp ${Math.round(n)}`;
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
