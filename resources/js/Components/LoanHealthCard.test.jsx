import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import LoanHealthCard from './LoanHealthCard';

/** Bentuknya mengikuti LoanHealthService::evaluate. */
const health = (ubah = {}) => ({
    status: 'risky',
    now: { installments: 5_000_000, dsr: 25, residual: 7_000_000 },
    worst: { installments: 8_500_000, dsr: 42.5, residual: -500_000, label: 'setelah 3 tahun bunga tetap' },
    monthly_taxes: 100_000,
    reasons: ['Angsuran KPR memakan 25% pendapatan — masih dalam batas sehat 30%.', 'Setelah 3 tahun bunga tetap, rasionya menjadi 42,5% — di atas batas 40%.'],
    thresholds: { dsr_healthy_max: 30, dsr_caution_max: 40, residual_min_percentage: 10 },
    ...ubah,
});

describe('LoanHealthCard', () => {
    it('menampilkan label, kedua keadaan, dan alasannya', () => {
        render(<LoanHealthCard health={health()} />);

        expect(screen.getByText('Berisiko')).toBeInTheDocument();
        expect(screen.getByText('Angsuran sekarang')).toBeInTheDocument();
        expect(screen.getByText('Setelah 3 tahun bunga tetap')).toBeInTheDocument();
        expect(screen.getByText('42,5%')).toBeInTheDocument();
        expect(screen.getByText(/di atas batas 40%/)).toBeInTheDocument();
    });

    it('sisa uang minus ditandai, bukan ditampilkan sebagai angka biasa', () => {
        render(<LoanHealthCard health={health()} />);

        const minus = screen.getByText('−Rp 500.000');
        expect(minus).toHaveClass('text-state-danger');
    });

    it('bunga tetap hanya punya satu keadaan', () => {
        render(<LoanHealthCard health={health({ status: 'healthy', worst: null })} />);

        expect(screen.getByText('Sehat')).toBeInTheDocument();
        expect(screen.queryByText('Setelah 3 tahun bunga tetap')).toBeNull();
    });

    /** Teks patokan mengikuti config server, bukan angka yang ditulis di sini. */
    it('patokan yang disebut mengikuti batas dari server', () => {
        render(<LoanHealthCard health={health({ thresholds: { dsr_healthy_max: 35, dsr_caution_max: 50, residual_min_percentage: 15 } })} />);

        expect(screen.getByText(/sampai 35% sehat/)).toHaveTextContent('35–50% waspada, di atas 50% berisiko');
        expect(screen.getByText(/sampai 35% sehat/)).toHaveTextContent('di bawah 15% pendapatan');
    });

    /**
     * Dengan kenaikan gaji, tiap keadaan dinilai dengan pendapatan yang
     * berbeda — angkanya ditampilkan, beserta pengingat bahwa pengeluaran
     * dianggap tetap (asumsi yang membuat hasilnya lebih ringan).
     */
    it('kenaikan gaji menampilkan pendapatan perkiraan tiap keadaan', () => {
        render(
            <LoanHealthCard
                health={health({
                    status: 'healthy',
                    income_growth: 5,
                    now: { income: 15_000_000, installments: 2_964_442, dsr: 19.8, residual: 6_035_558 },
                    worst: { income: 18_232_594, installments: 4_546_179, dsr: 24.9, residual: 7_686_415, label: 'mulai tahun ke-5' },
                })}
            />,
        );

        expect(screen.getAllByText('Pendapatan / bulan')).toHaveLength(2);
        expect(screen.getByText('Rp 18.232.594')).toBeInTheDocument();
        expect(screen.getByText(/dinaikkan 5% per tahun/)).toHaveTextContent('Pengeluaran');
    });

    it('tanpa kenaikan gaji, baris pendapatan tidak ditampilkan', () => {
        render(<LoanHealthCard health={health({ income_growth: 0 })} />);

        expect(screen.queryByText('Pendapatan / bulan')).toBeNull();
        expect(screen.queryByText(/dinaikkan .* per tahun/)).toBeNull();
    });

    /**
     * Label keseluruhan dulu di ujung kanan judul, tepat di atas kartu
     * keadaan kedua — terbaca sebagai milik kartu itu. Kartu yang menjadi
     * dasar penilaian kini ditandai sendiri, di mana pun letaknya.
     */
    it('menandai kartu yang menjadi dasar penilaian', () => {
        const { unmount } = render(<LoanHealthCard health={health()} />);

        const kartu = screen.getByText('Setelah 3 tahun bunga tetap').closest('div.rounded-lg');
        expect(kartu).toHaveTextContent('Dasar penilaian');
        expect(screen.getByText('Angsuran sekarang').closest('div.rounded-lg')).not.toHaveTextContent('Dasar penilaian');
        unmount();

        // Tanpa keadaan yang lebih berat, dasarnya angsuran sekarang.
        render(<LoanHealthCard health={health({ status: 'healthy', worst: null })} />);
        expect(screen.getByText('Angsuran sekarang').closest('div.rounded-lg')).toHaveTextContent('Dasar penilaian');
        expect(screen.getAllByText('Dasar penilaian')).toHaveLength(1);
    });

    it('label keseluruhan menempel pada judul', () => {
        render(<LoanHealthCard health={health()} />);

        expect(screen.getByRole('heading', { name: 'Kesehatan cicilan' }).parentElement).toHaveTextContent('Berisiko');
    });
});
