# Arus — desain Arus

Perubahan ini menerapkan arah visual Arus ke aplikasi Laravel + Inertia existing.
Basis: `baaab87f0607adeaaf2d6a02b3a9d63a8bc96063` (branch `main`).
Branch pengerjaan: `feat/fingoal-arus-redesign`.

## Desain

- Latar #101719, sidebar #131C1F, kartu #182124, aksen mint #98EDCE.
- Font Segoe UI/system, angka tabular, kartu 16px, navigasi desktop 244px.
- Token bernama `lime` dipertahankan sebagai alias aksen mint untuk kompatibilitas seluruh halaman.
- Dashboard mendahulukan ringkasan dana/target/progres, kemudian tujuan utama dan kalender.
- Angka dana terkumpul hanya menjumlahkan tujuan aktif dari backend; bukan klaim total saldo dompet.
- Kalender tetap menjadi lokasi pencatatan setoran, dengan anchor `#catat-setoran`.
- Drawer mobile menggunakan Headless UI Dialog untuk focus trap, Escape, dan backdrop.
- Login tetap email/password Breeze. Profil tetap memakai form, validasi, upload, dan aksi existing.
- Kalkulator, tujuan, dompet, riwayat, dan berita mengikuti token bersama.
- Tidak menambahkan fitur PRD yang belum tersedia atau data pasar ilustratif.

Dokumen ini menggantikan arahan visual lama tema Malam pada claude/DESIGN.md untuk palet,
font, shell navigasi, dan komposisi halaman yang diubah. Aturan domain, validasi, serta
komponen tanggal dan kalender tetap berlaku.

## Validasi

- `npm ci --no-audit --no-fund`: berhasil, lockfile tetap.
- `npm run build`: berhasil.
- `npm run test:js`: 26 lulus.
- `git diff --check`: bersih.
- Kontras teks utama/sekunder/muted serta border input diperiksa pada kartu.
- PHP/Composer tidak tersedia dalam lingkungan pengerjaan. Pengujian Laravel,
  browser end-to-end, dan verifikasi visual desktop/mobile harus dijalankan di lingkungan dev.

## Tinjau di dev

1. Checkout branch perubahan dan jalankan `npm ci` lalu `npm run build`.
2. Dengan environment Laravel existing, jalankan `php artisan test` dan `composer run dev`.
3. Periksa dashboard kosong/terisi, pindah tujuan, kalender dan pencatatan setoran,
   kalkulator, profil, login/logout, serta drawer pada lebar 390px/768px/1440px.
4. Bandingkan hasil kalkulator dengan baseline. Tidak ada perubahan mesin hitung,
   controller, route, model, migrasi, atau dependensi pada perubahan ini.
5. Tinjau PR sebelum menggabungkannya ke branch aplikasi.
