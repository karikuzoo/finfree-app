import { render, screen, within } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import AccountShow from './Show';

vi.mock('@/Layouts/AuthenticatedLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@inertiajs/react', async (asli) => ({ ...(await asli()), Head: () => null }));

const KINDS = [
    { value: 'bank', label: 'Bank', liquid: true },
    { value: 'gold', label: 'Emas', liquid: false },
];

/** Bentuknya mengikuti AccountController::kartu + last_valuation. */
const akun = (ubah = {}) => ({
    id: 3,
    name: 'BCA - Utama',
    kind: 'bank',
    kind_label: 'Bank',
    institution: 'Bank BCA',
    opening_balance: 227_000_000,
    units: null,
    unit: null,
    balance: 3_068_980_000,
    allocated: 2_401_511_558,
    allocated_goals: [
        { id: 1, name: 'Rumah Navapark', amount: 2_000_000_000 },
        { id: 2, name: 'Beli Monas', amount: 401_511_558 },
    ],
    free: 667_468_442,
    needs_valuation: false,
    kind_locked: true,
    last_valuation: null,
    ...ubah,
});

/** Bentuknya mengikuti paginator Laravel + AccountController::mutasi. */
const mutasi = (ubah = {}) => ({
    data: [
        { id: 9, occurred_on: '2026-09-10', name: 'Belanja bulanan', type: 'expense', type_label: 'Pengeluaran', category: 'Belanja', counterpart: null, debt: null, amount: -1_500_000, balance_after: 3_068_980_000 },
        { id: 8, occurred_on: '2026-09-06', name: 'Setor tunai', type: 'transfer', type_label: 'Transfer masuk', category: null, counterpart: 'Dompet', debt: null, amount: 500_000, balance_after: 3_070_480_000 },
        { id: 7, occurred_on: '2026-09-05', name: 'Ke reksa dana', type: 'transfer', type_label: 'Transfer', category: null, counterpart: 'Bibit', debt: null, amount: -2_000_000, balance_after: 3_069_980_000 },
    ],
    current_page: 1,
    last_page: 2,
    prev_page_url: null,
    next_page_url: '/rekening/3?page=2',
    ...ubah,
});

const tampilkan = (a = akun(), m = mutasi()) => render(<AccountShow account={a} mutations={m} kinds={KINDS} />);

describe('Detail rekening', () => {
    it('menampilkan saldo, saldo awal, dan sisa bebas dipakai', () => {
        tampilkan();

        expect(screen.getByRole('heading', { name: 'BCA - Utama' })).toBeInTheDocument();
        expect(screen.getAllByText('Rp 3.068.980.000').length).toBeGreaterThan(0);
        expect(screen.getByText('Rp 227.000.000')).toBeInTheDocument();
        expect(screen.getByText('Rp 667.468.442')).toBeInTheDocument();
    });

    it('merinci dana tujuan per tujuan beserta nominalnya', () => {
        tampilkan();

        const bagian = screen.getByRole('heading', { name: 'Dana tujuan di rekening ini' }).closest('section');
        expect(within(bagian).getByText('Rumah Navapark')).toBeInTheDocument();
        expect(within(bagian).getByText('Rp 401.511.558')).toBeInTheDocument();
    });

    it('mutasi memberi tanda masuk/keluar, lawan transfer, dan saldo sesudahnya', () => {
        tampilkan();

        expect(screen.getByText('+Rp 500.000')).toBeInTheDocument();
        expect(screen.getByText('−Rp 1.500.000')).toBeInTheDocument();
        expect(screen.getByText(/Transfer masuk · dari Dompet/)).toBeInTheDocument();
        expect(screen.getByText(/Transfer · ke Bibit/)).toBeInTheDocument();
        expect(screen.getByText(/Pengeluaran · Belanja/)).toBeInTheDocument();
        expect(screen.getByText('Saldo Rp 3.070.480.000')).toBeInTheDocument();
    });

    it('menautkan ke halaman mutasi berikutnya', () => {
        tampilkan();

        expect(screen.getByRole('link', { name: 'Lebih lama →' })).toHaveAttribute('href', '/rekening/3?page=2');
        expect(screen.queryByRole('link', { name: '← Lebih baru' })).toBeNull();
        expect(screen.getByText('Halaman 1 dari 2')).toBeInTheDocument();
    });

    it('rekening tanpa transaksi dan tanpa dana tujuan', () => {
        tampilkan(
            akun({ allocated: 0, allocated_goals: [], free: 227_000_000 }),
            mutasi({ data: [], last_page: 1, next_page_url: null }),
        );

        expect(screen.getByText('Belum ada transaksi di rekening ini.')).toBeInTheDocument();
        expect(screen.queryByRole('heading', { name: 'Dana tujuan di rekening ini' })).toBeNull();
        expect(screen.queryByRole('navigation', { name: 'Halaman mutasi' })).toBeNull();
    });

    it('aset yang perlu dinilai menyebut kapan nilainya terakhir diperbarui', () => {
        tampilkan(akun({ kind: 'gold', kind_label: 'Emas', needs_valuation: true, last_valuation: '2026-09-15' }));

        expect(screen.getByText(/Nilai terakhir diperbarui 15 Sep 2026/)).toBeInTheDocument();
    });

    it('tombol Ubah membuka form rekening yang sama dengan halaman daftar', async () => {
        tampilkan();

        await userEvent.click(screen.getByRole('button', { name: 'Ubah' }));

        expect(within(screen.getByRole('dialog')).getByLabelText('Nama rekening')).toHaveValue('BCA - Utama');
    });
    it('valas punya tombol Perbarui nilai yang membuka form penilaian', async () => {
        tampilkan(akun({ kind: 'valas', kind_label: 'Valas', currency: 'USD', unit: 'USD', units: 1500, needs_valuation: true, balance: 24_450_000 }));

        expect(screen.getByText(/Bank BCA · Valas USD/)).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Perbarui nilai' }));

        const form = within(screen.getByRole('dialog'));
        expect(form.getByLabelText('Nilai totalnya sekarang')).toHaveValue('24.450.000');
        expect(form.getByLabelText('Jumlah USD (opsional)')).toBeInTheDocument();
    });

    it('rekening bank tidak punya tombol Perbarui nilai', () => {
        tampilkan();

        expect(screen.queryByRole('button', { name: 'Perbarui nilai' })).toBeNull();
    });
});
