import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import TawaranRekalkulasi from './TawaranRekalkulasi';

/** Bentuknya mengikuti GoalRecalculationService::optionsFor. */
function tawaran(ubahOpsi = {}) {
    return {
        required_monthly_contribution: 4_583_334,
        planned_monthly_contribution: 3_333_334,
        options: {
            contribution: { monthly_contribution: 4_583_334 },
            date: { target_date: '2029-10-01', months_added: 9 },
            target: { target_amount: 90_000_000 },
            ...ubahOpsi,
        },
    };
}

describe('TawaranRekalkulasi', () => {
    it('tidak menampilkan apa pun untuk tujuan yang tidak ditawari', () => {
        const { container } = render(<TawaranRekalkulasi goalId={3} tawaran={null} />);

        expect(container).toBeEmptyDOMElement();
    });

    it('tertutup secara bawaan, lalu membuka tiga pilihan', async () => {
        render(<TawaranRekalkulasi goalId={3} tawaran={tawaran()} />);

        expect(screen.getByText('Rp 4.583.334')).toBeInTheDocument();
        expect(screen.queryByRole('button', { name: /Pakai|Naikkan/ })).not.toBeInTheDocument();

        await userEvent.click(screen.getByRole('button', { name: 'Hitung ulang rencana' }));

        expect(screen.getByRole('button', { name: 'Naikkan setoran: Rp 4.583.334 / bulan' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Mundurkan tanggal target: 1 Oktober 2029' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Turunkan nominal target: Rp 90.000.000' })).toBeInTheDocument();
        expect(screen.getByText(/9 bulan lebih lama/)).toBeInTheDocument();
    });

    /**
     * Pilihan yang tidak bisa dihitung server (tanggal yang tak pernah
     * tercapai, target yang akan turun di bawah dana terkumpul) datang
     * sebagai null dan tidak boleh muncul sebagai baris kosong.
     */
    it('menyembunyikan pilihan yang tidak tersedia', async () => {
        render(<TawaranRekalkulasi goalId={3} tawaran={tawaran({ date: null, target: null })} />);

        await userEvent.click(screen.getByRole('button', { name: 'Hitung ulang rencana' }));

        expect(screen.getAllByRole('button', { name: /:/ })).toHaveLength(1);
    });

    /** Yang dikirim hanya nama pilihannya — angkanya dihitung ulang server. */
    it('memilih mengirim nama pilihannya saja', async () => {
        const { kiriman } = sadapKiriman();
        render(<TawaranRekalkulasi goalId={3} tawaran={tawaran()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Hitung ulang rencana' }));
        await userEvent.click(screen.getByRole('button', { name: /^Mundurkan tanggal target/ }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/goals.recalculate/3');
        expect(kiriman[0].data).toEqual({ option: 'date' });
    });
});
