import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import CalculatorLoan from './Loan';

vi.mock('@/Layouts/PublicLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@/Components/LoanChart', () => ({ default: () => <div data-testid="grafik-pinjaman" /> }));
vi.mock('@inertiajs/react', async (asli) => ({ ...(await asli()), Head: () => null }));

/** Bentuknya mengikuti GoalCalculatorService::calculateLoan. */
const hasil = (ubah = {}) => ({
    monthly_installment: 4_825_109,
    last_installment: 4_825_109,
    principal: 500_000_000,
    total_payment: 1_158_026_160,
    total_interest: 658_026_160,
    monthly_rate: 0.1 / 12,
    months: 240,
    yearly: [
        { year: 1, principal_paid: 8_214_000, interest_paid: 49_687_308, balance: 491_786_000 },
        { year: 2, principal_paid: 9_074_000, interest_paid: 48_827_308, balance: 482_712_000 },
    ],
    series: [{ month: 0, balance: 500_000_000, cumulative_interest: 0 }],
    ...ubah,
});

const input = { principal: '500000000', annual_interest_rate: '10', months: '240' };

describe('Kalkulator Pinjaman / KPR', () => {
    it('sebelum dihitung, panel hasil memberi petunjuk, bukan angka nol', () => {
        render(<CalculatorLoan input={null} result={null} />);

        expect(screen.getByText(/Isi pokok pinjaman, suku bunga, dan tenor/)).toBeInTheDocument();
        expect(screen.queryByText('Angsuran bulanan')).toBeNull();
    });

    it('menampilkan angsuran, total bunga, dan grafik', () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.getByText('Rp 4.825.109')).toBeInTheDocument();
        expect(screen.getByText('Rp 658.026.160')).toBeInTheDocument();
        expect(screen.getByTestId('grafik-pinjaman')).toBeInTheDocument();
        // Konvensinya disebut terbuka — itu yang menjelaskan kenapa cocok
        // dengan simulasi bank.
        expect(screen.getByText(/dibagi dua belas/)).toBeInTheDocument();
    });

    it('angsuran terakhir yang berbeda disebutkan beserta alasannya', () => {
        render(<CalculatorLoan input={input} result={hasil({ last_installment: 4_824_950 })} />);

        expect(screen.getByText(/Angsuran terakhir Rp 4\.824\.950/)).toHaveTextContent('lunas tepat nol');
    });

    it('pinjaman tidak ditawari dijadikan tujuan', () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.queryByRole('link', { name: 'Jadikan Tujuan' })).toBeNull();
    });

    it('rincian per tahun tertutup dulu, lalu bisa dibuka', async () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.queryByRole('table')).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Tampilkan' }));

        expect(screen.getAllByRole('row')).toHaveLength(3);
        expect(screen.getByText('Rp 491.786.000')).toBeInTheDocument();
    });

    /** Bug "tombol diam" (Goal.jsx): pastikan tombolnya benar-benar mengirim. */
    it('Hitung Sekarang mengirim isian ke route kalkulator pinjaman', async () => {
        const { kiriman } = sadapKiriman('get');
        render(<CalculatorLoan input={input} result={null} />);

        await userEvent.click(screen.getByRole('button', { name: 'Hitung Sekarang' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/calculator.loan');
    });
});
