import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { router } from '@inertiajs/core';
import { describe, expect, it, vi } from 'vitest';

import CalculationHistory from './History';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
}));

/** Bentuknya mengikuti CalculationHistoryController::index. */
const baris = (ubah = {}) => ({
    id: 3,
    calculator: 'loan',
    input: { principal: '500000000', annual_interest_rate: '10', months: '240' },
    summary: { monthly_installment: 4_825_108, total_interest: 658_025_920 },
    url: '/kalkulator/pinjaman?principal=500000000&annual_interest_rate=10&months=240',
    calculated_at: '2026-10-02T08:30:00+07:00',
    ...ubah,
});

describe('Riwayat kalkulasi (FR-45)', () => {
    it('menampilkan angka utama, isian, dan tautan buka lagi', () => {
        render(<CalculationHistory histories={[baris()]} limit={50} />);

        expect(screen.getByText('Angsuran Rp 4.825.108 / bulan')).toBeInTheDocument();
        expect(screen.getByText(/Pinjaman Rp 500\.000\.000, tenor 20 tahun, bunga 10%/)).toBeInTheDocument();
        expect(screen.getByRole('link', { name: 'Buka lagi' })).toHaveAttribute('href', baris().url);
    });

    it('menguraikan bunga berjenjang dan dua kalkulator lainnya', () => {
        render(
            <CalculationHistory
                limit={50}
                histories={[
                    baris({ id: 1, input: { principal: '1', months: '12', rate_type: 'tiered', tiers: [{}, {}, {}] } }),
                    baris({
                        id: 2,
                        calculator: 'goal',
                        input: { target_amount: '120000000', months: '24', annual_return_rate: '0' },
                        summary: { monthly_contribution: 5_000_000 },
                    }),
                    baris({
                        id: 4,
                        calculator: 'investment',
                        input: { monthly_contribution: '1000000', months: '12', annual_return_rate: '6' },
                        summary: { final_value: 12_330_000 },
                    }),
                ]}
            />,
        );

        expect(screen.getByText(/bunga berjenjang \(3 jenjang\)/)).toBeInTheDocument();
        expect(screen.getByText('Rp 5.000.000 / bulan')).toBeInTheDocument();
        expect(screen.getByText('Nilai akhir Rp 12.330.000')).toBeInTheDocument();
    });

    it('menghapus satu baris mengirim DELETE ke barisnya', async () => {
        const hapus = vi.spyOn(router, 'delete').mockImplementation(() => {});
        render(<CalculationHistory histories={[baris()]} limit={50} />);

        await userEvent.click(screen.getByRole('button', { name: /^Hapus riwayat Pinjaman/ }));

        expect(hapus).toHaveBeenCalledWith('/calculator.history.destroy/3', expect.anything());
    });

    it('hapus semua menunggu konfirmasi', async () => {
        const hapus = vi.spyOn(router, 'delete').mockImplementation(() => {});
        vi.spyOn(window, 'confirm').mockReturnValueOnce(false).mockReturnValueOnce(true);
        render(<CalculationHistory histories={[baris()]} limit={50} />);

        const tombol = screen.getByRole('button', { name: 'Hapus semua riwayat' });
        await userEvent.click(tombol);
        expect(hapus).not.toHaveBeenCalled();

        await userEvent.click(tombol);
        expect(hapus).toHaveBeenCalledWith('/calculator.history.clear', expect.anything());
    });

    it('riwayat kosong memberi penjelasan, bukan daftar kosong', () => {
        render(<CalculationHistory histories={[]} limit={50} />);

        expect(screen.getByText(/Belum ada hitungan/)).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: 'Hapus semua riwayat' })).toBeNull();
    });
});
