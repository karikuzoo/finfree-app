import assert from "node:assert/strict";
import test from "node:test";

import {
    formatCompactRupiah,
    formatNumber,
    formatRupiah,
    parseNumber,
    spellRupiah,
} from "./format.js";

/**
 * Bug yang melahirkan berkas test ini: pemangkas nol memakai pola /\.?0+$/,
 * dengan titik yang OPSIONAL — sehingga ia juga memakan nol pada bilangan
 * bulat. Rp 50.000 tampil sebagai "Rp 5 rb", dan Rp 100.000 sebagai "Rp 1 rb".
 *
 * Kesalahannya makin besar justru pada angka yang makin bulat, yaitu angka
 * yang paling sering dipakai orang. Ia lolos berbulan-bulan karena hasilnya
 * tetap "terlihat wajar" — hanya salah.
 */
test("formatCompactRupiah tidak memakan nol pada bilangan bulat", () => {
    assert.equal(formatCompactRupiah(5000), "Rp 5 rb");
    assert.equal(formatCompactRupiah(50000), "Rp 50 rb");
    assert.equal(formatCompactRupiah(100000), "Rp 100 rb");
    assert.equal(formatCompactRupiah(20000), "Rp 20 rb");
    assert.equal(formatCompactRupiah(999000), "Rp 999 rb");
});

test("formatCompactRupiah tetap membuang nol di belakang koma", () => {
    assert.equal(formatCompactRupiah(2000000), "Rp 2 jt");
    assert.equal(formatCompactRupiah(1500000), "Rp 1,5 jt");
    assert.equal(formatCompactRupiah(10000000), "Rp 10 jt");
    assert.equal(formatCompactRupiah(2750000000), "Rp 2,75 M");
    assert.equal(formatCompactRupiah(1000000000), "Rp 1 M");
});

test("formatCompactRupiah menangani nilai kecil dan nol", () => {
    assert.equal(formatCompactRupiah(0), "Rp 0");
    assert.equal(formatCompactRupiah(999), "Rp 999");
    assert.equal(formatCompactRupiah(null), "Rp 0");
    assert.equal(formatCompactRupiah(""), "Rp 0");
});

test("formatCompactRupiah menangani nilai negatif", () => {
    assert.equal(formatCompactRupiah(-50000), "Rp -50 rb");
    assert.equal(formatCompactRupiah(-1500000), "Rp -1,5 jt");
});

test("formatNumber memberi pemisah ribuan dan mengosongkan nilai kosong", () => {
    assert.equal(formatNumber(1500000), "1.500.000");
    assert.equal(formatNumber(50000), "50.000");
    assert.equal(formatNumber(""), "");
    assert.equal(formatNumber(null), "");
});

test("formatRupiah memberi awalan Rp", () => {
    assert.equal(formatRupiah(50000), "Rp 50.000");
    assert.equal(formatRupiah(""), "");
});

/**
 * parseNumber adalah pasangan formatNumber di CurrencyInput. Nilai kosong
 * WAJIB tetap kosong — bukan 0 — supaya aturan "wajib diisi" di server tetap
 * menangkap kolom yang belum diisi.
 */
test("parseNumber membuang pemisah dan menjaga kolom kosong tetap kosong", () => {
    assert.equal(parseNumber("1.500.000"), 1500000);
    assert.equal(parseNumber("Rp 50.000"), 50000);
    assert.equal(parseNumber(""), "");
    assert.equal(parseNumber("abc"), "");
});

/** Bolak-balik format lalu parse harus mengembalikan angka yang sama. */
test("formatNumber dan parseNumber saling membatalkan", () => {
    for (const nilai of [1, 5000, 50000, 100000, 1500000, 999999999]) {
        assert.equal(parseNumber(formatNumber(nilai)), nilai);
    }
});

/**
 * Pembacaan nominal ada justru untuk menangkap salah ketik jumlah nol. Kalau
 * ia sendiri keliru satu satuan, ia berhenti jadi pengaman dan berubah jadi
 * sumber kekeliruan baru — pengguna akan memercayai bacaan yang salah.
 *
 * Kasus nyatanya: sebuah target terisi Rp 125.000.000.000 padahal maksudnya
 * 125 juta, dan barunya ketahuan setelah rencana menabungnya menuntut
 * Rp 2,26 miliar per bulan.
 */
test("spellRupiah menyebut satuan yang benar", () => {
    assert.equal(spellRupiah(125_000_000), "125 juta");
    assert.equal(spellRupiah(125_000_000_000), "125 miliar");
    assert.equal(spellRupiah(1_250_000_000), "1,25 miliar");
    assert.equal(spellRupiah(2_000_000_000), "2 miliar");
    assert.equal(spellRupiah(3_500_000), "3,5 juta");
    assert.equal(spellRupiah(1_000_000_000_000), "1 triliun");
});

/** Batas antar satuan adalah tempat paling mudah meleset sepuluh kali lipat. */
test("spellRupiah tepat di batas satuan", () => {
    assert.equal(spellRupiah(999_999), "");
    assert.equal(spellRupiah(1_000_000), "1 juta");
    assert.equal(spellRupiah(999_999_999), "1000 juta");
    assert.equal(spellRupiah(1_000_000_000), "1 miliar");
});

/** Di bawah satu juta tidak dibacakan — keterangan yang selalu ada berhenti dibaca. */
test("spellRupiah diam untuk nominal kecil dan nilai kosong", () => {
    for (const nilai of [0, 500, 800_000, null, undefined, ""]) {
        assert.equal(spellRupiah(nilai), "");
    }
});