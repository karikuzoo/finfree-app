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
