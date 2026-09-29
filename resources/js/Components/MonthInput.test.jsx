import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import MonthInput from './MonthInput';

const panel = () => screen.getByRole('dialog', { name: 'Pilih bulan' });
const buka = () => userEvent.click(screen.getByRole('button', { name: /^Pilih bulan:/ }));

describe('MonthInput', () => {
    beforeEach(() => {
        // "Bulan ini" dibekukan: September 2026.
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-09-29T05:00:00Z'));
    });

    afterEach(() => vi.useRealTimers());

    /**
     * Pengganti <input type="month"> bawaan, yang panelnya mengikuti bahasa
     * browser ("Jan… Clear, This month") dan warnanya di luar tema.
     */
    it('menampilkan bulan dalam Bahasa Indonesia', async () => {
        render(<MonthInput value="2026-09" onChange={() => {}} />);

        expect(screen.getByRole('button', { name: 'Pilih bulan: September 2026' })).toHaveTextContent('September 2026');

        await buka();

        expect(within(panel()).getByRole('button', { name: 'Agustus 2026' })).toHaveTextContent('Agu');
        expect(within(panel()).getByRole('button', { name: 'Bulan ini' })).toBeInTheDocument();
        expect(within(panel()).queryByText(/Clear|This month/)).toBeNull();
    });

    it('memilih bulan mengirim "YYYY-MM" dan menutup panel', async () => {
        const onChange = vi.fn();
        render(<MonthInput value="2026-09" onChange={onChange} />);

        await buka();
        await userEvent.click(within(panel()).getByRole('button', { name: 'Maret 2026' }));

        expect(onChange).toHaveBeenCalledWith('2026-03');
        expect(screen.queryByRole('dialog')).toBeNull();
    });

    it('panah berganti tahun', async () => {
        const onChange = vi.fn();
        render(<MonthInput value="2026-09" onChange={onChange} />);

        await buka();
        await userEvent.click(within(panel()).getByRole('button', { name: 'Tahun sebelumnya' }));
        await userEvent.click(within(panel()).getByRole('button', { name: 'Desember 2025' }));

        expect(onChange).toHaveBeenCalledWith('2025-12');
    });

    /** Arus kas bulan depan belum ada — tidak bisa dipilih, bukan kosong setelah dipilih. */
    it('bulan setelah batas tidak bisa dipilih', async () => {
        render(<MonthInput value="2026-09" onChange={() => {}} max="2026-09" />);

        await buka();

        expect(within(panel()).getByRole('button', { name: 'Oktober 2026' })).toBeDisabled();
        expect(within(panel()).getByRole('button', { name: 'September 2026' })).toBeEnabled();
        expect(within(panel()).getByRole('button', { name: 'Tahun berikutnya' })).toBeDisabled();
    });

    it('"Bulan ini" kembali ke bulan berjalan', async () => {
        const onChange = vi.fn();
        render(<MonthInput value="2025-01" onChange={onChange} />);

        await buka();
        await userEvent.click(within(panel()).getByRole('button', { name: 'Bulan ini' }));

        expect(onChange).toHaveBeenCalledWith('2026-09');
    });

    it('terpilih mint penuh, bulan ini mint lembut', async () => {
        render(<MonthInput value="2026-03" onChange={() => {}} />);

        await buka();

        expect(within(panel()).getByRole('button', { name: 'Maret 2026' })).toHaveClass('bg-lime-500');
        expect(within(panel()).getByRole('button', { name: 'September 2026' })).toHaveClass('bg-lime-softBg');
    });

    it('Escape menutup panel', async () => {
        render(<MonthInput value="2026-09" onChange={() => {}} />);

        await buka();
        await userEvent.keyboard('{Escape}');

        expect(screen.queryByRole('dialog')).toBeNull();
    });
});
