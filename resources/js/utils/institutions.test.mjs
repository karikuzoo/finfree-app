import assert from "node:assert/strict";
import test from "node:test";

import { cariLembaga, lencanaRekening } from "./institutions.js";

/**
 * Kolom `institution` dulu teks bebas. Isian lama harus tetap dikenali
 * dalam bentuk apa pun orang menuliskannya.
 */
test("cariLembaga mengenali cara penulisan yang beragam", () => {
    for (const tulisan of ["BCA", "bca", "Bank BCA", "PT Bank Central Asia Tbk.", "  Bank  BCA "]) {
        assert.equal(cariLembaga(tulisan)?.id, "bca", tulisan);
    }

    assert.equal(cariLembaga("Bank Mandiri")?.id, "mandiri");
    assert.equal(cariLembaga("PT Bank Rakyat Indonesia (Persero) Tbk")?.id, "bri");
    assert.equal(cariLembaga("CIMB Niaga")?.id, "cimb");
    assert.equal(cariLembaga("GoPay")?.id, "gopay");
});

test("cariLembaga diam untuk lembaga yang tidak dikenal", () => {
    for (const tulisan of ["", null, undefined, "Bank DKI", "Koperasi Sejahtera"]) {
        assert.equal(cariLembaga(tulisan), null, String(tulisan));
    }
});

test("saham, reksa dana, emas, dan tunai memakai ikon jenisnya", () => {
    assert.deepEqual(lencanaRekening({ kind: "stock", institution: "Ajaib" }), { jenis: "ikon", ikon: "stock" });
    assert.deepEqual(lencanaRekening({ kind: "gold", institution: "Antam" }), { jenis: "ikon", ikon: "gold" });
    assert.deepEqual(lencanaRekening({ kind: "fund", institution: "Bibit" }), { jenis: "ikon", ikon: "fund" });
    // Walau lembaganya bank: tunai tetap tunai.
    assert.deepEqual(lencanaRekening({ kind: "cash", institution: "BCA" }), { jenis: "ikon", ikon: "cash" });
});

test("bank tanpa lembaga dikenali dari namanya", () => {
    assert.equal(lencanaRekening({ kind: "bank", institution: "", name: "BCA - Utama" }).lembaga?.id, "bca");
    assert.equal(lencanaRekening({ kind: "bank", institution: null, name: "Tabungan Mandiri" }).lembaga?.id, "mandiri");
});

/**
 * "Dana darurat" adalah nama rekening yang wajar — dan bukan dompet DANA.
 * Nama lembaga yang juga kata biasa tidak dikenali dari nama rekening.
 */
test("nama rekening yang kebetulan berupa kata biasa tidak salah dikenali", () => {
    assert.deepEqual(lencanaRekening({ kind: "bank", institution: "", name: "Dana darurat" }), { jenis: "ikon", ikon: "bank" });
    assert.deepEqual(lencanaRekening({ kind: "bank", institution: "", name: "Jago masak" }), { jenis: "ikon", ikon: "bank" });
    // Tetapi dikenali bila memang dipilih sebagai lembaganya.
    assert.equal(lencanaRekening({ kind: "bank", institution: "DANA", name: "Dompet" }).lembaga?.id, "dana");
});

/** Lembaga yang diisi tapi tidak dikenal mengalahkan tebakan dari nama. */
test("lembaga tak dikenal tidak ditimpa tebakan dari nama", () => {
    assert.deepEqual(lencanaRekening({ kind: "bank", institution: "Bank DKI", name: "BCA lama" }), { jenis: "ikon", ikon: "bank" });
});
