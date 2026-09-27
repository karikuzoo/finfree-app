/**
 * "Hari ini" versi WIB (Asia/Jakarta, UTC+7) — BUKAN jam lokal
 * perangkat/browser pengunjung.
 *
 * Kenapa ini perlu (lihat juga tests/Feature/AppTimezoneTest.php di
 * backend, yang menjaga hal serupa di sisi server): backend aplikasi ini
 * sengaja di-set ke Asia/Jakarta (config/app.php), bukan default UTC
 * Laravel, supaya "hari ini" konsisten dengan WIB, bukan bergeser 7 jam
 * di dini hari. Tapi `new Date()` di JavaScript SELALU memakai zona
 * waktu perangkat pengunjung, bukan zona waktu server. Kalau perangkat
 * pengunjung di-set ke zona waktu lain (atau jamnya keliru), "hari ini"
 * versi frontend bisa berbeda dari versi backend — kalender aktivitas
 * bisa menandai tanggal yang salah, atau tombol "Hari ini" di DateInput
 * bisa melompat ke tanggal yang keliru.
 *
 * Pakai `Intl.DateTimeFormat` dengan `timeZone: 'Asia/Jakarta'` supaya
 * hasilnya SELALU WIB, apa pun zona waktu perangkat pengunjung.
 */

const jakartaFormatter = new Intl.DateTimeFormat('en-CA', {
    timeZone: 'Asia/Jakarta',
    year: 'numeric',
    month: '2-digit',
    day: '2-digit',
});

/**
 * Tanggal WIB saat ini, format "2026-09-02" — dipakai di mana pun kode
 * butuh "hari ini" sebagai string YYYY-MM-DD (mis. nilai default input
 * tanggal, atau perbandingan dengan tanggal lain).
 *
 * Locale 'en-CA' kebetulan memformat sebagai YYYY-MM-DD secara asali,
 * jadi tidak perlu susun ulang bagian tahun/bulan/tanggalnya manual.
 */
export function todayInJakarta() {
    return jakartaFormatter.format(new Date());
}

/**
 * Bagian-bagian tanggal WIB saat ini secara terpisah — dipakai kode
 * yang butuh angka tahun/bulan/tanggal sendiri-sendiri (mis. menentukan
 * bulan yang sedang ditampilkan di date picker). `bulan` sudah
 * 0-indexed (Januari = 0) supaya langsung cocok dipakai ke `new
 * Date(tahun, bulan, tanggal)` bawaan JS.
 */
export function nowInJakartaParts() {
    const [tahun, bulan, tanggal] = todayInJakarta().split('-').map(Number);

    return { tahun, bulan: bulan - 1, tanggal };
}

const jakartaTimeFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    hour: '2-digit',
    minute: '2-digit',
    hour12: false,
});

const jakartaDateLongFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    weekday: 'long',
    day: 'numeric',
    month: 'long',
    year: 'numeric',
});

/**
 * Jam WIB (format "14:05") dari sebuah waktu TERTENTU (bukan "sekarang")
 * — mis. `occurred_at` dari log aktivitas. Beda dari `todayInJakarta()`
 * di atas: ini menerima instant APA SAJA (string ISO8601), bukan selalu
 * "sekarang". Tetap aman dari bug yang sama: `Intl.DateTimeFormat` dengan
 * `timeZone: 'Asia/Jakarta'` eksplisit membuat hasilnya selalu WIB, apa
 * pun zona waktu perangkat pembacanya — bukan format jam lokal browser.
 */
export function formatJakartaTime(iso) {
    return jakartaTimeFormatter.format(new Date(iso));
}

/** "Kamis, 3 September 2026" dari sebuah waktu tertentu — lihat catatan di formatJakartaTime. */
export function formatJakartaDateLong(iso) {
    return jakartaDateLongFormatter.format(new Date(iso));
}

const jakartaDateShortFormatter = new Intl.DateTimeFormat('id-ID', {
    timeZone: 'Asia/Jakarta',
    day: 'numeric',
    month: 'short',
    year: 'numeric',
});

/**
 * "baru saja", "12 menit lalu", "3 jam lalu", "kemarin", "4 hari lalu" —
 * lalu tanggal biasa ("20 Sep 2026") setelah seminggu. Dipakai untuk waktu
 * terbit berita, yang lebih mudah dinilai kesegarannya dalam bentuk ini
 * daripada sebagai tanggal.
 *
 * "kemarin" dihitung dari TANGGAL WIB, bukan selisih 24 jam: berita pukul
 * 23.00 kemarin yang dibaca pukul 07.00 hari ini adalah berita kemarin,
 * meski selisihnya baru 8 jam.
 *
 * `sekarang` bisa diisi supaya hasilnya bisa diuji.
 */
export function formatRelativeTime(iso, sekarang = new Date()) {
    const waktu = new Date(iso);
    const detik = Math.floor((sekarang - waktu) / 1000);

    if (Number.isNaN(detik)) return '';
    if (detik < 60) return 'baru saja';

    const menit = Math.floor(detik / 60);
    if (menit < 60) return `${menit} menit lalu`;

    const hariIni = tanggalJakarta(sekarang);
    const hariItu = tanggalJakarta(waktu);
    const selisihHari = Math.round((Date.parse(hariIni) - Date.parse(hariItu)) / 86_400_000);

    if (selisihHari === 0) return `${Math.floor(menit / 60)} jam lalu`;
    if (selisihHari === 1) return 'kemarin';
    if (selisihHari < 7) return `${selisihHari} hari lalu`;

    return jakartaDateShortFormatter.format(waktu);
}

/** "2026-09-27" menurut kalender WIB. */
function tanggalJakarta(date) {
    return new Intl.DateTimeFormat('en-CA', { timeZone: 'Asia/Jakarta' }).format(date);
}
