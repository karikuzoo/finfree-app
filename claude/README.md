# Arus

Aplikasi web manajemen keuangan pribadi dengan **kalkulator tujuan finansial**: hitung berapa yang harus disisihkan tiap bulan untuk mencapai target (dana pensiun, rumah, kendaraan, dana darurat, pendidikan), lengkap dengan rekomendasi alokasi instrumen investasi dan pemantauan progres.

Produk ini punya dua pilar. **Tujuan** adalah intinya — target tersimpan yang punya progres dan dipantau berbulan-bulan. **Kalkulator** adalah pendukungnya — alat hitung sekali pakai untuk pertanyaan cepat seperti simulasi cicilan KPR. Keduanya dijembatani tombol "Jadikan Tujuan".

> **Status (25 Sep 2026): kalkulator, tujuan, dashboard, dan lapisan uang sudah berjalan.** Di atas skeleton **Laravel Breeze + Inertia.js + React** sudah dibangun kalkulator tujuan, daftar & pengelolaan tujuan, dashboard dengan kalender dan pengingat, serta pencatat keuangan (rekening, transaksi, investasi, utang, rencana menabung, cadangan data — PRD §6.13–§6.18). Modul berita juga sudah berjalan, dari NewsData.io (PRD D-16). Rinciannya di bagian "Menjalankan Project" di bawah.

Sejak September 2026 Arus juga **mencatat uang pengguna**: rekening & aset, transaksi, investasi, dan utang — lalu dana tiap tujuan ditandai dari saldo rekening bank/tunai, bukan disetor terpisah. Semua catatan itu diisi **manual** oleh pengguna. Arus tidak terhubung ke rekening bank, tidak mengambil harga pasar, tidak membeli/menjual instrumen apa pun, dan tidak memberi nasihat investasi personal; kalkulator dan rekomendasi alokasinya tetap simulasi edukatif.

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
| KPR: bunga tetap / berjenjang / mengambang + cek kesehatan cicilan | 2 | FR-86 |
| Kalkulator Investasi — proyeksi nilai akhir dari setoran rutin | 2 | FR-42 |
| **Jadikan Tujuan** — simpan hasil kalkulator jadi target yang dipantau | 2 | FR-43 |
| Dapat diakses **tanpa login**; menyimpan hasil baru butuh akun | 2 | FR-44 |
| Riwayat kalkulasi cepat, bisa dibuka & diubah lagi (belum dibangun) | 2 | FR-45 |
| Kalkulator Pajak PPh 21 | Fase 2 | FR-46 |

### 📈 Dashboard & Tujuan

| Fitur | Rilis | FR |
|---|---|---|
| Ringkasan seluruh tujuan aktif dengan progress bar | 1 | FR-13 |
| Grafik pertumbuhan kekayaan (kini dibangun dari riwayat transaksi) | 1 | FR-14 |
| Aktivitas terbaru (kalkulasi dibuat/diubah) | 1 | FR-15 |
| Halaman Tujuan — daftar tujuan berjalan beserta progres masing-masing | 1 | — |
| Kekayaan bersih, total aset, dan total utang di atas dashboard | Fase 4 | FR-79..FR-82 |
| Arus kas bulan yang sedang dilihat, komposisi aset, lima transaksi terbaru | Fase 4 | FR-79..FR-82 |

### 🏦 Rekening, Transaksi & Utang

Nilai **Fase 1–5** di tabel-tabel berikut merujuk ke tabel perluasan pencatat keuangan di [PRD.md](PRD.md) §12 — bukan "Fase 2" pasca-MVP.

| Fitur | Rilis | FR |
|---|---|---|
| Rekening & aset: bank, tunai, saham, reksa dana, emas — saldo dihitung dari saldo awal + riwayat | Fase 1 | FR-63 |
| Lima jenis transaksi: pemasukan, pengeluaran, transfer, penyesuaian nilai, pembayaran pokok utang | Fase 1 | FR-64 |
| Saldo tidak boleh minus, transfer tidak menciptakan uang, tanggal tidak boleh di masa depan | Fase 1 | FR-64..FR-69 |
| Kekayaan bersih dan arus kas bulanan (transfer & penyesuaian nilai di luar arus kas) | Fase 1 | FR-64..FR-69 |
| Investasi — rekening non-likuid beserta tanggal penilaian terakhirnya | Fase 1 | FR-71 |
| Perbarui nilai aset; selisihnya dicatat sebagai transaksi penyesuaian | Fase 1 | FR-72 |
| Utang & cicilan — sisa pokok, rencana pokok bulanan, status lunas dari riwayat pembayaran | Fase 2 | FR-70 |

### 🎯 Dana Tujuan & Rencana Menabung

| Fitur | Rilis | FR |
|---|---|---|
| Dana tujuan **ditandai** dari saldo rekening bank/tunai — uangnya tidak dipindahkan | Fase 3 | FR-73 |
| Prioritas tujuan: tinggi, sedang, rendah | Fase 3 | FR-73 |
| Anggaran bulanan: perkiraan penghasilan, perkiraan pengeluaran, dana cadangan | Fase 3 | FR-74 |
| Rencana menabung — kemampuan menabung dibagi menurut prioritas lalu tenggat, kekurangan ditampilkan | Fase 3 | FR-74..FR-78 |
| **Sudah saya sisihkan** — menambah dana tujuan sebesar sisa rencana bulan ini, atau "Jumlah lain…" | Fase 5 | FR-85 |

### 💰 Pencatatan Realisasi

| Fitur | Rilis | FR |
|---|---|---|
| ~~Catat setoran ke sebuah tujuan (nominal, tanggal, catatan)~~ — dipensiunkan | 1 | FR-32 |
| ~~Lihat, edit, hapus riwayat setoran~~ — dipensiunkan | 1 | FR-33 |
| ~~Progres dihitung dari dana awal + akumulasi setoran tercatat~~ — kini dari dana yang ditandai | 1 | FR-34 |
| Perbandingan **rencana vs realisasi** — tertinggal atau di depan target | 1 | FR-35 |
| Tawaran rekalkulasi saat realisasi meleset dari rencana — naikkan setoran, mundurkan tanggal, atau turunkan target | 1 | FR-36 |

Pencatatan setoran lewat kalender dipensiunkan pada Fase 3 (keputusan D-10 di PRD §13): begitu rekening dan transaksi ada, setoran ke tujuan menjadi tempat kedua untuk mencatat uang yang sama. Yang kini membuat dashboard hidup adalah transaksi bulanan dan tombol "Sudah saya sisihkan". Riwayat setoran lama tetap tersimpan dan nilainya sudah dipindahkan ke dana tujuan.

### 🗄️ Data & Riwayat

| Fitur | Rilis | FR |
|---|---|---|
| Cadangan seluruh data keuangan sebagai berkas JSON bertanggal | Fase 5 | FR-83 |
| Pulihkan dari berkas cadangan — mengganti data lama, ditolak utuh bila tidak sah | Fase 5 | FR-84 |
| Riwayat memuat peristiwa tujuan dan transaksi sekaligus, digabung saat dibaca | Fase 5 | — |
| Ekspor Excel memuat sheet Transaksi (menggantikan sheet Setoran) | Fase 3 | FR-38 |

### 📰 Berita Finansial

| Fitur | Rilis | FR |
|---|---|---|
| Berita keuangan berbahasa Indonesia dari NewsData.io (D-16) | 3 | FR-16 |
| Filter kategori: Kebijakan Moneter, Pasar Saham, Properti, Investasi, Tips Keuangan | 3 | FR-17, FR-28 |
| Cache di backend via scheduled job, hemat kuota API | 3 | FR-18 |
| Deduplikasi artikel & pemangkasan cache lama | 3 | FR-29, FR-30 |
| Fallback data tersimpan bila sumber gagal (penanda `stale`) | 3 | NFR-4 |

Modul ini punya gerbang: kualitas hasil sumbernya diuji lebih dulu dengan kata kunci nyata. **Currents API tidak lolos** (tidak ada bahasa Indonesia; "IHSG" dan "suku bunga" → 0 artikel), jadi sumbernya diganti ke **NewsData.io** — lihat [PRD](PRD.md) D-16. Sudah dibangun per 27 Sep 2026.

### Di Luar Lingkup

Integrasi rekening bank (rekening dan transaksi dicatat manual), eksekusi transaksi investasi sungguhan, aplikasi mobile native, dan berbagi antar pengguna **tidak** termasuk MVP. Panel Indeks Pasar (IHSG) dicoret karena tidak ada sumber data — lihat keputusan D-4 di PRD §13.

---

## Tech Stack

**Full-stack** Laravel 12 + Inertia.js v2 (React 18) — satu aplikasi, bukan SPA+API terpisah. Auth via Laravel Breeze (session/cookie), bukan token.
**Styling** Tailwind CSS, @headlessui/react, Recharts (charting), lucide-react (ikon, belum terpasang)
**Database** PostgreSQL (`.env.example` sudah `pgsql`, lihat CLAUDE.md §8)
**Eksternal** NewsData.io untuk modul Berita (menggantikan Currents API, PRD D-16)
**Testing** PHPUnit di PostgreSQL (`php artisan test`), `node --test` untuk fungsi utilitas JS (`npm run test:js`), Vitest + Testing Library untuk komponen React (`npm run test:ui`)

Tema visual: **Arus** — latar gelap `#101719` dengan aksen mint `#98EDCE`, dark-first. Lihat [../docs/ARUS-REDESIGN.md](../docs/ARUS-REDESIGN.md); palet "Malam" di [DESIGN.md](DESIGN.md) §2 tinggal referensi historis.

---

## Dokumentasi

| Dokumen | Isi |
|---|---|
| [PRD.md](PRD.md) | Requirement produk, user stories, metrik sukses, roadmap, keputusan yang sudah diambil |
| [DESIGN.md](DESIGN.md) | Design system: palet, tipografi, komponen, state, aksesibilitas |
| [CLAUDE.md](CLAUDE.md) | Konteks teknis: struktur repo, skema DB, rumus kalkulator, kontrak props Inertia, konvensi |
| [CONTRIBUTING.md](CONTRIBUTING.md) | Cara kerja tim: pembagian tugas, alur git, definition of done |
| [ORIENTASI.md](ORIENTASI.md) | **Mulai dari sini kalau baru pertama membaca kode.** Peta folder, alur satu halaman dari URL sampai layar, urutan baca, dan pembedahan berkas baris per baris |

---

## Menjalankan Project

Lihat [CONTRIBUTING.md](CONTRIBUTING.md) §9 untuk urutan setup lengkap — termasuk dua database PostgreSQL yang perlu dibuat, `php artisan storage:link`, dan akun demo yang dibuat oleh seeder.

Sebelum commit, jalankan ketiga test — backend, fungsi utilitas JS, dan komponen React:

```bash
php artisan test
npm run test:js
npm run test:ui
```

**Yang sudah bisa dipakai hari ini:** halaman depan, autentikasi lengkap (daftar, masuk, verifikasi email, reset kata sandi, batas laju), profil beserta foto dan data identitas, kalkulator tujuan publik dengan grafik proyeksi, dashboard (kekayaan bersih, arus kas, komposisi aset, progres tujuan) beserta kalender aktivitas dan pengingat, pembuatan, pengubahan, penghapusan, dan ekspor Excel tujuan finansial, Rekening & aset, Transaksi, Investasi, Utang & cicilan, Rencana menabung dengan tombol "Sudah saya sisihkan", Riwayat, cadangan & pemulihan data, halaman Berita dari NewsData.io, dan halaman error khusus.

**Rekalkulasi saat realisasi meleset (FR-36) dibangun 29 Sep 2026** — tawarannya ada di kartu tujuan yang tertinggal (halaman Tujuan). Pencatatan setoran lewat kalender sudah dipensiunkan (PRD D-10). Peta lengkapnya ada di [PRD.md](PRD.md) §12.
