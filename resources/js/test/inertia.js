import { router } from '@inertiajs/core';
import { vi } from 'vitest';

/**
 * Menyadap kiriman formulir Inertia tanpa jaringan.
 *
 * `useForm().post()` pada akhirnya memanggil `router.post(url, data, opsi)`
 * dari @inertiajs/core — di situlah test mencegatnya. Yang diperiksa adalah
 * APA yang benar-benar dikirim, karena bug "tombol diam" dulu justru terjadi
 * di antara klik dan kiriman: tidak ada yang terkirim, tidak ada galat.
 *
 * `selesai()` menjalankan onSuccess milik pemanggil, meniru server yang
 * menerima kiriman itu.
 *
 * @param {'post'|'patch'|'put'} metode
 */
export function sadapKiriman(metode = 'post') {
    const kiriman = [];

    vi.spyOn(router, metode).mockImplementation((url, data, opsi) => {
        kiriman.push({ url, data, opsi });
    });

    return {
        kiriman,
        selesai(i = kiriman.length - 1) {
            kiriman[i]?.opsi?.onSuccess?.({ props: {} });
        },
    };
}
