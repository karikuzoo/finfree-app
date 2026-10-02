import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import GoalCreate from './Create';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
}));

async function bukaPenentu(jenis) {
    await userEvent.click(screen.getByRole('button', { name: 'Belum tahu nominalnya? Hitung dulu' }));
    await userEvent.click(screen.getByRole('button', { name: jenis }));
}

async function isiPensiun() {
    await bukaPenentu('Dana pensiun');
    await userEvent.type(screen.getByLabelText('Usia sekarang'), '30');
    await userEvent.type(screen.getByLabelText('Usia pensiun'), '55');
    await userEvent.type(screen.getByLabelText('Perkiraan usia harapan hidup'), '75');
    await userEvent.type(screen.getByLabelText('Pengeluaran bulanan saat pensiun'), '10000000');
}

describe('Buat Tujuan — penentu target (FR-20..22)', () => {
    it('hasil dana pensiun mengisi nominal, jangka waktu, dan nama', async () => {
        render(<GoalCreate isFirstGoal />);
        await isiPensiun();

        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));

        expect(screen.getByLabelText('Nominal target')).toHaveValue('2.400.000.000');
        expect(screen.getByLabelText('Jangka waktu (bulan)')).toHaveValue(300);
        expect(screen.getByLabelText('Nama tujuan')).toHaveValue('Dana pensiun');
        // Panelnya tertutup sesudah dipakai.
        expect(screen.queryByRole('region', { name: 'Hitung nominal target' })).toBeNull();
    });

    it('angka dari penentu benar-benar terkirim saat disimpan', async () => {
        const { kiriman } = sadapKiriman();
        render(<GoalCreate isFirstGoal />);
        await isiPensiun();
        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));

        await userEvent.click(screen.getByRole('button', { name: 'Simpan Tujuan' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].data.target_amount).toBe(2_400_000_000);
        expect(kiriman[0].data.target_date).toMatch(/^\d{4}-\d{2}-\d{2}$/);
    });

    it('nama yang sudah diketik tidak ditimpa', async () => {
        render(<GoalCreate isFirstGoal />);
        await userEvent.type(screen.getByLabelText('Nama tujuan'), 'Pensiun di kampung');
        await isiPensiun();

        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));

        expect(screen.getByLabelText('Nama tujuan')).toHaveValue('Pensiun di kampung');
    });

    it('dana darurat memindahkan form ke "Tanpa tenggat" dan tidak mengirim tanggal', async () => {
        const { kiriman } = sadapKiriman();
        render(<GoalCreate isFirstGoal />);
        await bukaPenentu('Dana darurat');
        await userEvent.type(screen.getByLabelText('Pengeluaran bulanan saat ini'), '5000000');
        await userEvent.click(screen.getByLabelText(/Menikah atau punya tanggungan/));

        await userEvent.click(screen.getByRole('button', { name: 'Pakai angka ini' }));

        expect(screen.getByLabelText('Nominal target')).toHaveValue('30.000.000');
        expect(screen.getByRole('button', { name: 'Tanpa tenggat' })).toHaveAttribute('aria-pressed', 'true');

        await userEvent.click(screen.getByRole('button', { name: 'Simpan Tujuan' }));
        expect(kiriman[0].data.target_date).toBe('');
    });

    it('tombol pakai mati sampai isiannya lengkap', async () => {
        render(<GoalCreate isFirstGoal />);
        await bukaPenentu('Dana pendidikan');

        expect(screen.getByRole('button', { name: 'Pakai angka ini' })).toBeDisabled();
    });

    /** Panelnya ada di dalam form tujuan; Enter di sana tidak boleh menyimpan tujuan. */
    it('Enter di isian penentu tidak mengirim form tujuan', async () => {
        const { kiriman } = sadapKiriman();
        render(<GoalCreate isFirstGoal />);
        await isiPensiun();

        await userEvent.type(screen.getByLabelText('Usia sekarang'), '{Enter}');

        expect(kiriman).toHaveLength(0);
    });
});
