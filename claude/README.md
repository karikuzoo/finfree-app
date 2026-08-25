# FinGoal

Aplikasi web manajemen keuangan pribadi dengan **kalkulator tujuan finansial**: hitung berapa yang harus disisihkan tiap bulan untuk mencapai target (dana pensiun, rumah, kendaraan, dana darurat, pendidikan), lengkap dengan rekomendasi alokasi instrumen investasi dan pemantauan progres.

Produk ini punya dua pilar. **Tujuan** adalah intinya — target tersimpan yang punya progres dan dipantau berbulan-bulan. **Kalkulator** adalah pendukungnya — alat hitung sekali pakai untuk pertanyaan cepat seperti simulasi cicilan KPR. Keduanya dijembatani tombol "Jadikan Tujuan".

> **Status: scaffolding awal selesai, fitur FinGoal belum dimulai.** Dokumen produk, desain, dan konteks teknis sudah selesai. Repo sudah berisi skeleton **Laravel Breeze + Inertia.js + React** (auth register/login/logout/profil sudah berfungsi) — ini titik mulai implementasi, bukan project kosong. Belum ada satu pun fitur FinGoal (kalkulator, tujuan, dashboard, news) yang dikerjakan.

FinGoal adalah alat **simulasi dan perencanaan**, bukan aplikasi transaksi. Aplikasi ini tidak membeli/menjual instrumen apa pun, tidak terhubung ke rekening bank, dan tidak memberi nasihat investasi personal.

---

## Daftar Fitur

Kolom **Rilis** mengacu pada roadmap di [PRD.md](PRD.md) §12. Kode FR merujuk ke requirement fungsional di PRD §6.

### 🔐 Autentikasi & Akun

| Fitur | Rilis | FR |
|---|---|---|
| Daftar dengan email & password, verifikasi email | 1 | FR-1, FR-40 |
| Login, logout, reset password | 1 | FR-2 |
| Profil pengguna: nama, foto, mata uang, profil risiko | 1 | FR-3 |
| Preferensi format angka & profil risiko investasi | 1 | FR-19 |
| Preferensi "hanya instrumen syariah" | 2 | FR-27 |
| Ekspor seluruh data tujuan & kalkulasi (CSV/JSON) | 1 | FR-38 |
| Hapus akun beserta seluruh data | 1 | FR-37 |

### 🧮 Kalkulator Tujuan Finansial

| Fitur | Rilis | FR |
|---|---|---|
| Enam jenis tujuan: pensiun, rumah, kendaraan, dana darurat, pendidikan, kustom | 1–2 | FR-4 |
| Input parameter dengan nilai default per kategori (inflasi, return) | 1 | FR-5 |
| Hitung setoran bulanan yang dibutuhkan (*future value of annuity*) | 1 | FR-6 |
| Ringkasan hasil: setoran bulanan, total kontribusi vs hasil investasi | 1 | FR-7 |
| Grafik proyeksi pertumbuhan dana sampai target tercapai | 1 | FR-7 |
| Skenario "bagaimana jika" — geser jangka waktu / nominal, hasil berubah langsung | 1 | FR-8 |
| Simpan hasil sebagai tujuan yang dipantau | 1 | FR-9 |
| **Penentu target dana pensiun** dari pengeluaran bulanan yang diinginkan | 2 | FR-20 |
| **Penentu target dana darurat** dari pengeluaran bulanan × 3/6/12 | 2 | FR-21 |
| **Dana pendidikan berjenjang** (SD/SMP/SMA/kuliah) dengan inflasi pendidikan | 2 | FR-22 |

Kalkulator **beli rumah** dan **beli kendaraan** dikerjakan lebih dulu karena matematikanya paling lurus — satu target nominal, satu tanggal. Tiga kategori lain butuh langkah penentu target tersendiri.

### 📊 Rekomendasi Alokasi Investasi

| Fitur | Rilis | FR |
|---|---|---|
| Saran alokasi antar instrumen berdasarkan jangka waktu & profil risiko | 2 | FR-10 |
| Detail tiap instrumen: alokasi %, estimasi return, level risiko, penjelasan | 2 | FR-11 |
| **Blended return** dari alokasi menjadi default estimasi return kalkulator | 2 | FR-23 |
| Profil risiko dapat di-override per tujuan | 2 | FR-24 |
| Label eksplisit bruto/neto pajak pada estimasi return | 2 | FR-25 |
| Hanya kelas aset — tanpa nama produk atau kode efek | 2 | FR-26 |
| Varian instrumen syariah (sukuk, reksa dana syariah, deposito syariah) | 2 | FR-27 |
| Disclaimer permanen: simulasi edukatif, bukan nasihat investasi | 2 | FR-12 |

Instrumen yang dicakup: saham, reksa dana, obligasi/SBN, deposito, emas.

### 🧾 Kalkulator Utilitas

Alat hitung sekali pakai, terpisah dari Tujuan — jawab "kalau begini hasilnya berapa?" tanpa harus membuat target yang dipantau.

| Fitur | Rilis | FR |
|---|---|---|
| Kalkulator Pinjaman/KPR — angsuran, total bunga, grafik amortisasi | 2 | FR-41 |
| Kalkulator Investasi — proyeksi nilai akhir dari setoran rutin | 2 | FR-42 |
| **Jadikan Tujuan** — simpan hasil kalkulator jadi target yang dipantau | 2 | FR-43 |
| Dapat diakses **tanpa login**; menyimpan hasil baru butuh akun | 2 | FR-44 |
| Riwayat kalkulasi cepat, bisa dibuka & diubah lagi | 2 | FR-45 |
| Kalkulator Pajak PPh 21 | Fase 2 | FR-46 |

### 📈 Dashboard & Tujuan

| Fitur | Rilis | FR |
|---|---|---|
| Ringkasan seluruh tujuan aktif dengan progress bar | 1 | FR-13 |
| Grafik gabungan proyeksi total kekayaan | 1 | FR-14 |
| Aktivitas terbaru (kalkulasi dibuat/diubah) | 1 | FR-15 |
| Halaman Tujuan — daftar tujuan berjalan beserta progres masing-masing | 1 | — |

### 💰 Pencatatan Realisasi

| Fitur | Rilis | FR |
|---|---|---|
| Catat setoran ke sebuah tujuan (nominal, tanggal, catatan) | 1 | FR-32 |
| Lihat, edit, hapus riwayat setoran | 1 | FR-33 |
| Progres dihitung dari dana awal + akumulasi setoran tercatat | 1 | FR-34 |
| Perbandingan **rencana vs realisasi** — tertinggal atau di depan target | 1 | FR-35 |
| Tawaran rekalkulasi saat realisasi meleset dari rencana | 1 | FR-36 |

Bagian ini yang membuat dashboard hidup. Menghitung setoran adalah aktivitas sekali seumur tujuan; mencatat realisasi adalah yang bulanan.

### 📰 Berita Finansial

| Fitur | Rilis | FR |
|---|---|---|
| Berita finansial terkini dari Currents API | 3 | FR-16 |
| Filter kategori: Kebijakan Moneter, Pasar Saham, Properti, Investasi, Tips Keuangan | 3 | FR-17, FR-28 |
| Cache di backend via scheduled job, hemat kuota API | 3 | FR-18 |
| Deduplikasi artikel & pemangkasan cache lama | 3 | FR-29, FR-30 |
| Fallback data tersimpan bila sumber gagal (penanda `stale`) | 3 | NFR-4 |

Modul ini ditaruh terakhir dan punya gerbang: kualitas hasil pencarian Currents diuji lebih dulu dengan kata kunci nyata. Bila hasilnya kurang, sumber diganti atau modul dicoret.

### Di Luar Lingkup

Integrasi rekening bank, eksekusi transaksi investasi sungguhan, aplikasi mobile native, dan berbagi antar pengguna **tidak** termasuk MVP. Panel Indeks Pasar (IHSG) dicoret karena tidak ada sumber data — lihat keputusan D-4 di PRD §13.

---

## Tech Stack

**Full-stack** Laravel 12 + Inertia.js v2 (React 18) — satu aplikasi, bukan SPA+API terpisah. Auth via Laravel Breeze (session/cookie), bukan token.
**Styling** Tailwind CSS, @headlessui/react, Recharts (charting, belum terpasang), lucide-react (ikon, belum terpasang)
**Database** PostgreSQL (repo saat ini masih default `sqlite` bawaan Breeze, lihat CLAUDE.md §8)
**Eksternal** Currents API untuk modul News

Tema visual: **"Malam"** — near-black `#0B0C0B` dengan aksen lime `#CFF04A`, dark-first. Lihat [DESIGN.md](DESIGN.md).

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [PRD.md](PRD.md) | Requirement produk, user stories, metrik sukses, roadmap, keputusan yang sudah diambil |
| [DESIGN.md](DESIGN.md) | Design system: palet, tipografi, komponen, state, aksesibilitas |
| [CLAUDE.md](CLAUDE.md) | Konteks teknis: struktur repo, skema DB, rumus kalkulator, kontrak props Inertia, konvensi |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Cara kerja tim: pembagian tugas, alur git, definition of done |

---

## Menjalankan Project

Skeleton Breeze+Inertia sudah bisa dijalankan sekarang lewat satu perintah (`composer run dev`) — lihat [CLAUDE.md](CLAUDE.md) §9 untuk urutan setup lengkap termasuk konfigurasi `.env` yang masih perlu diganti dari default Breeze (database, nama app, kredensial Currents API). Menjalankannya hari ini akan menampilkan halaman Breeze bawaan (Welcome, Login, Register, Dashboard kosong) — fitur FinGoal sendiri belum ada di dalamnya.
