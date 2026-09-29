import { render, screen } from '@testing-library/react';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import CalculatorGoal from './Goal';

let penggunaMasuk = null;

vi.mock('@/Layouts/PublicLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@/Components/WhatIfPanel', () => ({ default: () => null }));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
    usePage: () => ({ props: { auth: { user: penggunaMasuk } } }),
}));

/** Bentuknya mengikuti GoalCalculatorController::show — input dari query, sebagai string. */
const input = {
    target_amount: '1000000000',
    current_amount: '50000000',
    months: '120',
    annual_return_rate: '8',
    annual_inflation_rate: '2',
};

const hasil = (ubah = {}) => ({
    monthly_contribution_required: 6_168_232,
    future_value_target: 1_218_994_420,
    future_value_projection: 1_218_994_556,
    total_contribution_projection: 740_187_840,
    total_investment_growth_projection: 428_806_716,
    already_achieved: false,
    ...ubah,
});

beforeEach(() => {
    penggunaMasuk = null;
});

describe('Kalkulator tujuan — Jadikan Tujuan', () => {
    /**
     * Bug yang melahirkan test ini: tombolnya dinonaktifkan sejak Fase 0 dengan
     * tulisan "tersedia di Rilis 1", dan tetap begitu lama setelah form Buat
     * Tujuan ada.
     */
    it('membawa angka hasil hitungan ke form Buat Tujuan', () => {
        render(<CalculatorGoal input={input} result={hasil()} />);

        const tautan = screen.getByRole('link', { name: 'Jadikan Tujuan' });
        const url = new URL(tautan.getAttribute('href'), 'http://arus.test');

        expect(url.pathname).toBe('/goals.create');
        expect(Object.fromEntries(url.searchParams)).toEqual({
            target_amount: '1000000000',
            initial_amount: '50000000',
            months: '120',
            estimated_return_rate: '8',
            estimated_inflation_rate: '2',
        });
        expect(screen.queryByText(/Rilis 1/)).toBeNull();
    });

    it('tamu diberi tahu perlu masuk, dan angkanya ikut terbawa', () => {
        render(<CalculatorGoal input={input} result={hasil()} />);

        expect(screen.getByText(/Masuk dulu/)).toHaveTextContent('angka di atas ikut terbawa');
    });

    it('pengguna yang sudah masuk tinggal memberi nama', () => {
        penggunaMasuk = { name: 'Uji' };
        render(<CalculatorGoal input={input} result={hasil()} />);

        expect(screen.getByText(/tinggal beri nama tujuannya/)).toBeInTheDocument();
    });

    it('target yang sudah tercapai tidak ditawari dijadikan tujuan', () => {
        render(<CalculatorGoal input={input} result={hasil({ already_achieved: true, monthly_contribution_required: 0 })} />);

        expect(screen.queryByRole('link', { name: 'Jadikan Tujuan' })).toBeNull();
    });
});
