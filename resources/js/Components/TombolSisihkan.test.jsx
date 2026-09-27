import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import TombolSisihkan from './TombolSisihkan';

/** Satu baris Rencana menabung, bentuknya mengikuti SavingsPlanService::mapping. */
function baris(ubah = {}) {
    return {
        goal_id: 7,
        name: 'Beli Monas',
        allocation: 3_750_000,
        remaining_this_month: 3_750_000,
        set_aside_this_month: 0,
        achieved: false,
        can_set_aside: true,
        ...ubah,
    };
}

describe('TombolSisihkan', () => {
    /**
     * Bug yang melahirkan berkas ini: `form.transform(...).post(...)` dirantai
     * seperti di adapter Vue. Di React transform() mengembalikan undefined,
     * klik melempar TypeError, dan tombolnya diam — tidak ada kiriman, tidak
     * ada pesan. Test backend tetap lulus karena endpoint-nya sendiri benar.
     */
    it('menekan tombol utama benar-benar mengirim sisa bulan ini', async () => {
        const { kiriman } = sadapKiriman();
        render(<TombolSisihkan baris={baris()} />);

        await userEvent.click(
            screen.getByRole('button', { name: 'Sudah saya sisihkan Rp 3.750.000' }),
        );

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/goals.set-aside/7');
        expect(kiriman[0].data).toEqual({ amount: 3_750_000 });
    });

    /**
     * Yang dikirim dibaca dari props SAAT diklik. Setelah sekali berhasil,
     * sisa bulan ini berubah; mengirim angka dari render pertama berarti
     * menyisihkan dua kali lipat.
     */
    it('klik berikutnya memakai sisa terbaru, bukan angka basi', async () => {
        const { kiriman } = sadapKiriman();
        const { rerender } = render(<TombolSisihkan baris={baris()} />);

        rerender(
            <TombolSisihkan
                baris={baris({ remaining_this_month: 1_250_000, set_aside_this_month: 2_500_000 })}
            />,
        );

        await userEvent.click(
            screen.getByRole('button', { name: 'Sudah saya sisihkan Rp 1.250.000' }),
        );

        expect(kiriman[0].data).toEqual({ amount: 1_250_000 });
    });

    it('"Jumlah lain…" membuka isian berisi angka saran', async () => {
        render(<TombolSisihkan baris={baris()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Jumlah lain…' }));

        expect(screen.getByLabelText('Nominal yang disisihkan untuk Beli Monas')).toHaveValue(
            '3.750.000',
        );
        expect(
            screen.getByText('Ditambahkan ke dana yang sudah terkumpul, bukan menggantinya.'),
        ).toBeInTheDocument();
    });

    /** Yang dikirim tetap TAMBAHANNYA — nominal yang diketik, bukan total baru. */
    it('jumlah lain mengirim nominal yang diketik', async () => {
        const { kiriman, selesai } = sadapKiriman();
        render(<TombolSisihkan baris={baris()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Jumlah lain…' }));
        const isian = screen.getByLabelText('Nominal yang disisihkan untuk Beli Monas');
        await userEvent.clear(isian);
        await userEvent.type(isian, '1500000');
        await userEvent.click(screen.getByRole('button', { name: 'Sisihkan' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].data).toEqual({ amount: 1_500_000 });

        // Setelah diterima server, isiannya menutup lagi.
        selesai();
        expect(
            await screen.findByRole('button', { name: /Sudah saya sisihkan/ }),
        ).toBeInTheDocument();
    });

    it('isian kosong tidak bisa dikirim', async () => {
        const { kiriman } = sadapKiriman();
        render(<TombolSisihkan baris={baris()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Jumlah lain…' }));
        await userEvent.clear(screen.getByLabelText('Nominal yang disisihkan untuk Beli Monas'));

        expect(screen.getByRole('button', { name: 'Sisihkan' })).toBeDisabled();
        expect(kiriman).toHaveLength(0);
    });

    it('batal menutup isian tanpa mengirim apa pun', async () => {
        const { kiriman } = sadapKiriman();
        render(<TombolSisihkan baris={baris()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Jumlah lain…' }));
        await userEvent.click(screen.getByRole('button', { name: 'Batal' }));

        expect(screen.queryByLabelText('Nominal yang disisihkan untuk Beli Monas')).toBeNull();
        expect(kiriman).toHaveLength(0);
    });

    /** Bulan ini sudah dipenuhi: tidak disarankan lagi, tapi tetap bisa menambah. */
    it('setelah rencana bulan ini terpenuhi, tombol utama diganti keterangan', () => {
        render(
            <TombolSisihkan
                baris={baris({ remaining_this_month: 0, set_aside_this_month: 3_750_000 })}
            />,
        );

        expect(screen.queryByRole('button', { name: /Sudah saya sisihkan/ })).toBeNull();
        expect(screen.getByText('Rencana bulan ini sudah terpenuhi.')).toBeInTheDocument();
        expect(screen.getByText('Bulan ini sudah disisihkan Rp 3.750.000')).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Sisihkan lagi…' })).toBeInTheDocument();
    });

    /** Tombol tidak pernah lenyap tanpa penjelasan. */
    it('tujuan tanpa rekening menjelaskan sebabnya', () => {
        render(<TombolSisihkan baris={baris({ can_set_aside: false })} />);

        expect(screen.queryByRole('button')).toBeNull();
        expect(screen.getByText(/Tentukan dulu rekening tempat dananya berada/)).toBeInTheDocument();
    });

    it('tanpa alokasi bulan ini tetap menyediakan jumlah lain', () => {
        render(
            <TombolSisihkan baris={baris({ allocation: 0, remaining_this_month: 0 })} />,
        );

        expect(
            screen.getByText('Belum ada dana yang bisa dialokasikan untuk target ini bulan ini.'),
        ).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Sisihkan lagi…' })).toBeInTheDocument();
    });

    it('tujuan yang sudah tercapai tidak menampilkan apa pun', () => {
        const { container } = render(<TombolSisihkan baris={baris({ achieved: true })} />);

        expect(container).toBeEmptyDOMElement();
    });
});
