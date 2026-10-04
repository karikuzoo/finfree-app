import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import { tambahBulan } from '@/utils/timezone';
import GoalEdit from './Edit';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
}));

/** Bentuknya mengikuti GoalController::edit. */
const goal = {
    id: 7,
    name: 'Pensiun tenang',
    target_amount: 1_000_000_000,
    initial_amount: 0,
    target_date: '2040-01-01',
    estimated_return_rate: 6,
    estimated_inflation_rate: 0,
};

async function bukaPenentu(jenis) {
    await userEvent.click(screen.getByRole('button', { name: 'Hitung ulang nominalnya' }));
    await userEvent.click(screen.getByRole('button', { name: jenis }));
}

describe('Ubah Tujuan — penentu target (FR-20..22)', () => {
    it('dana pensiun mengisi nominal, tanggal target dari jangka waktu, dan inflasi', async () => {
        const { kiriman } = sadapKiriman('patch');
        render(<GoalEdit goal={goal} currentAmount={0} />);

        await bukaPenentu('Dana pensiun');
        await userEvent.type(screen.getByLabelText('Usia sekarang'), '30');
        await userEvent.type(screen.getByLabelText('Usia pensiun'), '55');
        await userEvent.type(screen.getByLabelText('Perkiraan usia harapan hidup'), '75');
        await userEvent.type(screen.getByLabelText('Pengeluaran bulanan saat pensiun'), '10000000');
        const panel = within(screen.getByRole('region', { name: 'Hitung nominal target' }));
        await userEvent.clear(panel.getByLabelText('Estimasi inflasi (% / tahun)'));
        await userEvent.type(panel.getByLabelText('Estimasi inflasi (% / tahun)'), '4');
        await userEvent.type(screen.getByLabelText('Imbal hasil selama pensiun (% / tahun)'), '4');
        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));

        expect(screen.getByLabelText('Nominal target')).toHaveValue('2.400.000.000');
        // Nama tidak ditimpa di form ubah.
        expect(screen.getByLabelText('Nama tujuan')).toHaveValue('Pensiun tenang');

        await userEvent.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/goals.update/7');
        expect(kiriman[0].data).toMatchObject({
            name: 'Pensiun tenang',
            target_amount: 2_400_000_000,
            target_date: tambahBulan(300),
            estimated_inflation_rate: '4',
        });
    });

    it('dana darurat mengosongkan tanggal target (tanpa tenggat)', async () => {
        const { kiriman } = sadapKiriman('patch');
        render(<GoalEdit goal={goal} currentAmount={0} />);

        await bukaPenentu('Dana darurat');
        await userEvent.type(screen.getByLabelText('Pengeluaran bulanan saat ini'), '5000000');
        await userEvent.click(screen.getByLabelText(/Lajang, tanpa tanggungan/));
        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));
        expect(screen.getByText(/tanggal target dikosongkan \(tanpa tenggat\)/)).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

        expect(kiriman[0].data).toMatchObject({ target_amount: 15_000_000, target_date: '' });
        // Inflasi tidak ditanyakan penentu dana darurat, jadi tidak disentuh.
        expect(kiriman[0].data.estimated_inflation_rate).toBe(0);
    });

    /** Di form ubah, titik berangkat perkiraan waktunya adalah dana yang sudah terkumpul. */
    it('perkiraan waktu dana darurat memakai dana yang sudah terkumpul', async () => {
        render(<GoalEdit goal={goal} currentAmount={9_000_000} />);

        await bukaPenentu('Dana darurat');
        await userEvent.type(screen.getByLabelText('Pengeluaran bulanan saat ini'), '5000000');
        await userEvent.click(screen.getByLabelText(/Lajang, tanpa tanggungan/));
        await userEvent.type(screen.getByLabelText('Sanggup disisihkan per bulan (opsional)'), '2000000');

        // (15 jt − 9 jt) / 2 jt = 3 bulan.
        expect(screen.getByText('3 bulan')).toBeInTheDocument();
    });

    it('menutup panel tanpa memakai hasilnya tidak mengubah apa pun', async () => {
        const { kiriman } = sadapKiriman('patch');
        render(<GoalEdit goal={goal} currentAmount={0} />);

        await bukaPenentu('Dana pendidikan');
        await userEvent.click(screen.getByRole('button', { name: 'Tutup' }));
        await userEvent.click(screen.getByRole('button', { name: 'Simpan Perubahan' }));

        expect(kiriman[0].data).toMatchObject({
            target_amount: goal.target_amount,
            target_date: goal.target_date,
        });
    });
});
