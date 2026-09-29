import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import ActivityCalendar from './ActivityCalendar';

function kalender(bulan = '2026-09') {
    return {
        month: bulan,
        label: bulan,
        notes: [{ id: 1, date: `${bulan}-10`, body: 'Gajian' }],
        reminders: [{ id: 1, date: `${bulan}-20`, time: '09:00', title: 'Bayar listrik', completed: false }],
    };
}

describe('ActivityCalendar', () => {
    beforeEach(() => {
        // "Hari ini" dibekukan: 29 September 2026, 12.00 WIB.
        vi.useFakeTimers({ toFake: ['Date'] });
        vi.setSystemTime(new Date('2026-09-29T05:00:00Z'));
    });

    afterEach(() => vi.useRealTimers());

    /** Menengok setahun lalu dulu butuh dua belas klik panah. */
    it('bulan dan tahun bisa dipilih langsung', async () => {
        const { kiriman } = sadapKiriman('get');
        render(<ActivityCalendar calendar={kalender()} />);

        await userEvent.selectOptions(screen.getByRole('combobox', { name: 'Tahun' }), '2025');

        expect(kiriman[0].url).toBe('/dashboard');
        expect(kiriman[0].data).toEqual({ bulan: '2025-09' });
    });

    it('panah menggeser satu bulan', async () => {
        const { kiriman } = sadapKiriman('get');
        render(<ActivityCalendar calendar={kalender()} />);

        await userEvent.click(screen.getByRole('button', { name: 'Bulan berikutnya' }));

        expect(kiriman[0].data).toEqual({ bulan: '2026-10' });
    });

    it('"Hari ini" hanya muncul di bulan lain, dan membawa pulang', async () => {
        const { kiriman } = sadapKiriman('get');

        const { unmount } = render(<ActivityCalendar calendar={kalender()} />);
        expect(screen.queryByRole('button', { name: 'Hari ini' })).toBeNull();
        unmount();

        render(<ActivityCalendar calendar={kalender('2026-06')} />);
        await userEvent.click(screen.getByRole('button', { name: 'Hari ini' }));

        expect(kiriman[0].data).toEqual({ bulan: '2026-09' });
    });

    it('hari ini ditandai dan penanda aktivitas terbaca pembaca layar', () => {
        render(<ActivityCalendar calendar={kalender()} />);

        expect(screen.getByRole('button', { name: 'Tanggal 29' })).toHaveClass('bg-lime-softBg');
        expect(screen.getByRole('button', { name: 'Tanggal 10, ada catatan' })).toBeInTheDocument();
        expect(screen.getByRole('button', { name: 'Tanggal 20, 1 pengingat' })).toBeInTheDocument();
    });

    /** Di Arus merah berarti galat atau angka negatif — hari Minggu bukan keduanya. */
    it('hari Minggu tidak diwarnai merah', () => {
        const { container } = render(<ActivityCalendar calendar={kalender()} />);

        expect(container.querySelector('.text-state-danger')).toBeNull();
    });
});
