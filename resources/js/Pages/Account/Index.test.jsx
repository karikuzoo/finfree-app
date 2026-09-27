import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import AccountIndex from './Index';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({ ...(await asli()), Head: () => null }));

const KINDS = [
    { value: 'bank', label: 'Bank', liquid: true },
    { value: 'cash', label: 'Tunai', liquid: true },
    { value: 'stock', label: 'Saham', liquid: false },
    { value: 'fund', label: 'Reksa Dana', liquid: false },
    { value: 'gold', label: 'Emas', liquid: false },
];

function rekening(ubah = {}) {
    return {
        id: 3,
        name: 'Portofolio saham',
        kind: 'stock',
        kind_label: 'Saham',
        institution: 'Stockbit',
        opening_balance: 20_000_000,
        balance: 20_000_000,
        allocated: 0,
        allocated_goals: [],
        free: 20_000_000,
        needs_valuation: true,
        kind_locked: false,
        ...ubah,
    };
}

function tampilkan(akun = [rekening()]) {
    return render(<AccountIndex accounts={akun} totalAssets={20_000_000} composition={[]} kinds={KINDS} />);
}

const dialog = () => screen.getByRole('dialog');

describe('Halaman Rekening & aset', () => {
    /**
     * Bug yang melahirkan test ini: form ubah selalu terpasang, jadi isian
     * yang ditolak (saham → emas) masih tampil saat form dibuka lagi — bukan
     * data rekeningnya yang sebenarnya.
     */
    it('isian yang dibatalkan tidak terbawa saat form dibuka lagi', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Ubah' }));
        const nama = within(dialog()).getByLabelText('Nama rekening');
        await userEvent.clear(nama);
        await userEvent.type(nama, 'Nama yang batal');
        await userEvent.selectOptions(within(dialog()).getByLabelText('Jenis'), 'gold');
        await userEvent.click(within(dialog()).getByRole('button', { name: 'Batal' }));

        await userEvent.click(screen.getByRole('button', { name: 'Ubah' }));

        expect(within(dialog()).getByLabelText('Nama rekening')).toHaveValue('Portofolio saham');
        expect(within(dialog()).getByLabelText('Jenis')).toHaveValue('stock');
    });

    it('form tambah selalu dibuka kosong', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Tambah rekening' }));
        await userEvent.type(within(dialog()).getByLabelText('Nama rekening'), 'Setengah jadi');
        await userEvent.click(within(dialog()).getByRole('button', { name: 'Batal' }));

        await userEvent.click(screen.getByRole('button', { name: 'Tambah rekening' }));

        expect(within(dialog()).getByLabelText('Nama rekening')).toHaveValue('');
    });

    /** Jenis dikunci sejak awal, bukan baru ditolak setelah disimpan. */
    it('jenis rekening yang punya riwayat tidak bisa diganti', async () => {
        tampilkan([rekening({ kind_locked: true })]);

        await userEvent.click(screen.getByRole('button', { name: 'Ubah' }));

        expect(within(dialog()).getByLabelText('Jenis')).toBeDisabled();
        expect(within(dialog()).getByText(/sudah punya riwayat transaksi/)).toBeInTheDocument();
    });

    it('rekening saham memilih sekuritasnya dari daftar', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Ubah' }));

        expect(within(dialog()).getByText('Sekuritas atau aplikasi (opsional)')).toBeInTheDocument();
        expect(within(dialog()).getByRole('radio', { name: /Stockbit/ })).toHaveAttribute('aria-checked', 'true');
        expect(within(dialog()).getByRole('radio', { name: /Ajaib/ })).toBeInTheDocument();
    });

    /** Ganti jenis: pilihan dari daftar lama dikosongkan, isian bebas dibiarkan. */
    it('mengganti jenis mengosongkan lembaga dari daftar jenis lama', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Tambah rekening' }));
        await userEvent.click(within(dialog()).getByRole('radio', { name: /^BCA/ }));
        await userEvent.selectOptions(within(dialog()).getByLabelText('Jenis'), 'gold');

        expect(within(dialog()).getByText('Tempat menyimpan emas (opsional)')).toBeInTheDocument();
        expect(within(dialog()).getByRole('radio', { name: /Antam/ })).toHaveAttribute('aria-checked', 'false');
        expect(within(dialog()).queryByRole('textbox', { name: '' })).toBeNull();
    });

    it('tunai memakai isian teks biasa', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Tambah rekening' }));
        await userEvent.selectOptions(within(dialog()).getByLabelText('Jenis'), 'cash');

        expect(within(dialog()).getByLabelText('Lembaga (opsional)')).toBeInTheDocument();
        expect(within(dialog()).queryByRole('radiogroup')).toBeNull();
    });
});
