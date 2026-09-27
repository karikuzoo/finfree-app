import { render } from '@testing-library/react';
import { cloneElement } from 'react';
import { describe, expect, it, vi } from 'vitest';

import AssetGrowthChart from './AssetGrowthChart';

/**
 * jsdom tidak punya tata letak: ResponsiveContainer selalu mengukur 0×0 dan
 * grafiknya tidak menggambar apa pun. Di sini ia diganti ukuran tetap,
 * sisanya Recharts sungguhan.
 */
vi.mock('recharts', async (asli) => {
    const recharts = await asli();

    return {
        ...recharts,
        ResponsiveContainer: ({ children }) => cloneElement(children, { width: 800, height: 260 }),
    };
});

function harian(nilai, mulai = '2026-09-') {
    return nilai.map((v, i) => ({
        period: `${mulai}${String(i + 1).padStart(2, '0')}`,
        cumulative_amount: v,
    }));
}

const LABEL_X = '.recharts-xAxis-tick-labels .recharts-cartesian-axis-tick-value';

/** Label sumbu X yang tergambar. */
function labelSumbu(container) {
    return [...container.querySelectorAll(LABEL_X)].map((el) => el.textContent);
}

/** Posisi horizontal label sumbu X pertama dan terakhir. */
function ujungSumbu(container) {
    const label = [...container.querySelectorAll(LABEL_X)];

    return [Number(label[0].getAttribute('x')), Number(label.at(-1).getAttribute('x'))];
}

describe('AssetGrowthChart', () => {
    /**
     * Bug yang melahirkan test ini: deret harian memakai kunci `date`
     * sementara grafiknya membaca kunci lain. Labelnya jadi undefined dan
     * grafik harian kosong melompong — tanpa satu pun error.
     */
    it('label harian terbaca, bukan undefined', () => {
        const { container } = render(
            <AssetGrowthChart series={harian([1, 2, 3, 4, 5])} granularity="daily" />,
        );

        const label = labelSumbu(container);

        expect(label.length).toBeGreaterThan(0);
        expect(label).toContain('1 Sep');
        expect(label.join(' ')).not.toMatch(/undefined|NaN/);
    });

    it('label bulanan memakai nama bulan', () => {
        const { container } = render(
            <AssetGrowthChart
                series={[
                    { period: '2026-08', cumulative_amount: 1_000_000 },
                    { period: '2026-09', cumulative_amount: 2_000_000 },
                ]}
                granularity="monthly"
            />,
        );

        expect(labelSumbu(container)).toEqual(['Agu', 'Sep']);
    });

    it('garisnya tergambar', () => {
        const { container } = render(
            <AssetGrowthChart series={harian([1_000_000, 2_000_000, 3_000_000])} granularity="daily" />,
        );

        const garis = container.querySelector('.recharts-area-curve');

        expect(garis).not.toBeNull();
        expect(garis.getAttribute('d')).toMatch(/^M/);
    });

    /**
     * Hari sebelum rekening pertama dimulai bernilai null. Garisnya harus
     * mulai dari hari pertama yang ada nilainya — bukan ditarik dari nol, yang
     * akan tampak sebagai lonjakan kekayaan di hari pertama mencatat.
     */
    it('titik null tidak digambar sebagai nol', () => {
        const { container } = render(
            <AssetGrowthChart
                series={harian([null, null, null, 5_000_000, 6_000_000])}
                granularity="daily"
            />,
        );

        const d = container.querySelector('.recharts-area-curve').getAttribute('d');
        const xAwal = Number(d.match(/^M([\d.]+)/)[1]);
        const [kiri, kanan] = ujungSumbu(container);

        // Lima titik berjarak sama: hari ke-4 ada di 3/4 lebar sumbu. Garis
        // harus berangkat dari situ, bukan dari hari pertama.
        const hariKeempat = kiri + ((kanan - kiri) * 3) / 4;
        expect(xAwal).toBeCloseTo(hariKeempat, 0);
    });

    /** Rekening yang baru dibuat hari ini: satu titik saja, dan tetap kelihatan. */
    it('satu titik bernilai tetap tergambar sebagai titik', () => {
        const { container } = render(
            <AssetGrowthChart series={harian([null, null, 7_000_000])} granularity="daily" />,
        );

        expect(container.querySelectorAll('.recharts-area-dot').length).toBe(1);
    });

    it('deret banyak titik tidak diberi titik per hari', () => {
        const { container } = render(
            <AssetGrowthChart series={harian([1, 2, 3, 4])} granularity="daily" />,
        );

        expect(container.querySelectorAll('.recharts-area-dot').length).toBe(0);
    });

    it('deret kosong tidak menggambar apa pun', () => {
        const { container } = render(<AssetGrowthChart series={[]} granularity="daily" />);

        expect(container).toBeEmptyDOMElement();
    });
});
