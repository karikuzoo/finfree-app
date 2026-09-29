import { render, screen } from '@testing-library/react';
import { describe, expect, it, vi } from 'vitest';

import CalculatorInvestment from './Investment';

vi.mock('@/Layouts/PublicLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@/Components/ProjectionChart', () => ({
    default: ({ data }) => <div data-testid="grafik" data-titik={data.length} data-akhir={data.at(-1)?.balance} />,
}));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: null } } }),
}));

/** Input dari query (string), hasil dari GoalCalculatorService::projectInvestment. */
const input = (ubah = {}) => ({
    initial_amount: '10000000',
    monthly_contribution: '1000000',
    months: '24',
    annual_return_rate: '0',
    ...ubah,
});

const hasil = {
    final_value: 34_000_000,
    initial_amount: 10_000_000,
    total_contribution: 24_000_000,
    investment_growth: 0,
    monthly_rate: 0,
    months: 24,
};

describe('Kalkulator Investasi', () => {
    it('menampilkan nilai akhir beserta bagian-bagiannya', () => {
        render(<CalculatorInvestment input={input()} result={hasil} />);

        expect(screen.getByText('Nilai akhir setelah 2 tahun')).toBeInTheDocument();
        expect(screen.getByText('Rp 34.000.000')).toBeInTheDocument();
        expect(screen.getByText('Rp 24.000.000')).toBeInTheDocument();
    });

    /** Grafik dan angka hasil harus sampai di ujung yang sama. */
    it('grafik berakhir tepat di nilai akhir', () => {
        render(<CalculatorInvestment input={input()} result={hasil} />);

        expect(screen.getByTestId('grafik')).toHaveAttribute('data-akhir', '34000000');
    });

    it('nilai akhir bisa dijadikan target tujuan', () => {
        render(<CalculatorInvestment input={input({ annual_return_rate: '7.5' })} result={hasil} />);

        const url = new URL(screen.getByRole('link', { name: 'Jadikan Tujuan' }).getAttribute('href'), 'http://arus.test');
        expect(url.pathname).toBe('/goals.create');
        expect(Object.fromEntries(url.searchParams)).toEqual({
            target_amount: '34000000',
            initial_amount: '10000000',
            months: '24',
            estimated_return_rate: '7.5',
            estimated_inflation_rate: '0',
        });
    });

    it('tanpa setoran bulanan tidak ada yang dikejar, jadi tidak ditawari', () => {
        render(
            <CalculatorInvestment
                input={input({ monthly_contribution: '0' })}
                result={{ ...hasil, total_contribution: 0, final_value: 10_000_000 }}
            />,
        );

        expect(screen.queryByRole('link', { name: 'Jadikan Tujuan' })).toBeNull();
    });
});
