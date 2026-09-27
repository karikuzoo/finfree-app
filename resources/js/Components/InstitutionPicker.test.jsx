import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it, vi } from 'vitest';

import AccountBadge from './AccountBadge';
import InstitutionPicker from './InstitutionPicker';

function Formulir({ awal = '', onChange = () => {} }) {
    const [nilai, setNilai] = useState(awal);

    return (
        <InstitutionPicker
            value={nilai}
            onChange={(v) => {
                setNilai(v);
                onChange(v);
            }}
        />
    );
}

describe('InstitutionPicker', () => {
    it('memilih bank menyimpan NAMA-nya sebagai teks lembaga', async () => {
        const onChange = vi.fn();
        render(<Formulir onChange={onChange} />);

        await userEvent.click(screen.getByRole('radio', { name: /Mandiri/ }));

        expect(onChange).toHaveBeenLastCalledWith('Mandiri');
        expect(screen.getByRole('radio', { name: /Mandiri/ })).toHaveAttribute('aria-checked', 'true');
    });

    /** Isian lama yang ditulis bebas tetap dikenali dan terpilih. */
    it('isian lama "Bank BCA" langsung terpilih sebagai BCA', () => {
        render(<Formulir awal="Bank BCA" />);

        expect(screen.getByRole('radio', { name: /^BCA/ })).toHaveAttribute('aria-checked', 'true');
        expect(screen.queryByRole('textbox')).toBeNull();
    });

    it('bank di luar daftar memakai "Lainnya" dengan isian teks', async () => {
        const onChange = vi.fn();
        render(<Formulir onChange={onChange} />);

        await userEvent.click(screen.getByRole('radio', { name: /Lainnya/ }));
        await userEvent.type(screen.getByRole('textbox'), 'Bank DKI');

        expect(onChange).toHaveBeenLastCalledWith('Bank DKI');
    });

    it('isian lama yang tidak dikenal membuka "Lainnya" dengan isinya', () => {
        render(<Formulir awal="Bank DKI" />);

        expect(screen.getByRole('radio', { name: /Lainnya/ })).toHaveAttribute('aria-checked', 'true');
        expect(screen.getByRole('textbox')).toHaveValue('Bank DKI');
    });

    it('bank dan dompet digital dikelompokkan terpisah', () => {
        render(<Formulir />);

        expect(screen.getByText('Bank')).toBeInTheDocument();
        expect(screen.getByText('Dompet digital')).toBeInTheDocument();
        expect(screen.getByRole('radio', { name: /GoPay/ })).toBeInTheDocument();
    });
});

describe('AccountBadge', () => {
    it('bank yang dikenal tampil dengan singkatan dan warnanya', () => {
        render(<AccountBadge rekening={{ kind: 'bank', institution: 'BCA', name: 'Tabungan' }} />);

        const lencana = screen.getByRole('img', { name: 'BCA' });
        expect(lencana).toHaveTextContent('BCA');
        expect(lencana).toHaveStyle({ backgroundColor: '#005BAA' });
    });

    it('saham dan emas tampil dengan ikon jenisnya', () => {
        render(
            <>
                <AccountBadge rekening={{ kind: 'stock', institution: 'Ajaib', name: 'Saham' }} />
                <AccountBadge rekening={{ kind: 'gold', institution: 'Antam', name: 'Emas' }} />
            </>,
        );

        expect(screen.getByRole('img', { name: 'Saham' })).toBeInTheDocument();
        expect(screen.getByRole('img', { name: 'Emas' })).toBeInTheDocument();
    });

    it('bank tak dikenal tampil dengan ikon bank netral', () => {
        render(<AccountBadge rekening={{ kind: 'bank', institution: 'Bank DKI', name: 'Tabungan' }} />);

        expect(screen.getByRole('img', { name: 'Bank' })).toBeInTheDocument();
    });
});
