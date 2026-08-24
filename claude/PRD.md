# PRD.md — FinGoal: Aplikasi Kalkulator & Perencanaan Tujuan Finansial

## 1. Ringkasan Produk

**FinGoal** adalah aplikasi manajemen keuangan pribadi berbasis web yang membantu pengguna menghitung berapa nominal yang harus disisihkan secara berkala untuk mencapai tujuan finansial tertentu (dana pensiun, membeli rumah, membeli kendaraan, dana darurat, dana pendidikan), lengkap dengan rekomendasi alokasi instrumen investasi (saham, reksa dana, obligasi/SBN, deposito, emas) agar target tersebut realistis dicapai. Aplikasi juga menyajikan berita & analisis keuangan terkini sebagai konteks pengambilan keputusan.

### Dua Pilar Produk

Produk ini punya dua bagian yang harus dibedakan tegas, karena keduanya berperilaku berbeda:

| | **Tujuan** (inti produk) | **Kalkulator** (pendukung) |
|---|---|---|
| Sifat | Tersimpan, punya progres, dipantau berbulan-bulan | Sekali pakai, hasil langsung, tidak wajib disimpan |
| Pertanyaan yang dijawab | "Berapa harus saya sisihkan agar target tercapai?" | "Kalau begini, hasilnya berapa?" |
| Menghasilkan | Objek yang punya siklus hidup: aktif → tercapai/arsip | Angka |
| Contoh | Dana pensiun, DP rumah, dana darurat | Simulasi cicilan KPR, proyeksi investasi |

**Tujuan adalah inti produk; kalkulator adalah alat pendukung.** Aturan pembeda: kalau hasilnya perlu dipantau dari waktu ke waktu, itu Tujuan. Kalau pengguna cuma ingin tahu angkanya sekarang, itu Kalkulator.

Keduanya dijembatani satu fitur: hasil kalkulator bisa langsung dijadikan Tujuan (FR-43). Tanpa jembatan itu, keduanya hanya jadi dua menu yang kebetulan bertetangga.

## 2. Latar Belakang & Masalah

- Banyak individu ingin punya rumah, dana pensiun, atau kendaraan, tapi tidak tahu berapa yang harus ditabung/diinvestasikan per bulan.
- Kalkulator finansial yang ada umumnya hanya menghitung cicilan pinjaman, bukan simulasi *goal-based saving & investing*.
- Minim edukasi terintegrasi tentang instrumen apa yang cocok untuk jangka waktu & profil risiko tertentu.

## 3. Tujuan Produk

1. Memungkinkan pengguna mendefinisikan tujuan finansial (nama, nominal target, jangka waktu, dana awal) dan mendapatkan hasil: nominal tabungan/investasi bulanan yang dibutuhkan.
2. Memberikan rekomendasi alokasi instrumen investasi berdasarkan jangka waktu & profil risiko pengguna.
3. Menyediakan dashboard pemantauan progres seluruh tujuan finansial pengguna.
4. Menyajikan berita finansial terkini yang relevan (kebijakan moneter, pasar saham, properti, investasi) untuk mendukung keputusan.

## 4. Target Pengguna

- **Persona utama:** pekerja usia 22–40 tahun, penghasilan tetap, ingin mulai merencanakan keuangan tapi belum familiar dengan istilah investasi kompleks.
- **Persona sekunder:** individu mendekati usia pensiun yang ingin memvalidasi kecukupan dana pensiun.

## 5. Lingkup (Scope)

### 5.1 Dalam Lingkup (MVP)
- Autentikasi pengguna (register/login/logout, reset password).
- Modul Tujuan Finansial: Dana Pensiun, Beli Rumah, Beli Kendaraan, Dana Darurat, Dana Pendidikan.
- Kalkulator utilitas: Pinjaman/KPR dan Investasi (§6.9).
- Engine rekomendasi alokasi instrumen investasi (rule-based, berdasarkan jangka waktu & toleransi risiko).
- Dashboard ringkasan: total target aktif, total tabungan diperlukan, progres tiap target.
- Manajemen "Tujuan Saya" (CRUD target finansial + pencatatan setoran + histori kalkulasi).

**Catatan arsitektur informasi:** halaman "Portofolio" yang sebelumnya direncanakan terpisah **digabung** ke halaman Tujuan — keduanya menampilkan hal yang sama, yaitu daftar tujuan berjalan beserta progresnya. Navigasi utama menjadi: Dashboard · Tujuan · Kalkulator · News · Pengaturan.
- Modul News: menampilkan berita finansial dari Currents API dengan filter kategori.
- Pengaturan profil dasar & preferensi (mata uang default: IDR, format angka).

### 5.2 Di Luar Lingkup (MVP)
- Integrasi rekening bank / open banking real-time.
- Eksekusi transaksi investasi sungguhan (beli saham/reksa dana langsung).
- Aplikasi mobile native (fokus web responsif dulu).
- Multi-user/family sharing.

### 5.3 Kandidat Fase Berikutnya (Post-MVP)
- Notifikasi pengingat menabung bulanan (email/reminder).
- Import data portofolio manual & tracking realisasi vs rencana.
- Kalkulator pajak & simulasi KPR/KPM (mengacu pola referensi desain awal).
- Multi-currency.

## 6. Fitur & Requirement Fungsional

### 6.1 Autentikasi & Profil
- FR-1: Pengguna dapat mendaftar dengan email & password.
- FR-2: Pengguna dapat login/logout, reset password via email.
- FR-3: Pengguna dapat mengatur profil (nama, foto, mata uang, profil risiko: konservatif/moderat/agresif).

### 6.2 Kalkulator Tujuan Finansial
- FR-4: Pengguna memilih jenis tujuan (Pensiun, Rumah, Kendaraan, Dana Darurat, Pendidikan, Kustom).
- FR-5: Pengguna memasukkan parameter: nama tujuan, nominal target, jangka waktu (tahun/bulan), dana awal yang sudah dimiliki, estimasi inflasi (default per kategori, dapat diubah), estimasi return investasi (default per kategori, dapat diubah).
- FR-6: Sistem menghitung nominal yang harus disisihkan per bulan menggunakan rumus *future value of annuity* (mempertimbangkan bunga majemuk/return investasi dan inflasi terhadap target nominal).
- FR-7: Sistem menampilkan ringkasan hasil: setoran bulanan dibutuhkan, total kontribusi vs total hasil investasi (proyeksi), grafik proyeksi pertumbuhan dana per tahun/bulan hingga target tercapai.
- FR-8: Sistem dapat menampilkan skenario "bagaimana jika" (ubah jangka waktu atau nominal setoran, hasil ter-update otomatis/real-time).
- FR-9: Pengguna dapat menyimpan hasil kalkulasi sebagai "Tujuan Saya" untuk dipantau progresnya di dashboard.

#### 6.2.1 Penentu Target (pre-calculator)
FR-5 mengasumsikan pengguna sudah tahu nominal targetnya. Untuk tiga kategori, asumsi itu tidak berlaku — justru menentukan targetnya yang sulit. Setiap kategori berikut punya langkah bantu sebelum masuk kalkulator utama:

- FR-20 (Dana Pensiun): pengguna memasukkan usia sekarang, usia pensiun target, dan **pengeluaran bulanan yang diinginkan saat pensiun (nilai hari ini)**. Sistem menurunkan nominal target dari kebutuhan bulanan tersebut × inflasi hingga usia pensiun × estimasi lama masa pensiun, dengan asumsi dana sisa tetap berkembang selama masa pensiun. Asumsi harapan hidup default dapat dikonfigurasi dan ditampilkan terbuka ke pengguna.
- FR-21 (Dana Darurat): pengguna memasukkan **pengeluaran bulanan saat ini** dan status tanggungan. Sistem menyarankan target 3× (lajang), 6× (menikah), atau 12× (berpenghasilan tidak tetap) pengeluaran bulanan. Kategori ini **tidak** memakai tanggal target; targetnya "secepat mungkin", sehingga output yang ditampilkan adalah *estimasi waktu tercapai* dari nominal setoran yang sanggup disisihkan (kebalikan dari kalkulator lain).
- FR-22 (Dana Pendidikan): target bukan satu nominal tunggal melainkan **rangkaian pencairan** (masuk SD, SMP, SMA, kuliah). Sistem menghitung kebutuhan tiap jenjang dengan inflasi pendidikan tersendiri (jauh di atas inflasi umum) dan menjumlahkan kebutuhan setoran bulanannya. MVP boleh menyederhanakan ke satu jenjang terpilih, tetapi model data harus sudah mengakomodasi banyak pencairan.

> Konsekuensi desain: ketiga kategori di atas tidak bisa memakai satu form generik yang sama. Rencanakan `GoalForm` sebagai shell + strategi per kategori sejak awal, bukan `if/else` yang ditempel belakangan.

### 6.3 Rekomendasi Instrumen Investasi
- FR-10: Berdasarkan jangka waktu tujuan & profil risiko pengguna, sistem menampilkan saran alokasi antar instrumen (contoh: jangka <2 tahun → dominan deposito/obligasi jangka pendek; 2–5 tahun → campuran obligasi & reksa dana campuran; >5 tahun → dominan saham/reksa dana saham).
- FR-11: Tiap instrumen yang direkomendasikan menampilkan: nama instrumen, persentase alokasi, estimasi return tahunan (rentang), level risiko, deskripsi singkat edukatif.
- FR-12: Sistem menampilkan disclaimer bahwa rekomendasi bersifat simulasi edukatif, bukan nasihat investasi personal/berlisensi.
- FR-23: **Alokasi yang direkomendasikan menentukan estimasi return yang dipakai kalkulator.** Sistem menghitung *blended expected return* = Σ (alokasi% × estimasi return instrumen) dan menjadikannya nilai default `estimasi return investasi` di FR-5. Pengguna tetap boleh menimpanya secara manual, tetapi bila ditimpa jauh di atas blended return, tampilkan peringatan bahwa asumsi tidak konsisten dengan alokasi yang dipilih.
- FR-24: Profil risiko dapat **di-override per tujuan**. Profil di level akun hanya menjadi nilai awal. Contoh: pengguna agresif tetap harus diarahkan konservatif untuk Dana Darurat.
- FR-25: Estimasi return yang ditampilkan harus dinyatakan **bruto atau neto pajak** secara eksplisit. Instrumen di Indonesia dikenai perlakuan pajak berbeda (bunga deposito PPh final, kupon obligasi/SBN PPh final, transaksi jual saham dikenai pungutan atas nilai transaksi, reksa dana tidak dipotong di level investor). MVP boleh memakai angka bruto, tetapi wajib memberi label "sebelum pajak" dan menyimpan field pajak di master instrumen agar bisa diaktifkan tanpa migrasi ulang.
- FR-26: Rekomendasi tidak boleh menyebut **nama produk, penerbit, atau kode efek spesifik** — hanya kelas aset. Ini membatasi paparan aplikasi terhadap ranah nasihat investasi berizin.
- FR-27: Master instrumen memiliki penanda **syariah/konvensional**, dan pengguna dapat memilih preferensi "hanya instrumen syariah". Rekomendasi menyesuaikan (sukuk/SBSN, reksa dana syariah, deposito syariah, saham indeks syariah).

### 6.4 Dashboard
- FR-13: Menampilkan ringkasan seluruh tujuan aktif pengguna beserta progres (persentase tercapai) dalam bentuk progress bar.
- FR-14: Menampilkan grafik gabungan proyeksi total kekayaan dari seluruh tujuan.
- FR-15: Menampilkan aktivitas terbaru (kalkulasi baru dibuat/diubah).

### 6.5 News
- FR-16: Sistem mengambil berita finansial terkini dari Currents API (kategori: business/finance, dengan query tambahan seperti "investasi", "pasar saham", "suku bunga", "properti" — disesuaikan dengan bahasa Indonesia jika tersedia).
- FR-17: Pengguna dapat memfilter berita per kategori (Kebijakan Moneter, Pasar Saham, Properti, Investasi, Tips Keuangan).
- FR-18: Berita di-cache di backend (Laravel scheduled job) untuk mengurangi pemanggilan API berulang & menghormati rate limit Currents API gratis.
- FR-28: **Kategori pada FR-17 tidak disediakan Currents API** dan harus diturunkan sendiri. Sistem melakukan klasifikasi saat ingest berdasarkan aturan kata kunci per kategori (mis. "suku bunga|BI Rate|inflasi" → Kebijakan Moneter). Aturan disimpan sebagai konfigurasi, bukan hardcode. Artikel yang tidak cocok kategori mana pun masuk "Lainnya" dan tidak dibuang.
- FR-29: Ingest berita melakukan **deduplikasi berdasarkan URL** — job berjalan tiap 30–60 menit dan akan mengembalikan artikel yang sama berulang kali.
- FR-30: Artikel cache lebih lama dari N hari (default 30) dipangkas otomatis agar tabel tidak tumbuh tanpa batas.
- ~~FR-31: Panel Indeks Pasar (IHSG dsb.)~~ — **dicoret dari MVP** (keputusan D-4). Currents API hanya menyediakan berita, bukan data harga, dan panel berisi angka statis lebih buruk daripada tidak ada panel di aplikasi keuangan. Dipindah ke Fase 3 bersama integrasi data pasar.

### 6.6 Pengaturan
- FR-19: Pengguna dapat mengubah preferensi format angka/mata uang tampilan dan profil risiko investasi.

### 6.7 Pencatatan Realisasi (prasyarat dashboard progres)
Tanpa bagian ini, `current_amount` tidak pernah berubah dan progress bar di FR-13 selamanya diam — dashboard hanya menampilkan hasil kalkulasi, bukan progres. Ini juga menghapus alasan pengguna untuk kembali, sehingga metrik retensi di §9 tidak akan tercapai.

- FR-32: Pengguna dapat mencatat setoran ke sebuah tujuan (nominal, tanggal, catatan opsional) secara manual.
- FR-33: Pengguna dapat melihat, mengedit, dan menghapus riwayat setoran per tujuan.
- FR-34: `current_amount` sebuah tujuan adalah hasil turunan dari dana awal + akumulasi setoran tercatat, bukan angka yang diedit langsung.
- FR-35: Dashboard membandingkan **rencana vs realisasi**: setoran seharusnya sampai bulan ini vs yang benar-benar tercatat, beserta selisihnya (tertinggal/di depan target).
- FR-36: Bila realisasi meleset, sistem menawarkan rekalkulasi: naikkan setoran, mundurkan tanggal target, atau turunkan nominal target.

### 6.8 Data Pribadi & Akun
- FR-37: Pengguna dapat **menghapus akun beserta seluruh datanya** secara mandiri (hak penghapusan, UU 27/2022 tentang Pelindungan Data Pribadi).
- FR-38: Pengguna dapat **mengekspor** seluruh data tujuan & kalkulasinya (CSV/JSON).
- FR-39: Tersedia halaman kebijakan privasi yang menjelaskan data apa yang disimpan, tujuannya, dan berapa lama disimpan; persetujuan diminta saat pendaftaran.
- FR-40: Verifikasi alamat email saat pendaftaran, dan pembatasan laju (rate limit) pada endpoint login, register, dan reset password.

### 6.9 Kalkulator Utilitas (pilar kedua)

Alat hitung sekali pakai, terpisah dari Tujuan. Daftar di bawah **bukan** salinan sidebar mockup awal — beberapa item di sana sengaja tidak dijadikan kalkulator terpisah, alasannya dijelaskan di bawah tabel.

- FR-41: **Kalkulator Pinjaman / KPR.** Input pokok pinjaman, suku bunga tahunan, tenor. Output: angsuran bulanan, total pembayaran, total bunga, dan grafik amortisasi (sisa pokok vs akumulasi bunga). Metode perhitungan (anuitas efektif) ditampilkan terbuka ke pengguna, bukan disembunyikan.
- FR-42: **Kalkulator Investasi.** Input dana awal, setoran bulanan, jangka waktu, estimasi return. Output: nilai akhir, total setoran, dan bagian yang berasal dari pengembangan. Ini kebalikan arah dari kalkulator tujuan — di sini setorannya diketahui dan hasilnya dicari.
- FR-43: **Jadikan Tujuan.** Hasil kalkulator dapat langsung disimpan menjadi Tujuan, dengan parameter yang sudah terisi. Ini jembatan antara kedua pilar dan jalur konversi paling alami dari pengguna iseng menjadi pengguna aktif.
- FR-44: Kalkulator utilitas **dapat diakses tanpa login**; menyimpan hasil (FR-43) barulah menuntut akun. Kalkulator adalah pintu masuk paling murah untuk menarik pengguna baru — mengunci di balik pendaftaran membuang keunggulan itu.
- FR-45: Riwayat kalkulasi cepat tersimpan bagi pengguna yang login, dapat dibuka kembali dan diubah parameternya.
- FR-46 *(Fase 2, bukan MVP)*: **Kalkulator Pajak (PPh 21).** Ditunda karena aturan pajak berubah tiap tahun dan menuntut pemeliharaan berkelanjutan — biaya perawatannya tidak sebanding untuk MVP, dan salah hitung pajak lebih berbahaya bagi kepercayaan pengguna daripada tidak menyediakannya sama sekali.

**Yang sengaja tidak dibuat sebagai kalkulator terpisah:**

| Item di mockup | Keputusan | Alasan |
|---|---|---|
| Tabungan | Digabung ke FR-42 | Matematikanya identik dengan kalkulator investasi, hanya berbeda asumsi return. Dua menu untuk satu rumus hanya membingungkan. |
| Pensiun | Tetap sebagai **Tujuan**, bukan kalkulator | Dana pensiun perlu dipantau bertahun-tahun. Menjadikannya alat sekali pakai membuang seluruh nilai pemantauan progres. |

**Reuse:** FR-41 dan FR-42 memakai keluarga rumus yang sama dengan kalkulator tujuan (anuitas). Keduanya dibangun di atas `GoalCalculatorService` yang sudah teruji, bukan sebagai mesin hitung terpisah — lihat CLAUDE.md §6.

## 7. Requirement Non-Fungsional

- **NFR-1 Performa:** Waktu hitung kalkulator < 200ms di sisi backend; halaman utama first load < 2.5s pada koneksi 4G.
- **NFR-2 Keamanan:** Password di-hash (bcrypt via Laravel), autentikasi berbasis token (Laravel Sanctum), validasi input di frontend & backend.
- **NFR-3 Skalabilitas:** Backend Laravel + PostgreSQL dirancang stateless agar mudah horizontal scaling; cache berita disimpan di tabel/DB atau Redis (opsional).
- **NFR-4 Ketersediaan Data Eksternal:** Jika Currents API gagal/limit habis, modul News menampilkan data cache terakhir + pesan fallback, tidak mem-block modul lain.
- **NFR-5 Aksesibilitas:** Kontras warna memenuhi WCAG AA (lihat DESIGN.md), navigasi keyboard didukung penuh.
- **NFR-6 Responsivitas:** Layout mendukung desktop, tablet, dan mobile (mobile: sidebar menjadi drawer/bottom nav).
- **NFR-7 Observability:** Logging error backend (Laravel log) dan tracking kegagalan pemanggilan API eksternal.
- **NFR-8 Instrumentasi Produk:** Seluruh metrik di §9 hanya bisa diukur bila ada pelacakan event. Minimal: `goal_calculation_started`, `goal_calculation_completed`, `goal_saved`, `contribution_recorded`, `news_opened`. Tanpa ini, target "penyelesaian alur kalkulator ≥60%" tidak dapat diverifikasi.
- **NFR-9 Kepatuhan & Legal:** Aplikasi memberi simulasi edukatif, bukan nasihat investasi. Konsekuensinya: tanpa nama produk/kode efek (FR-26), disclaimer permanen (bukan hanya sekali di onboarding), dan tidak ada klaim imbal hasil yang dijanjikan. Untuk data pribadi finansial, ikuti UU 27/2022 PDP: minimasi data, retensi jelas, hak akses/hapus (FR-37, FR-38).
- **NFR-10 Akurasi Perhitungan:** Mesin kalkulator wajib punya **test vector** (kumpulan kasus input→output yang sudah diverifikasi manual, termasuk kasus batas: return 0%, dana awal ≥ target, jangka waktu 1 bulan). Perhitungan uang tidak boleh memakai tipe floating point. Bila rumus diduplikasi di frontend untuk preview, kedua implementasi diuji terhadap test vector yang sama.
- **NFR-11 Cadangan Data:** Backup harian PostgreSQL dengan uji restore berkala. Data rencana keuangan pengguna tidak dapat direkonstruksi bila hilang.

## 8. User Stories (contoh prioritas MVP)

1. *Sebagai pengguna baru*, saya ingin mendaftar dan login agar data tujuan finansial saya tersimpan secara personal.
2. *Sebagai pengguna*, saya ingin memasukkan target dana pensiun (nominal, usia pensiun target) dan melihat berapa yang harus saya tabung/investasikan tiap bulan.
3. *Sebagai pengguna*, saya ingin melihat rekomendasi alokasi saham/obligasi/reksa dana agar target rumah saya dalam 5 tahun realistis.
4. *Sebagai pengguna*, saya ingin menyimpan beberapa tujuan sekaligus (rumah + kendaraan) dan melihat progres masing-masing di satu dashboard.
5. *Sebagai pengguna*, saya ingin membaca berita pasar saham/kebijakan suku bunga terbaru tanpa keluar dari aplikasi.

## 9. Metrik Keberhasilan (Success Metrics)

- Jumlah tujuan finansial yang berhasil dibuat & disimpan per pengguna aktif (target: ≥1.5 tujuan/user aktif bulanan).
- Retention 30 hari pengguna terdaftar (**target: ≥25%** — sebelumnya tidak berangka sehingga tidak bisa dinilai tercapai atau tidak).
- Tingkat penyelesaian alur kalkulator (dari mulai input hingga simpan hasil) ≥ 60%.
- Waktu rata-rata pemuatan modul News < 2 detik (dengan cache).
- **Metrik retensi utama yang sesungguhnya: persentase pengguna yang mencatat setoran (FR-32) minimal sekali dalam 30 hari setelah membuat tujuan (target: ≥30%).** Membuat kalkulasi hanyalah aktivitas sekali jalan; mencatat realisasi adalah alasan pengguna kembali tiap bulan.

> Catatan: seluruh metrik di atas bergantung pada NFR-8. Tanpa instrumentasi event, tidak satu pun dapat diukur.

## 10. Ketergantungan Eksternal

- **Currents API** (currentsapi.services) — tier gratis untuk berita finansial. Perlu strategi caching & rate-limit handling karena tier gratis punya kuota terbatas per hari.

## 11. Asumsi & Batasan

- Mata uang default IDR; belum mendukung multi-currency di MVP.
- Estimasi return/inflasi menggunakan nilai default per kategori instrumen yang dapat dikonfigurasi admin (bukan data real-time pasar).
- Rekomendasi instrumen bersifat rule-based/edukatif, bukan hasil algoritma robo-advisor bersertifikasi.

## 12. Roadmap Ringkas

MVP dipecah jadi tiga rilis. Alasannya: dana pensiun terlihat seperti fitur unggulan, tetapi matematikanya paling berat dan paling mudah salah — ia dibangun **setelah** mesin kalkulator terbukti benar lewat kategori sederhana.

| Rilis | Fokus | Kenapa di sini |
|---|---|---|
| **Rilis 1 — produk utuh terkecil** | Auth (+ verifikasi email, rate limit), kalkulator **2 kategori**: beli rumah & beli kendaraan, pencatatan setoran (FR-32..36), dashboard progres, hapus/ekspor akun (FR-37..38) | Matematika kedua kategori ini paling lurus: satu target nominal, satu tanggal. Sudah menjadi produk yang benar-benar bisa dipakai orang, dan sudah punya alasan pengguna kembali tiap bulan. |
| **Rilis 2 — kedalaman finansial** | Mesin rekomendasi instrumen + blended return (FR-10..12, FR-23..27), **kalkulator utilitas** Pinjaman/KPR & Investasi (FR-41..45), lalu kategori **dana darurat**, **dana pendidikan**, dan **dana pensiun** | Rekomendasi lebih dulu karena ia memberi makan estimasi return kalkulator. Kalkulator utilitas ditaruh di sini karena memakai keluarga rumus yang sama dan biayanya rendah setelah mesin kalkulator terbukti benar — sekaligus jadi pintu masuk pengguna baru lewat FR-44. Tiga kategori tujuan sisanya butuh penentu target tersendiri (FR-20..22). |
| **Rilis 3 — modul News** | Ingest + klasifikasi kategori (FR-16..18, FR-28..30), panel berita | Ditaruh terakhir karena bergantung pada pihak ketiga yang kualitasnya belum terverifikasi. **Gerbang mulai: uji kualitas hasil pencarian Currents dengan kata kunci nyata.** Bila hasilnya kurang, ganti sumber atau coret modul — jangan dipaksakan. |

**Setelah MVP**

| Fase | Fokus |
|---|---|
| Fase 2 | Kalkulator Pajak PPh 21 (FR-46), notifikasi pengingat setoran, perhitungan return neto pajak (mengaktifkan D-3) |
| Fase 3 | Multi-currency, family sharing, integrasi data pasar real-time (sekaligus membuka kembali Panel Indeks Pasar, lihat D-4) |

## 13. Keputusan yang Sudah Diambil

Disetujui 2026-08-23. Setiap keputusan disertai alasan agar bisa ditinjau ulang bila asumsinya berubah.

| # | Keputusan | Alasan |
|---|---|---|
| D-1 | **Inflasi menaikkan nominal target** (pendekatan A): `FV = target × (1+inflasi)^tahun`, lalu pakai return nominal. Bukan *real return*. | Nominal masa depan terlihat oleh pengguna sehingga lebih mudah dijelaskan ("rumah 800 juta hari ini ≈ 1,07 M dalam 5 tahun"). **Larangan keras: jangan sekaligus memotong return dengan inflasi** — itu perhitungan ganda. |
| D-2 | **Ordinary annuity** (setoran di akhir bulan). | Menghasilkan setoran sedikit lebih besar daripada *annuity due*, jadi lebih konservatif untuk alat perencanaan — lebih baik pengguna menabung sedikit berlebih daripada meleset. Asumsi ini ditampilkan terbuka di panel hasil. |
| D-3 | **MVP memakai return bruto**, diberi label eksplisit "sebelum pajak". Kolom pajak tetap disiapkan di master instrumen. | Menghindari kompleksitas tiga rezim pajak di rilis pertama, tanpa mengunci diri dari perhitungan neto nanti (tidak perlu migrasi ulang). |
| D-4 | **Panel Indeks Pasar dicoret dari MVP.** FR-31 ditutup. Dipindah ke Fase 3, menunggu penyedia data pasar. | Tidak ada sumber data — Currents hanya menyediakan berita. Membangun panel berisi angka statis lebih buruk daripada tidak ada panel sama sekali di aplikasi keuangan. |
| D-5 | **Sanctum bearer token.** `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN` dihapus dari konfigurasi. | Frontend Vite beda origin dari API; mode cookie menuntut CORS berkredensial, kesamaan domain induk, dan penanganan CSRF — tidak sepadan untuk SPA ini. |
| D-6 | **Alokasi di-snapshot per kalkulasi.** `goal_recommended_allocations` menempel ke `goal_calculation_id`. | Saat aturan alokasi diubah admin, hasil lama tetap bisa direproduksi dan dijelaskan ke pengguna. |
| D-7 | Angka default return/inflasi disimpan **beserta `rates_as_of` dan `rates_source`**, ditinjau setahun sekali. Nilai awal wajib diverifikasi ke sumber resmi saat seeding, bukan diambil dari dokumen ini. | Angka ini langsung membentuk hasil yang dilihat pengguna. Angka tanpa sumber dan tanpa tanggal berlaku adalah utang teknis yang diam-diam menyesatkan. Sumber acuan: BPS (inflasi umum & pendidikan), Bank Indonesia (BI Rate), LPS (bunga penjaminan deposito), Kemenkeu DJPPR (kupon SBN ritel), OJK/BEI (kinerja jangka panjang indeks). |
