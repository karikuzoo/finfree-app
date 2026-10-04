import assert from "node:assert/strict";
import test from "node:test";

import { formatRelativeTime, todayInJakarta, nowInJakartaParts, tambahBulan } from "./timezone.js";

/**
 * Pasangan frontend dari tests/Feature/AppTimezoneTest.php di backend.
 *
 * Backend sudah dijaga tetap Asia/Jakarta lewat test itu, tapi
 * `new Date()` di JavaScript tidak tahu apa-apa soal config Laravel —
 * ia selalu memakai zona waktu perangkat yang menjalankannya. Fungsi di
 * timezone.js ini yang menjembatani, dan test di sini memverifikasi
 * hasilnya benar-benar terikat ke Asia/Jakarta, bukan zona waktu mesin
 * yang menjalankan test (CI, laptop developer, dst — yang zona waktunya
 * bisa apa saja).
 */

test("todayInJakarta menghasilkan format YYYY-MM-DD", () => {
    const hasil = todayInJakarta();

    assert.match(hasil, /^\d{4}-\d{2}-\d{2}$/);
});

test("nowInJakartaParts konsisten dengan todayInJakarta", () => {
    const iso = todayInJakarta();
    const { tahun, bulan, tanggal } = nowInJakartaParts();
    const isoDariParts = `${tahun}-${String(bulan + 1).padStart(2, "0")}-${String(tanggal).padStart(2, "0")}`;

    assert.equal(isoDariParts, iso);
});

test("nowInJakartaParts.bulan sudah 0-indexed (Januari = 0)", () => {
    const { bulan } = nowInJakartaParts();

    assert.ok(bulan >= 0 && bulan <= 11);
});

/**
 * Waktu terbit berita. "kemarin" dihitung dari TANGGAL WIB, bukan selisih 24
 * jam — berita pukul 23.00 WIB yang dibaca pukul 07.00 esoknya adalah
 * berita kemarin, meski selisihnya baru 8 jam.
 */
test("formatRelativeTime mengikuti kalender WIB", () => {
    // 27 Sep 2026 07.00 WIB = 00.00 UTC
    const sekarang = new Date("2026-09-27T00:00:00Z");

    assert.equal(formatRelativeTime("2026-09-26T23:59:30Z", sekarang), "baru saja");
    assert.equal(formatRelativeTime("2026-09-26T23:48:00Z", sekarang), "12 menit lalu");
    // 04.00 WIB hari yang sama
    assert.equal(formatRelativeTime("2026-09-26T21:00:00Z", sekarang), "3 jam lalu");
    // 23.00 WIB kemarin — baru 8 jam, tetapi sudah tanggal kemarin
    assert.equal(formatRelativeTime("2026-09-26T16:00:00Z", sekarang), "kemarin");
    assert.equal(formatRelativeTime("2026-09-23T05:00:00Z", sekarang), "4 hari lalu");
    assert.equal(formatRelativeTime("2026-09-15T05:00:00Z", sekarang), "15 Sep 2026");
});

test("formatRelativeTime diam untuk masukan rusak", () => {
    assert.equal(formatRelativeTime("bukan tanggal"), "");
});

// bulan di nowInJakartaParts 0-indexed: { bulan: 9 } = Oktober.
test("tambahBulan menghitung n bulan dari tanggal WIB yang diberikan", () => {
    const sekarang = { tahun: 2026, bulan: 9, tanggal: 4 };

    assert.equal(tambahBulan(1, sekarang), "2026-11-04");
    assert.equal(tambahBulan(3, sekarang), "2027-01-04");
    assert.equal(tambahBulan(300, sekarang), "2051-10-04");
    // Isian dari form datang sebagai teks.
    assert.equal(tambahBulan("12", sekarang), "2027-10-04");
});
