import assert from "node:assert/strict";
import test from "node:test";

import { inflatedTarget } from "./goalCalculator.js";
import { KELIPATAN_DARURAT, TARGET_PLANNERS } from "./targetPlanners.js";

const penentu = (key) => TARGET_PLANNERS.find((p) => p.key === key);
const hitung = (key, ubah = {}, konteks = {}) => {
    const p = penentu(key);

    return p.compute({ ...p.initial, ...ubah }, konteks);
};

const pensiunLengkap = {
    usia: "30",
    usia_pensiun: "55",
    usia_harapan: "75",
    pengeluaran: 10_000_000,
};

// ── Shell ────────────────────────────────────────────────────────────────

test("setiap penentu punya isian awal untuk setiap kolomnya", () => {
    for (const p of TARGET_PLANNERS) {
        for (const f of p.fields) {
            assert.ok(f.key in p.initial, `${p.key}.${f.key} tidak punya isian awal`);
        }
    }
});

test("isian kosong tidak menghasilkan apa pun, bukan galat", () => {
    for (const p of TARGET_PLANNERS) {
        assert.deepEqual(p.compute(p.initial), { status: "belum" }, p.key);
    }
});

/**
 * Bawaan nol, bukan angka "wajar" (D-7): imbal hasil, inflasi, dan usia
 * harapan hidup tidak boleh disodorkan lebih dulu oleh Arus.
 */
test("tidak ada angka bawaan selain nol", () => {
    for (const p of TARGET_PLANNERS) {
        for (const [kolom, v] of Object.entries(p.initial)) {
            assert.ok(v === "" || v === "0", `${p.key}.${kolom} berisi bawaan ${v}`);
        }
    }
});

// ── Pensiun ──────────────────────────────────────────────────────────────

test("pensiun tanpa imbal hasil dan inflasi = pengeluaran × bulan pensiun", () => {
    const h = hitung("pensiun", pensiunLengkap);

    assert.equal(h.status, "siap");
    assert.equal(h.isian.target_amount, 10_000_000 * 20 * 12);
    assert.equal(h.isian.months, 25 * 12);
    assert.equal(h.isian.mode, "waktu");
    assert.equal(h.isian.estimated_inflation_rate, 0);
});

/**
 * Imbal hasil yang hanya menyamai inflasi tidak menambah daya beli, jadi
 * kebutuhannya dalam nilai hari ini sama dengan tanpa keduanya. Test ini gagal
 * bila suatu saat inflasi ikut dilipat ke nominal (dihitung dua kali, D-1).
 */
test("pensiun dengan imbal hasil sama dengan inflasi = tanpa keduanya", () => {
    const h = hitung("pensiun", { ...pensiunLengkap, inflasi: "4", imbal_pensiun: "4" });

    assert.equal(h.isian.target_amount, 10_000_000 * 20 * 12);
    assert.equal(h.isian.estimated_inflation_rate, 4);
});

/**
 * Dihitung manual: imbal hasil riil 1% per bulan = (1,01^12 − 1) per tahun.
 * 12 penarikan Rp 1 jt di awal bulan:
 * 1.000.000 × (1 − 1,01^−12) / 0,01 × 1,01 = 11.367.628,4 → dibulatkan ke atas.
 */
test("pensiun dengan imbal hasil riil cocok dengan hitungan manual", () => {
    const h = hitung("pensiun", {
        usia: "54",
        usia_pensiun: "55",
        usia_harapan: "56",
        pengeluaran: 1_000_000,
        imbal_pensiun: String((Math.pow(1.01, 12) - 1) * 100),
    });

    assert.equal(h.isian.target_amount, 11_367_629);
});

test("imbal hasil di bawah inflasi membuat kebutuhan lebih besar", () => {
    const dasar = hitung("pensiun", pensiunLengkap).isian.target_amount;
    const tergerus = hitung("pensiun", { ...pensiunLengkap, inflasi: "5", imbal_pensiun: "2" });

    assert.ok(tergerus.isian.target_amount > dasar);
});

test("nominal saat pensiun tiba sama dengan yang dihitung form", () => {
    const h = hitung("pensiun", { ...pensiunLengkap, inflasi: "3" });
    const setara = h.rincian.find((r) => r.istilah === "Setara saat pensiun tiba").nilai;

    assert.equal(setara, inflatedTarget(h.isian.target_amount, h.isian.months, 3));
});

test("urutan usia yang mustahil ditolak per kolom", () => {
    assert.ok(hitung("pensiun", { ...pensiunLengkap, usia_pensiun: "30" }).errors.usia_pensiun);
    assert.ok(hitung("pensiun", { ...pensiunLengkap, usia_harapan: "55" }).errors.usia_harapan);
    assert.ok(hitung("pensiun", { ...pensiunLengkap, usia: "30.5" }).errors.usia);
});

test("jarak ke pensiun tidak boleh melewati batas form tujuan", () => {
    const h = hitung("pensiun", { ...pensiunLengkap, usia: "5", usia_pensiun: "65", usia_harapan: "80" });

    assert.equal(h.status, "galat");
    assert.ok(h.errors.usia_pensiun);
    assert.equal(
        hitung("pensiun", { ...pensiunLengkap, usia: "6", usia_pensiun: "65", usia_harapan: "80" }).isian.months,
        59 * 12,
    );
});

// ── Darurat ──────────────────────────────────────────────────────────────

test("dana darurat memakai kelipatan 3, 6, dan 12", () => {
    assert.deepEqual(KELIPATAN_DARURAT, { lajang: 3, menikah: 6, tidak_tetap: 12 });

    for (const [keadaan, kali] of Object.entries(KELIPATAN_DARURAT)) {
        const h = hitung("darurat", { pengeluaran: 5_000_000, keadaan });
        assert.equal(h.isian.target_amount, 5_000_000 * kali, keadaan);
    }
});

test("dana darurat tanpa tenggat dan tidak menyentuh inflasi form", () => {
    const h = hitung("darurat", { pengeluaran: 5_000_000, keadaan: "lajang" });

    assert.equal(h.isian.mode, "tanpa");
    assert.equal(h.isian.months, null);
    assert.ok(!("estimated_inflation_rate" in h.isian));
});

test("perkiraan waktu dana darurat memperhitungkan dana awal", () => {
    // Target 15 jt, sudah ada 3 jt, sanggup 2 jt/bulan → 6 bulan.
    const h = hitung(
        "darurat",
        { pengeluaran: 5_000_000, keadaan: "lajang", sanggup: 2_000_000 },
        { initial_amount: 3_000_000 },
    );

    assert.equal(h.rincian.find((r) => r.jenis === "durasi").nilai, 6);
});

test("perkiraan waktu dibulatkan ke atas", () => {
    const h = hitung("darurat", { pengeluaran: 5_000_000, keadaan: "lajang", sanggup: 4_000_000 });

    assert.equal(h.rincian.find((r) => r.jenis === "durasi").nilai, 4);
});

// ── Pendidikan ───────────────────────────────────────────────────────────

test("dana pendidikan mengisi biaya hari ini dan inflasi pendidikannya", () => {
    const h = hitung("pendidikan", { jenjang: "kuliah", biaya: 80_000_000, tahun: "10", inflasi: "8" });

    assert.equal(h.isian.target_amount, 80_000_000);
    assert.equal(h.isian.months, 120);
    assert.equal(h.isian.estimated_inflation_rate, 8);
    assert.equal(h.nama, "Dana pendidikan Kuliah");
});

test("perkiraan biaya saat masuk sama dengan yang dihitung form", () => {
    const h = hitung("pendidikan", { jenjang: "sd", biaya: 25_000_000, tahun: "4", inflasi: "10" });
    const saatMasuk = h.rincian.find((r) => r.istilah === "Perkiraan biaya saat masuk").nilai;

    assert.equal(saatMasuk, inflatedTarget(25_000_000, 48, 10));
});

test("tahun masuk di luar batas ditolak", () => {
    assert.ok(hitung("pendidikan", { jenjang: "sd", biaya: 1, tahun: "0" }).errors.tahun);
    assert.ok(hitung("pendidikan", { jenjang: "sd", biaya: 1, tahun: "60" }).errors.tahun);
});
