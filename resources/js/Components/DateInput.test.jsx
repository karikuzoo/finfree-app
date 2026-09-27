import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import DateInput from './DateInput';

/** Pembungkus berstatus, seperti pemakaiannya di formulir. */
function Formulir({ awal = '', onChange = () => {}, ...props }) {
    const [nilai, setNilai] = useState(awal);

    return (
        <DateInput
            id="tgl"
            value={nilai}
            onChange={(v) => {
                setNilai(v);
                onChange(v);
            }}
            {...props}
        />
    );
}

const panel = () => screen.getByRole('dialog', { name: 'Pilih tanggal' });
const bulan = () => within(panel()).getByRole('combobox', { name: 'Bulan' });
const tahun = () => within(panel()).getByRole('combobox', { name: 'Tahun' });
const tanggal = (n) => within(panel()).getByRole('button', { name: String(n), exact: true });

describe('DateInput', () => {
    beforeEach(() => {
        // "Hari ini" dibekukan: 27 September 2026, 10.00 WIB.
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-09-27T03:00:00Z'));
    });

    afterEach(() => {
        vi.useRealTimers();
    });

    /**
     * Bug yang melahirkan berkas ini: tanggal lahir dibatasi usia minimal 17
     * tahun, tetapi panel kosong selalu dibuka di bulan berjalan — kalender
     * tanpa satu pun tanggal yang bisa diklik. Tanggal lahir praktis tidak
     * bisa diisi.
     */
    it('tanpa nilai, panel dibuka di bulan terakhir yang masih boleh dipilih', async () => {
        render(<Formulir min="1900-01-02" max="2009-09-27" placeholder="Pilih tanggal lahir" />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal lahir' }));

        expect(bulan()).toHaveValue('8'); // September (0-indexed)
        expect(tahun()).toHaveValue('2009');
        expect(tanggal(27)).toBeEnabled();
        expect(tanggal(28)).toBeDisabled();
    });

    it('tanpa batas, panel dibuka di bulan ini', async () => {
        render(<Formulir />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal' }));

        expect(bulan()).toHaveValue('8');
        expect(tahun()).toHaveValue('2026');
    });

    /** Tanpa pilihan tahun, sampai ke 1995 butuh ±370 klik panah. */
    it('tahun dan bulan bisa dipilih langsung', async () => {
        const onChange = vi.fn();
        render(<Formulir min="1900-01-02" max="2009-09-27" placeholder="Pilih tanggal lahir" onChange={onChange} />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal lahir' }));
        await userEvent.selectOptions(tahun(), '1995');
        await userEvent.selectOptions(bulan(), '4'); // Mei
        await userEvent.click(tanggal(12));

        expect(onChange).toHaveBeenCalledWith('1995-05-12');
        expect(screen.getByRole('button', { name: '12 Mei 1995' })).toBeInTheDocument();
    });

    it('pilihan tahun dibatasi rentang min–max', async () => {
        render(<Formulir min="1900-01-02" max="2009-09-27" placeholder="Pilih tanggal lahir" />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal lahir' }));

        const pilihan = within(tahun()).getAllByRole('option').map((o) => o.value);
        expect(pilihan[0]).toBe('2009');
        expect(pilihan.at(-1)).toBe('1900');
        expect(pilihan).not.toContain('2010');
    });

    it('panah tidak membawa melewati batas', async () => {
        render(<Formulir min="1900-01-02" max="2009-09-27" placeholder="Pilih tanggal lahir" />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal lahir' }));

        expect(within(panel()).getByRole('button', { name: 'Bulan berikutnya' })).toBeDisabled();
        expect(within(panel()).getByRole('button', { name: 'Bulan sebelumnya' })).toBeEnabled();
    });

    it('dengan nilai, panel dibuka di bulan nilainya', async () => {
        render(<Formulir awal="1995-05-12" min="1900-01-02" max="2009-09-27" />);

        await userEvent.click(screen.getByRole('button', { name: '12 Mei 1995' }));

        expect(bulan()).toHaveValue('4');
        expect(tahun()).toHaveValue('1995');
        expect(tanggal(12)).toHaveAttribute('aria-current', 'true');
    });

    it('"Hari ini" nonaktif bila hari ini di luar rentang', async () => {
        render(<Formulir max="2009-09-27" placeholder="Pilih tanggal lahir" />);

        await userEvent.click(screen.getByRole('button', { name: 'Pilih tanggal lahir' }));

        expect(within(panel()).getByRole('button', { name: 'Hari ini' })).toBeDisabled();
    });

    it('"Kosongkan" menghapus nilai', async () => {
        const onChange = vi.fn();
        render(<Formulir awal="1995-05-12" onChange={onChange} />);

        await userEvent.click(screen.getByRole('button', { name: '12 Mei 1995' }));
        await userEvent.click(within(panel()).getByRole('button', { name: 'Kosongkan' }));

        expect(onChange).toHaveBeenCalledWith('');
    });
});
