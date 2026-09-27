import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import GoalProgressList from './GoalProgressList';

function tujuan(persen, ubah = {}) {
    return {
        id: 1,
        name: 'Rumah pertama',
        current_amount: 3_000_000,
        target_amount: 2_000_000_000,
        progress_percentage: persen,
        ...ubah,
    };
}

/** Lebar batang progres, dibaca dari gaya inline-nya. */
function lebarBatang(container) {
    return container.querySelector('.bg-lime-500').style.width;
}

describe('GoalProgressList', () => {
    /**
     * Bug yang melahirkan test ini: Rp 3 juta dari target Rp 2 miliar adalah
     * 0,15%, dan `toFixed(0)` menjadikannya "0%" — tidak terbedakan dari tujuan
     * yang belum disentuh sama sekali.
     */
    it('progres kecil tampil "<1%", bukan "0%"', () => {
        const { container } = render(<GoalProgressList goals={[tujuan(0.15)]} />);

        expect(screen.getByText('<1%')).toBeInTheDocument();
        expect(screen.queryByText('0%')).toBeNull();
        // Batangnya tetap kelihatan, bukan lenyap.
        expect(lebarBatang(container)).toBe('1.5%');
    });

    it('tujuan yang benar-benar belum disentuh tetap "0%" tanpa batang', () => {
        const { container } = render(
            <GoalProgressList goals={[tujuan(0, { current_amount: 0 })]} />,
        );

        expect(screen.getByText('0%')).toBeInTheDocument();
        expect(lebarBatang(container)).toBe('0%');
    });

    /** 99,6% dibulatkan menjadi "100%" akan mengaku tercapai padahal belum. */
    it('hampir tercapai tampil ">99%", bukan "100%"', () => {
        render(<GoalProgressList goals={[tujuan(99.6)]} />);

        expect(screen.getByText('>99%')).toBeInTheDocument();
        expect(screen.queryByText('100%')).toBeNull();
    });

    it('persentase biasa dibulatkan', () => {
        const { container } = render(<GoalProgressList goals={[tujuan(42.4)]} />);

        expect(screen.getByText('42%')).toBeInTheDocument();
        expect(lebarBatang(container)).toBe('42.4%');
    });

    it('melebihi target dibatasi 100%', () => {
        const { container } = render(<GoalProgressList goals={[tujuan(130)]} />);

        expect(screen.getByText('100%')).toBeInTheDocument();
        expect(lebarBatang(container)).toBe('100%');
    });

    it('menampilkan nominal terkumpul dan target', () => {
        render(<GoalProgressList goals={[tujuan(0.15)]} />);

        expect(screen.getByText('Rp 3.000.000 / Rp 2.000.000.000')).toBeInTheDocument();
    });

    it('tanpa tujuan menampilkan keterangan kosong', () => {
        render(<GoalProgressList goals={[]} />);

        expect(screen.getByText('Belum ada tujuan aktif.')).toBeInTheDocument();
    });
});
