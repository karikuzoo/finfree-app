import { render, screen } from '@testing-library/react';
import userEvent from '@testing-library/user-event';
import { beforeEach, describe, expect, it, vi } from 'vitest';

import { sadapKiriman } from '@/test/inertia';
import AuthenticatedLayout from './AuthenticatedLayout';

vi.mock('@inertiajs/react', async (asli) => ({
    ...(await asli()),
    usePage: () => ({ url: '/dashboard', props: { auth: { user: { name: 'Uji Coba', avatar_url: null } } } }),
}));

beforeEach(() => {
    // Layout memanggil route() TANPA argumen untuk route().current(); pengganti
    // global di test/setup.js hanya mengenal bentuk dengan nama.
    globalThis.route = vi.fn((nama, param) => {
        if (nama === undefined) return { current: () => false };

        return param === undefined ? `/${nama}` : `/${nama}/${param}`;
    });
});

describe('AuthenticatedLayout — keluar akun', () => {
    it('meminta konfirmasi dulu, tidak langsung keluar', async () => {
        const { kiriman } = sadapKiriman();
        render(<AuthenticatedLayout>isi</AuthenticatedLayout>);

        await userEvent.click(screen.getByRole('button', { name: 'Keluar akun' }));

        expect(screen.getByRole('dialog')).toHaveTextContent('Keluar dari akun?');
        expect(kiriman).toHaveLength(0);
    });

    it('Batal menutup dialog tanpa keluar', async () => {
        const { kiriman } = sadapKiriman();
        render(<AuthenticatedLayout>isi</AuthenticatedLayout>);

        await userEvent.click(screen.getByRole('button', { name: 'Keluar akun' }));
        await userEvent.click(screen.getByRole('button', { name: 'Batal' }));

        expect(kiriman).toHaveLength(0);
    });

    it('Keluar di dialog benar-benar mengirim logout', async () => {
        const { kiriman } = sadapKiriman();
        render(<AuthenticatedLayout>isi</AuthenticatedLayout>);

        await userEvent.click(screen.getByRole('button', { name: 'Keluar akun' }));
        await userEvent.click(screen.getByRole('button', { name: 'Keluar' }));

        expect(kiriman).toHaveLength(1);
        expect(kiriman[0].url).toBe('/logout');
    });
});
