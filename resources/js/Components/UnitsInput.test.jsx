import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import UnitsInput, { KeteranganSatuan } from './UnitsInput';

describe('UnitsInput', () => {
    /**
     * Isiannya menyimpan teksnya sendiri. Kalau diformat ulang dari angkanya
     * setiap ketukan, koma yang baru diketik ("10,") langsung hilang — dan
     * pecahan gram tidak pernah bisa diketik.
     */
    it('pecahan gram bisa diketik dengan koma', async () => {
        const onChange = vi.fn();
        render(<UnitsInput unit="gram" value="" onChange={onChange} />);

        const isian = screen.getByLabelText('Berat emas (gram) (opsional)');
        await userEvent.type(isian, '10,5');

        expect(isian).toHaveValue('10,5');
        expect(onChange).toHaveBeenLastCalledWith(10.5);
    });

    it('dirapikan saat ditinggalkan', async () => {
        render(<UnitsInput unit="unit" value="" onChange={() => {}} />);

        const isian = screen.getByLabelText('Jumlah unit penyertaan (opsional)');
        await userEvent.type(isian, '1234.5678');
        await userEvent.tab();

        expect(isian).toHaveValue('1.234,5678');
    });

    it('nilai tersimpan ditampilkan dengan format Indonesia', () => {
        render(<UnitsInput unit="gram" value={10.5} onChange={() => {}} />);

        expect(screen.getByLabelText('Berat emas (gram) (opsional)')).toHaveValue('10,5');
    });

    it('saham menjelaskan satuan lot', () => {
        render(<UnitsInput unit="lot" value="" onChange={() => {}} />);

        expect(screen.getByText(/1 lot = 100 lembar saham/)).toBeInTheDocument();
    });

    /** Bank dan tunai tidak punya satuan: tidak ada isian sama sekali. */
    it('tanpa satuan tidak menampilkan apa pun', () => {
        const { container } = render(<UnitsInput unit={null} value="" onChange={() => {}} />);

        expect(container).toBeEmptyDOMElement();
    });
});

describe('KeteranganSatuan', () => {
    it('emas: berat dan harga per gram', () => {
        render(<KeteranganSatuan rekening={{ units: 10, unit: 'gram' }} nilai={14_500_000} />);

        expect(screen.getByText(/10 gram/)).toHaveTextContent('10 gram · ≈ Rp 1.450.000/gram');
    });

    it('tanpa jumlah tidak menampilkan apa pun', () => {
        const { container } = render(<KeteranganSatuan rekening={{ units: null, unit: 'gram' }} nilai={1} />);

        expect(container).toBeEmptyDOMElement();
    });
});
