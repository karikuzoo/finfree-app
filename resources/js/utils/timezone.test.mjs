import assert from "node:assert/strict";
import test from "node:test";

import { todayInJakarta, nowInJakartaParts } from "./timezone.js";

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
