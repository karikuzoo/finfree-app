/**
 * Pemeriksaan sisi browser untuk form pendaftaran.
 *
 * **Ini hanya pengingat lebih awal, bukan pengaman.** Yang menentukan diterima
 * atau tidak tetap validasi backend di `RegisteredUserController` — siapa pun
 * bisa mengirim request langsung tanpa lewat halaman ini.
 *
 * Aturannya sengaja ditulis mencerminkan aturan backend. Kalau salah satunya
 * diubah, ubah keduanya; kalau tidak, pengguna melihat "boleh" di layar lalu
 * ditolak server tanpa tahu sebabnya.
 */

// Cerminan dari: regex:/^[\pL\s.'-]+$/u
// \p{L} mencakup huruf beraksen, bukan hanya A–Z.
const POLA_NAMA = /^[\p{L}\s.'-]+$/u;

// Sengaja longgar. Tujuannya menangkap salah ketik yang jelas — lupa @, lupa
// domain — bukan menegakkan RFC 5322. Regex email yang "benar-benar lengkap"
// panjangnya ratusan karakter dan tetap menolak alamat sah.
const POLA_EMAIL = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;

export function periksaNama(nilai) {
    const teks = String(nilai ?? '').trim();

    if (!teks) return 'Nama wajib diisi.';
    if (!POLA_NAMA.test(teks)) {
        return 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.';
    }
    if (teks.length > 255) return 'Nama maksimal 255 karakter.';

    return null;
}

export function periksaEmail(nilai) {
    const teks = String(nilai ?? '').trim();

    if (!teks) return 'Email wajib diisi.';
    if (!POLA_EMAIL.test(teks)) return 'Format email tidak valid.';
    if (teks !== teks.toLowerCase()) {
        return 'Email harus ditulis dengan huruf kecil.';
    }

    return null;
}

export function periksaKataSandi(nilai) {
    const teks = String(nilai ?? '');

    if (!teks) return 'Kata sandi wajib diisi.';

    // Pesannya sengaja mengarahkan ke daftar syarat di bawah field, bukan
    // menyebut satu aturan yang gagal — pengguna bisa melihat sendiri mana
    // yang belum tercentang.
    const lolos =
        teks.length >= 6 &&
        /[A-Z]/.test(teks) &&
        /[a-z]/.test(teks) &&
        /\d/.test(teks) &&
        /[^A-Za-z0-9]/.test(teks);

    return lolos ? null : 'Kata sandi belum memenuhi semua syarat di bawah.';
}

export function periksaKonfirmasi(nilai, kataSandi) {
    if (!nilai) return 'Konfirmasi kata sandi wajib diisi.';
    if (nilai !== kataSandi) return 'Konfirmasi kata sandi tidak cocok.';

    return null;
}

export const pemeriksa = {
    name: (data) => periksaNama(data.name),
    email: (data) => periksaEmail(data.email),
    password: (data) => periksaKataSandi(data.password),
    password_confirmation: (data) =>
        periksaKonfirmasi(data.password_confirmation, data.password),
};
