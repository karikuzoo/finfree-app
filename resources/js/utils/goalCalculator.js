/**
 * Salinan rumus kalkulator tujuan untuk pratinjau langsung di browser (FR-8).
 *
 * **Backend tetap sumber kebenaran.** Berkas ini hanya dipakai agar angka
 * bergerak seketika saat slider digeser; nilai yang disimpan selalu hasil dari
 * `App\Services\GoalCalculatorService`.
 *
 * Keduanya WAJIB lolos berkas uji yang sama, `docs/fixtures/calculator-cases.json`
 * (CLAUDE.md §6.6). Kalau dua implementasi bisa berbeda diam-diam, cepat atau
 * lambat mereka akan berbeda — dan pengguna akan melihat satu angka di slider
 * lalu angka lain setelah menekan tombol.
 *
 * Aturan yang dikunci: D-1 inflasi menaikkan target (bukan memotong imbal
 * hasil), D-2 setoran di akhir bulan, konversi rate majemuk bukan dibagi dua
 * belas, setoran dibulatkan ke atas.
 */

/** Rate tahunan efektif -> rate bulanan. Sengaja bukan r/12. */
export function monthlyRate(annualRatePercent) {
    const r = Number(annualRatePercent);
    if (!r) return 0;

    return Math.pow(1 + r / 100, 1 / 12) - 1;
}

/** D-1: target dinaikkan ke nilai masa depan menurut inflasi. */
export function inflatedTarget(targetAmount, months, annualInflationRate) {
    const infl = Number(annualInflationRate);
    if (!infl) return Math.round(Number(targetAmount));

    return Math.round(
        Number(targetAmount) * Math.pow(1 + infl / 100, Number(months) / 12),
    );
}

/**
 * Setoran bulanan yang dibutuhkan. Mengembalikan bentuk yang sama dengan
 * GoalCalculatorService di backend.
 */
export function calculateMonthlyContribution({
    targetAmount,
    currentAmount = 0,
    months,
    annualReturnRate,
    annualInflationRate = 0,
}) {
    const n = Number(months);
    const pv = Number(currentAmount) || 0;

    if (!(n >= 1) || !(Number(targetAmount) > 0)) return null;

    const i = monthlyRate(annualReturnRate);
    const fvTarget = inflatedTarget(targetAmount, n, annualInflationRate);
    const growth = i === 0 ? 1 : Math.pow(1 + i, n);
    const grownCurrent = pv * growth;

    let pmt;
    if (grownCurrent >= fvTarget) {
        pmt = 0;
    } else if (i === 0) {
        pmt = Math.ceil((fvTarget - pv) / n);
    } else {
        pmt = Math.ceil(((fvTarget - grownCurrent) * i) / (growth - 1));
    }

    const fvProjection = Math.round(
        i === 0 ? pv + pmt * n : grownCurrent + (pmt * (growth - 1)) / i,
    );
    const totalContribution = pmt * n;

    return {
        monthly_contribution_required: pmt,
        future_value_target: fvTarget,
        future_value_projection: fvProjection,
        total_contribution_projection: totalContribution,
        total_investment_growth_projection:
            fvProjection - Math.round(pv) - totalContribution,
        monthly_rate: i,
        months: n,
        already_achieved: pmt === 0,
    };
}

/**
 * Arah sebaliknya: berapa lama target tercapai bila setoran bulanannya sekian.
 *
 * Diselesaikan dengan pencarian biner, bukan rumus tertutup. Alasannya: dengan
 * inflasi aktif, nominal target ikut tumbuh seiring bertambahnya bulan
 * (`FV = target × (1+inflasi)^(n/12)`), sehingga `n` muncul di kedua sisi
 * persamaan dan tidak bisa dipisahkan secara aljabar.
 *
 * Mengembalikan jumlah bulan, atau null bila target tidak tercapai dalam batas
 * maksimum — yang memang mungkin terjadi bila inflasi mengejar lebih cepat
 * daripada setoran ditambah imbal hasil.
 */
export function solveMonths({
    targetAmount,
    currentAmount = 0,
    monthlyContribution,
    annualReturnRate,
    annualInflationRate = 0,
    maxMonths = 720,
}) {
    const pmt = Number(monthlyContribution);
    if (!(pmt > 0)) return null;

    const i = monthlyRate(annualReturnRate);
    const pv = Number(currentAmount) || 0;

    // Saldo yang terkumpul setelah n bulan, dikurangi target pada bulan itu.
    // Fungsi ini menaik terhadap n selama setoran mengalahkan inflasi.
    const gap = (n) => {
        const growth = i === 0 ? 1 : Math.pow(1 + i, n);
        const balance =
            i === 0 ? pv + pmt * n : pv * growth + (pmt * (growth - 1)) / i;

        return balance - inflatedTarget(targetAmount, n, annualInflationRate);
    };

    if (gap(1) >= 0) return 1;
    if (gap(maxMonths) < 0) return null;

    let low = 1;
    let high = maxMonths;

    while (high - low > 1) {
        const mid = Math.floor((low + high) / 2);
        if (gap(mid) >= 0) high = mid;
        else low = mid;
    }

    return high;
}

/**
 * Deret saldo per bulan untuk grafik proyeksi.
 *
 * `step` menjarangkan titiknya supaya grafik 30 tahun tidak menggambar 360
 * titik yang tidak terbaca — dan bulan terakhir selalu disertakan agar
 * ujungnya tepat di target.
 */
export function projectionSeries({
    currentAmount = 0,
    months,
    monthlyContribution,
    annualReturnRate,
    maxPoints = 60,
}) {
    const n = Number(months);
    const pmt = Number(monthlyContribution) || 0;
    const pv = Number(currentAmount) || 0;
    const i = monthlyRate(annualReturnRate);

    if (!(n >= 1)) return [];

    const step = Math.max(1, Math.ceil(n / maxPoints));
    const points = [];

    const at = (m) => {
        const growth = i === 0 ? 1 : Math.pow(1 + i, m);
        const balance =
            i === 0 ? pv + pmt * m : pv * growth + (pmt * (growth - 1)) / i;
        const contributed = pv + pmt * m;

        return {
            month: m,
            balance: Math.round(balance),
            contributed: Math.round(contributed),
            // Bagian yang datang dari imbal hasil, bukan dari kantong pengguna.
            growth: Math.round(balance - contributed),
        };
    };

    for (let m = 0; m <= n; m += step) points.push(at(m));
    if (points[points.length - 1].month !== n) points.push(at(n));

    return points;
}
