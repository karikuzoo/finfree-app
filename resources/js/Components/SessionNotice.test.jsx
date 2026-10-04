import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { describe, expect, it, vi } from 'vitest';

import SessionNotice from './SessionNotice';

let props = {};

vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    usePage: () => ({ props }),
}));

describe('SessionNotice', () => {
    it('tidak menampilkan apa pun tanpa pemberitahuan', () => {
        props = {};
        render(<SessionNotice />);

        expect(screen.queryByRole('status')).toBeNull();
    });

    it('menampilkan pemberitahuan dari server dan bisa ditutup', async () => {
        props = { notice: 'Kiriman tadi belum tersimpan.' };
        render(<SessionNotice />);

        expect(screen.getByRole('status')).toHaveTextContent('Kiriman tadi belum tersimpan.');

        await userEvent.click(screen.getByRole('button', { name: 'Tutup pemberitahuan' }));
        expect(screen.queryByRole('status')).toBeNull();
    });
});
