import { render, screen } from '@testing-library/react';
import { describe, expect, it } from 'vitest';

import KeteranganRekening from './KeteranganRekening';

/** Bentuknya mengikuti AccountBalanceService::accountOptions. */
function rekening(ubah = {}) {
    return {
        id: 1,
        name: 'BCA - Utama',
        balance: 50_000_000,
        allocated: 5_000_000,
        free: 45_000_000,
        allocated_goals: [{ id: 7, name: 'Beli Monas', amount: 5_000_000 }],
        ...ubah,
    };
}

function teks(container) {
    return container.textContent.replace(/\s+/g, ' ').trim();
}

describe('KeteranganRekening', () => {
    it('menyebut yang bebas dipakai dan untuk tujuan apa sisanya', () => {
        const { container } = render(<KeteranganRekening rekening={rekening()} />);

        expect(teks(container)).toBe(
            'Bebas dipakai Rp 45.000.000 — Rp 5.000.000 lainnya untuk Beli Monas.',
        );
    });

    it('beberapa tujuan disebut semua', () => {
        const { container } = render(
            <KeteranganRekening
                rekening={rekening({
                    allocated: 8_000_000,
                    free: 42_000_000,
                    allocated_goals: [
                        { id: 7, name: 'Beli Monas', amount: 5_000_000 },
                        { id: 8, name: 'Dana darurat', amount: 3_000_000 },
                    ],
                })}
            />,
        );

        expect(teks(container)).toContain('untuk Beli Monas, Dana darurat.');
    });

    /**
     * Menyunting pengeluaran Rp 2 juta dari rekening yang sama: uang itu
     * "kembali" dulu sebelum nominal barunya diambil, jadi batasnya ikut naik.
     * Tanpa ini keterangannya lebih sempit dari yang diterima server.
     */
    it('saat menyunting, nominal lamanya ikut dihitung kembali', () => {
        render(<KeteranganRekening rekening={rekening()} kembalikan={2_000_000} />);

        expect(screen.getByText('Rp 47.000.000')).toBeInTheDocument();
    });

    it('rekening tanpa dana tujuan tidak menampilkan apa pun', () => {
        const { container } = render(
            <KeteranganRekening
                rekening={rekening({ allocated: 0, free: 50_000_000, allocated_goals: [] })}
            />,
        );

        expect(container).toBeEmptyDOMElement();
    });

    it('belum ada rekening terpilih tidak error', () => {
        const { container } = render(<KeteranganRekening rekening={undefined} />);

        expect(container).toBeEmptyDOMElement();
    });
});
