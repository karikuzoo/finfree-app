import { spellRupiah } from "@/utils/format";

/**
 * Membacakan nominal dengan kata satuannya, tepat di bawah kolomnya.
 *
 * Deretan titik pada angka besar terlalu mirip untuk dihitung sekilas:
 * Rp 125.000.000 dan Rp 125.000.000.000 nyaris tak terbedakan saat mengetik.
 * Salah satu target di aplikasi ini pernah terisi seribu kali lipat dari yang
 * dimaksud, dan barunya ketahuan setelah rencana menabungnya menuntut
 * Rp 2,26 miliar per bulan.
 *
 * Bacaannya SELALU muncul begitu nominalnya mencapai satu juta — bukan hanya
 * saat angkanya dianggap janggal. Ambang batas apa pun akan salah menebak:
 * dana pensiun tiga miliar itu wajar, sedangkan uang muka seratus juta yang
 * kelebihan tiga nol tidak. Membacakannya selalu membuat pengguna yang
 * memeriksa punya sesuatu untuk diperiksa, tanpa aplikasi perlu menghakimi
 * niatnya.
 */
export default function BacaanNominal({ value }) {
    const bacaan = spellRupiah(value);

    if (bacaan === "") {
        return null;
    }

    return (
        <p className="mt-1.5 text-xs font-medium text-text-secondary">
            Terbaca: <span className="text-text-primary">{bacaan} rupiah</span>
        </p>
    );
}
