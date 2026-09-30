import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { useState } from 'react';
import { describe, expect, it } from 'vitest';

import TierInput from './TierInput';

/** Pembungkus berstate, supaya perubahan benar-benar tampil ulang. */
function Uji({ awal, months = '240', errors }) {
    const [tiers, setTiers] = useState(awal);

    return (
        <>
            <TierInput tiers={tiers} onChange={setTiers} months={months} errors={errors} />
            <output data-testid="data">{JSON.stringify(tiers)}</output>
        </>
    );
}

const data = () => JSON.parse(screen.getByTestId('data').textContent);

describe('TierInput', () => {
    it('tahun mulai tiap jenjang mengikuti batas jenjang sebelumnya', async () => {
        render(<Uji awal={[{ until_year: '1', rate: '3.75' }, { until_year: '', rate: '6.75' }]} />);

        expect(screen.getByText('Tahun 1')).toBeInTheDocument();
        expect(screen.getByText('Tahun 2')).toBeInTheDocument();
        // Baris terakhir berlaku sampai akhir tenor 240 bulan.
        expect(screen.getByText('sampai tahun 20')).toBeInTheDocument();

        await userEvent.clear(screen.getByLabelText('Jenjang 1: sampai tahun ke'));
        await userEvent.type(screen.getByLabelText('Jenjang 1: sampai tahun ke'), '3');

        expect(screen.getByText('Tahun 4')).toBeInTheDocument();
    });

    /**
     * Jenjang terakhir biasanya bunga mengambang yang berlaku sampai tenor
     * habis — menambah jenjang tidak boleh menggesernya ke tengah.
     */
    it('jenjang baru disisipkan sebelum jenjang terakhir', async () => {
        render(<Uji awal={[{ until_year: '1', rate: '3.75' }, { until_year: '', rate: '10.75', floating: true }]} />);

        await userEvent.click(screen.getByRole('button', { name: '+ Tambah jenjang' }));

        expect(data()).toEqual([
            { until_year: '1', rate: '3.75' },
            { until_year: '', rate: '', floating: false },
            { until_year: '', rate: '10.75', floating: true },
        ]);
    });

    it('minimal dua jenjang — tombol hapus baru muncul di jenjang ketiga', async () => {
        render(<Uji awal={[{ until_year: '1', rate: '5' }, { until_year: '', rate: '9' }]} />);
        expect(screen.queryByRole('button', { name: /Hapus jenjang/ })).toBeNull();

        await userEvent.click(screen.getByRole('button', { name: '+ Tambah jenjang' }));
        await userEvent.click(screen.getByRole('button', { name: 'Hapus jenjang 2' }));

        expect(data()).toHaveLength(2);
    });

    it('galat dari server tampil di baris yang bersangkutan', () => {
        render(
            <Uji
                awal={[{ until_year: '5', rate: '5' }, { until_year: '3', rate: '7' }, { until_year: '', rate: '9' }]}
                errors={{ 'tiers.1.until_year': 'Jenjang ini mulai tahun ke-6, jadi batasnya paling cepat tahun ke-6.' }}
            />,
        );

        expect(screen.getAllByRole('listitem')[1]).toHaveTextContent('paling cepat tahun ke-6');
    });
});
