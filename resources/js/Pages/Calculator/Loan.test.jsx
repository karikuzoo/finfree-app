import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import CalculatorLoan from './Loan';

vi.mock('@/Layouts/PublicLayout', () => ({ default: ({ children }) => <>{children}</> }));
vi.mock('@/Components/LoanChart', () => ({ default: () => <div data-testid="grafik-pinjaman" /> }));
// Galat yang sudah ada di halaman saat dimuat (tautan yang ditolak server).
let galatHalaman = {};

vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    Head: () => null,
    usePage: () => ({ props: { errors: galatHalaman, auth: { user: null } } }),
}));

beforeEach(() => {
    galatHalaman = {};
});

/** Bentuknya mengikuti GoalCalculatorService::calculateLoan. */
const hasil = (ubah = {}) => ({
    monthly_installment: 4_825_109,
    last_installment: 4_825_109,
    principal: 500_000_000,
    total_payment: 1_158_026_160,
    total_interest: 658_026_160,
    monthly_rate: 0.1 / 12,
    months: 240,
    yearly: [
        { year: 1, principal_paid: 8_214_000, interest_paid: 49_687_308, balance: 491_786_000 },
        { year: 2, principal_paid: 9_074_000, interest_paid: 48_827_308, balance: 482_712_000 },
    ],
    tiers: [{ from_month: 1, to_month: 240, rate: 10, installment: 4_825_109 }],
    series: [{ month: 0, balance: 500_000_000, cumulative_interest: 0 }],
    ...ubah,
});

const input = { principal: '500000000', annual_interest_rate: '10', months: '240' };

describe('Kalkulator Pinjaman / KPR', () => {
    it('sebelum dihitung, panel hasil memberi petunjuk, bukan angka nol', () => {
        render(<CalculatorLoan input={null} result={null} />);

        expect(screen.getByText(/Isi pokok pinjaman, tenor, dan suku bunga/)).toBeInTheDocument();
        expect(screen.queryByText('Angsuran bulanan')).toBeNull();
    });

    it('menampilkan angsuran, total bunga, dan grafik', () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.getByText('Rp 4.825.109')).toBeInTheDocument();
        expect(screen.getByText('Rp 658.026.160')).toBeInTheDocument();
        expect(screen.getByTestId('grafik-pinjaman')).toBeInTheDocument();
        // Konvensinya disebut terbuka — itu yang menjelaskan kenapa cocok
        // dengan simulasi bank.
        expect(screen.getByText(/dibagi dua belas/)).toBeInTheDocument();
    });

    it('angsuran terakhir yang berbeda disebutkan beserta alasannya', () => {
        render(<CalculatorLoan input={input} result={hasil({ last_installment: 4_824_950 })} />);

        expect(screen.getByText(/Angsuran terakhir Rp 4\.824\.950/)).toHaveTextContent('lunas tepat nol');
    });

    it('pinjaman tidak ditawari dijadikan tujuan', () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.queryByRole('link', { name: 'Jadikan Tujuan' })).toBeNull();
    });

    it('rincian per tahun tertutup dulu, lalu bisa dibuka', async () => {
        render(<CalculatorLoan input={input} result={hasil()} />);

        expect(screen.queryByRole('table')).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Tampilkan' }));

        expect(screen.getAllByRole('row')).toHaveLength(3);
        expect(screen.getByText('Rp 491.786.000')).toBeInTheDocument();
    });


    it('berjenjang menggantikan isian bunga tunggal dengan daftar jenjang', async () => {
        render(<CalculatorLoan input={null} result={null} />);

        expect(screen.getByLabelText('Suku bunga (% / tahun)')).toBeInTheDocument();
        await userEvent.click(screen.getByRole('button', { name: 'Berjenjang' }));

        expect(screen.queryByLabelText('Suku bunga (% / tahun)')).toBeNull();
        expect(screen.getByLabelText('Jenjang 1: bunga (% per tahun)')).toBeInTheDocument();
        // Baris terakhir tidak punya batas tahun — berlaku sampai tenor habis.
        expect(screen.getByLabelText('Jenjang 1: sampai tahun ke')).toBeInTheDocument();
        expect(screen.queryByLabelText('Jenjang 2: sampai tahun ke')).toBeNull();
    });

    /**
     * Isian yang tidak berlaku untuk jenis bunganya, dan data keuangan saat
     * cek kesehatan dilewati, tidak ikut terkirim — URL hasilnya hanya
     * membawa angka yang benar-benar dipakai.
     */
    it('isian yang tidak berlaku tidak ikut terkirim', async () => {
        const { kiriman } = sadapKiriman('get');
        render(
            <CalculatorLoan
                input={{ ...input, rate_type: 'fixed', floating_rate: '11', monthly_income: '' }}
                result={null}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Hitung Sekarang' }));

        expect(kiriman[0].data).toEqual({
            principal: '500000000',
            annual_interest_rate: '10',
            months: '240',
            rate_type: 'fixed',
        });
    });

    /**
     * Daftar jenjang dikirim dengan format `indices` (tiers[0][rate]) —
     * format bawaan membuat PHP memecah tiap isian jadi baris tersendiri —
     * tanpa batas tahun di baris terakhir, dan `floating` sebagai 1/0.
     */
    it('jenjang dikirim sebagai larik berindeks', async () => {
        const { kiriman } = sadapKiriman('get');
        render(
            <CalculatorLoan
                input={{
                    principal: '500000000',
                    months: '240',
                    rate_type: 'tiered',
                    tiers: [
                        { until_year: '1', rate: '3.75' },
                        { until_year: '4', rate: '6.75' },
                        { until_year: '99', rate: '10.75', floating: '1' },
                    ],
                }}
                result={null}
            />,
        );

        await userEvent.click(screen.getByRole('button', { name: 'Hitung Sekarang' }));

        expect(kiriman[0].opsi.queryStringArrayFormat).toBe('indices');
        expect(kiriman[0].data).toEqual({
            principal: '500000000',
            months: '240',
            rate_type: 'tiered',
            tiers: [
                { until_year: '1', rate: '3.75', floating: 0 },
                { until_year: '4', rate: '6.75', floating: 0 },
                { rate: '10.75', floating: 1 },
            ],
        });
    });

    it('cek kesehatan tertutup dulu, terbuka sendiri bila pendapatan sudah diisi', async () => {
        const { unmount } = render(<CalculatorLoan input={null} result={null} />);
        expect(screen.queryByLabelText('Pendapatan bersih per bulan')).toBeNull();
        await userEvent.click(screen.getByRole('button', { name: 'Isi data keuangan' }));
        expect(screen.getByLabelText('Pendapatan bersih per bulan')).toBeInTheDocument();
        expect(screen.getByLabelText('Pajak tahunan')).toBeInTheDocument();
        unmount();

        render(<CalculatorLoan input={{ ...input, monthly_income: '20000000' }} result={null} />);
        expect(screen.getByLabelText('Pendapatan bersih per bulan')).toBeInTheDocument();
    });

    /** Contoh pengguna (30 Sep 2026). */
    it('berjenjang menampilkan angsuran per jenjang beserta kenaikannya', () => {
        render(
            <CalculatorLoan
                input={{ principal: '500000000', months: '240', rate_type: 'tiered', tiers: [{}, {}, {}, { floating: '1' }] }}
                result={hasil({
                    monthly_installment: 2_964_442,
                    tiers: [
                        { from_month: 1, to_month: 12, rate: 3.75, installment: 2_964_442 },
                        { from_month: 13, to_month: 48, rate: 6.75, installment: 3_763_866 },
                        { from_month: 49, to_month: 120, rate: 9.75, installment: 4_546_179 },
                        { from_month: 121, to_month: 240, rate: 10.75, installment: 4_739_763 },
                    ],
                })}
            />,
        );

        expect(screen.getByText('Angsuran tahun 1')).toBeInTheDocument();
        expect(screen.getByText('Tahun 2–4')).toBeInTheDocument();
        expect(screen.getByText('Tahun 11–20')).toBeInTheDocument();
        expect(screen.getByText('+27%')).toBeInTheDocument();
        expect(screen.getByText('(perkiraan)')).toBeInTheDocument();
        expect(screen.getByText(/60% lebih besar dari\s+tahun pertama/)).toBeInTheDocument();
    });


    /**
     * Tautan yang ditolak server (mis. `rate_type=fix_float` dari versi lama)
     * diarahkan ke alamat bersih dengan galat di props halaman — bukan di
     * form.errors, yang baru terisi sesudah form dikirim. Tanpa ini halamannya
     * tampil kosong tanpa penjelasan.
     */
    it('galat dari tautan yang ditolak tampil saat halaman dimuat', () => {
        galatHalaman = { rate_type: 'Jenis bunga di tautan ini tidak dikenal — mungkin tautan dari versi lama. Pilih jenis bunganya lagi.' };
        render(<CalculatorLoan input={null} result={null} />);

        expect(screen.getByText(/Jenis bunga di tautan ini tidak dikenal/)).toBeInTheDocument();
    });

    /** Bug "tombol diam" (Goal.jsx): pastikan tombolnya benar-benar mengirim. */
    it('Hitung Sekarang mengirim isian ke route kalkulator pinjaman', async () => {
        const { kiriman } = sadapKiriman('get');
        render(<CalculatorLoan input={input} result={null} />);

        await userEvent.click(screen.getByRole('button', { name: 'Hitung Sekarang' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/calculator.loan');
    });
});
