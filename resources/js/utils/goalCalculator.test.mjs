/**
 * Menjalankan: npm run test:js
 *
 * Memakai test runner bawaan Node (`node --test`) supaya tidak menambah paket
 * apa pun. Berkas uji yang dipakai sama persis dengan test PHP —
 * docs/fixtures/calculator-cases.json — sesuai syarat di CLAUDE.md §6.6.
 */
import assert from 'node:assert/strict';
import { readFileSync } from 'node:fs';
import { test } from 'node:test';

import {
    calculateMonthlyContribution,
    monthlyRate,
    projectionSeries,
    solveMonths,
} from './goalCalculator.js';

const fixture = JSON.parse(
    readFileSync(
        new URL('../../../docs/fixtures/calculator-cases.json', import.meta.url),
    ),
);

const KEYS = [
    'monthly_contribution_required',
    'future_value_target',
    'future_value_projection',
    'total_contribution_projection',
    'total_investment_growth_projection',
];

for (const kasus of fixture.cases) {
    test(`test vector: ${kasus.name}`, () => {
        const hasil = calculateMonthlyContribution({
            targetAmount: kasus.input.target_amount,
            currentAmount: kasus.input.current_amount,
            months: kasus.input.months,
            annualReturnRate: kasus.input.annual_return_rate,
            annualInflationRate: kasus.input.annual_inflation_rate,
        });

        for (const key of KEYS) {
            assert.equal(
                hasil[key],
                kasus.expected[key],
                `${key} tidak sesuai test vector`,
            );
        }

        assert.ok(
            Math.abs(hasil.monthly_rate - kasus.expected.monthly_rate) < 1e-10,
            'monthly_rate tidak sesuai',
        );
    });
}

test('konversi rate memakai effective annual, bukan dibagi dua belas', () => {
    const i = monthlyRate(12);

    assert.ok(Math.abs(i - 0.0094887929) < 1e-9);
    assert.ok(Math.abs(Math.pow(1 + i, 12) - 1 - 0.12) < 1e-12);
    assert.notEqual(i, 0.01);
});

test('inflasi menaikkan target, tidak memotong imbal hasil', () => {
    const tanpa = calculateMonthlyContribution({
        targetAmount: 500_000_000,
        months: 120,
        annualReturnRate: 8,
    });
    const dengan = calculateMonthlyContribution({
        targetAmount: 500_000_000,
        months: 120,
        annualReturnRate: 8,
        annualInflationRate: 3.5,
    });

    assert.equal(tanpa.future_value_target, 500_000_000);
    assert.ok(dengan.future_value_target > tanpa.future_value_target);
    assert.equal(dengan.monthly_rate, tanpa.monthly_rate);
});

test('solveMonths adalah kebalikan dari perhitungan setoran', () => {
    const pmt = calculateMonthlyContribution({
        targetAmount: 500_000_000,
        months: 120,
        annualReturnRate: 8,
    }).monthly_contribution_required;

    const bulan = solveMonths({
        targetAmount: 500_000_000,
        monthlyContribution: pmt,
        annualReturnRate: 8,
    });

    // Setoran dibulatkan ke atas, jadi targetnya tercapai tepat atau sedikit
    // lebih cepat — tidak pernah lebih lambat.
    assert.ok(bulan <= 120 && bulan >= 119, `dapat ${bulan} bulan`);
});

test('solveMonths mengembalikan null bila target mustahil tercapai', () => {
    const bulan = solveMonths({
        targetAmount: 10_000_000_000,
        monthlyContribution: 50_000,
        annualReturnRate: 1,
        annualInflationRate: 15,
    });

    assert.equal(bulan, null);
});

test('deret proyeksi berakhir tepat pada bulan terakhir', () => {
    const deret = projectionSeries({
        months: 120,
        monthlyContribution: 2_775_862,
        annualReturnRate: 8,
    });

    assert.equal(deret[0].month, 0);
    assert.equal(deret[deret.length - 1].month, 120);
    assert.ok(deret.length <= 62, `titik terlalu banyak: ${deret.length}`);
    // Saldo selalu >= total setoran, karena imbal hasilnya tidak negatif.
    for (const titik of deret) {
        assert.ok(titik.balance >= titik.contributed - 1);
    }
});
