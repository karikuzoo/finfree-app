# PRD.md — Arus: Aplikasi Kalkulator & Perencanaan Tujuan Finansial

## 1. Ringkasan Produk

**Arus** adalah aplikasi manajemen keuangan pribadi berbasis web yang membantu pengguna menghitung berapa nominal yang harus disisihkan secara berkala untuk mencapai tujuan finansial tertentu (dana pensiun, membeli rumah, membeli kendaraan, dana darurat, dana pendidikan), lengkap dengan rekomendasi alokasi instrumen investasi (saham, reksa dana, obligasi/SBN, deposito, emas) agar target tersebut realistis dicapai. Aplikasi juga menyajikan berita & analisis keuangan terkini sebagai konteks pengambilan keputusan.

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

### Lapisan Uang (sejak 24–25 Sep 2026)

Di bawah kedua pilar itu kini ada lapisan ketiga: **pencatat keuangan**. Pengguna mencatat rekening & aset, transaksi, investasi, dan utangnya sendiri (§6.13–§6.18), dan dana sebuah Tujuan tidak lagi disetor ke tujuan itu melainkan **ditandai** dari saldo rekening bank/tunai (FR-73, keputusan D-11). Seluruh pencatatannya manual: Arus tetap tidak terhubung ke rekening bank, tidak membeli/menjual instrumen apa pun, dan tidak memberi nasihat investasi personal.

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
- Manajemen "Tujuan Saya" (CRUD target finansial + pencatatan setoran + histori kalkulasi). *Pencatatan setoran sudah dipensiunkan dan digantikan alokasi dana tujuan — lihat §6.7 dan D-10.*

**Catatan arsitektur informasi:** halaman "Portofolio" yang sebelumnya direncanakan terpisah **digabung** ke halaman Tujuan — keduanya menampilkan hal yang sama, yaitu daftar tujuan berjalan beserta progresnya. Navigasi utama menjadi: Dashboard · Tujuan · Kalkulator · News · Pengaturan.
- Modul News: menampilkan berita finansial dari Currents API dengan filter kategori.
- Pengaturan profil dasar & preferensi (mata uang default: IDR, format angka).

### 5.2 Di Luar Lingkup (MVP)
- Integrasi rekening bank / open banking real-time. *(Tetap berlaku setelah lapisan uang ada: rekening dan transaksi di §6.13 diisi manual oleh pengguna.)*
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
- FR-4: Tujuan dikenali dari **namanya sendiri**, bebas diketik pengguna. Tidak ada pilihan jenis/kategori di form.

  Semula ada enam kategori tetap (Pensiun, Rumah, Kendaraan, Dana Darurat, Pendidikan, Kustom) yang harus dipilih lebih dulu. Kategori itu dihapus dari antarmuka karena memaksa pengguna memilih kotak sebelum boleh menulis apa yang sebenarnya ia inginkan — sementara **tidak satu pun logika bergantung padanya**: alokasi instrumen dihitung dari profil risiko dan jangka waktu (`InvestmentAllocationService`), bukan dari jenis tujuan.

  Kolom `financial_goals.type` tetap ada di database dan diisi `custom` untuk semua tujuan baru. Menghapus kolomnya menuntut migrasi yang membuang jenis pada data lama tanpa memberi manfaat apa pun.
- FR-5: Pengguna memasukkan parameter: nama tujuan, nominal target, jangka waktu, dana awal yang sudah dimiliki, estimasi inflasi, dan estimasi return investasi. Kedua estimasi sudah terisi nilai wajar yang berlaku umum dan dapat diubah — bukan lagi default per kategori, karena kategorinya sudah tidak ada.
- FR-5a: Jangka waktu ditentukan lewat **tiga mode** yang semuanya bermuara pada satu `target_date`:
  - **Jangka waktu** — pengguna mengisi berapa bulan.
  - **Setoran harian** — pengguna mengisi nominal harian, lalu jangka waktunya dihitung mundur (`solveMonths()`).
  - **Tanpa tenggat** — tujuan dikumpulkan terus-menerus, `target_date` bernilai NULL.

  Mode ketiga menggantikan aturan lama "hanya Dana Darurat yang boleh tanpa tanggal". Setelah kategori dihapus, aturan itu kehilangan pegangan, dan tanpa mode ini kemampuannya akan ikut hilang diam-diam — padahal kolomnya nullable dan Dashboard sudah menanganinya (`days_remaining` dan `on_track` bernilai null). Kini tujuan apa pun boleh tanpa tenggat, bukan hanya dana darurat.
- FR-6: Sistem menghitung nominal yang harus disisihkan per bulan menggunakan rumus *future value of annuity* (mempertimbangkan bunga majemuk/return investasi dan inflasi terhadap target nominal).
- FR-7: Sistem menampilkan ringkasan hasil: setoran bulanan dibutuhkan, total kontribusi vs total hasil investasi (proyeksi), grafik proyeksi pertumbuhan dana per tahun/bulan hingga target tercapai.
- FR-8: Sistem dapat menampilkan skenario "bagaimana jika" (ubah jangka waktu atau nominal setoran, hasil ter-update otomatis/real-time).
- FR-9: Pengguna dapat menyimpan hasil kalkulasi sebagai "Tujuan Saya" untuk dipantau progresnya di dashboard.

#### 6.2.1 Penentu Target (pre-calculator)
FR-5 mengasumsikan pengguna sudah tahu nominal targetnya. Untuk tiga kategori, asumsi itu tidak berlaku — justru menentukan targetnya yang sulit. Setiap kategori berikut punya langkah bantu sebelum masuk kalkulator utama:

- FR-20 (Dana Pensiun): pengguna memasukkan usia sekarang, usia pensiun target, dan **pengeluaran bulanan yang diinginkan saat pensiun (nilai hari ini)**. Sistem menurunkan nominal target dari kebutuhan bulanan tersebut × inflasi hingga usia pensiun × estimasi lama masa pensiun, dengan asumsi dana sisa tetap berkembang selama masa pensiun. Asumsi harapan hidup default dapat dikonfigurasi dan ditampilkan terbuka ke pengguna.
- FR-21 (Dana Darurat): pengguna memasukkan **pengeluaran bulanan saat ini** dan status tanggungan. Sistem menyarankan target 3× (lajang), 6× (menikah), atau 12× (berpenghasilan tidak tetap) pengeluaran bulanan. Kategori ini **tidak** memakai tanggal target; targetnya "secepat mungkin", sehingga output yang ditampilkan adalah *estimasi waktu tercapai* dari nominal setoran yang sanggup disisihkan (kebalikan dari kalkulator lain).
- FR-22 (Dana Pendidikan): target bukan satu nominal tunggal melainkan **rangkaian pencairan** (masuk SD, SMP, SMA, kuliah). Sistem menghitung kebutuhan tiap jenjang dengan inflasi pendidikan tersendiri (jauh di atas inflasi umum) dan menjumlahkan kebutuhan setoran bulanannya. MVP boleh menyederhanakan ke satu jenjang terpilih, tetapi model data harus sudah mengakomodasi banyak pencairan.

> **Pertanyaan terbuka setelah kategori dihapus (FR-4).** Ketiga penentu target di atas dulu dipicu oleh jenis tujuan yang dipilih pengguna. Tanpa pilihan itu, aplikasi tidak lagi tahu kapan harus menawarkannya, dan ketiganya perlu jalan masuk baru sebelum dibangun di Rilis 2.
>
> Arah yang paling menjanjikan: jadikan ketiganya **alat bantu opsional** yang dipanggil pengguna dari dalam form — sebuah tautan bernada "Belum tahu nominal targetnya? Hitung dulu" yang membuka penentu yang sesuai, lalu mengisikan hasilnya ke kolom nominal target. Dengan begitu penentu target menjadi pilihan pengguna, bukan konsekuensi kategori yang terlanjur dipilih di awal.
>
> Konsekuensi teknis lamanya tetap berlaku: ketiga penentu itu tidak bisa memakai satu form generik yang sama. Rencanakan sebagai shell + strategi sejak awal, bukan `if/else` yang ditempel belakangan. Putuskan jalan masuknya sebelum FR-20..22 mulai dikerjakan.

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
> **Sumber berita diganti dari Currents API ke NewsData.io (27 Sep 2026, keputusan D-16).** Currents tidak lolos gerbang Rilis 3: tidak mendukung bahasa Indonesia, dan kata kunci berbahasa Indonesia tidak menghasilkan satu artikel pun. Teks FR di bawah sudah disesuaikan; penyebutan "Currents" yang tersisa di dokumen lain adalah jejak rancangan.

- FR-16: Sistem mengambil berita finansial terkini dari **NewsData.io** (`country=id`, `language=id`), dengan **satu kueri kata kunci per kategori FR-17** (mis. "IHSG" untuk Pasar Saham, "BI Rate"/"suku bunga" untuk Kebijakan Moneter), dibatasi ke media keuangan dan berita utama. Kata kunci gabungan dalam satu kueri terbukti menurunkan ketepatan (D-16).
- FR-17: Pengguna dapat memfilter berita per kategori (Kebijakan Moneter, Pasar Saham, Properti, Investasi, Tips Keuangan).
- FR-18: Berita di-cache di backend (Laravel scheduled job) untuk mengurangi pemanggilan API berulang & menghormati kuota gratis NewsData.io (200 kredit/hari; satu kueri per kategori per jam memakai 120).
- FR-28: **Kategori pada FR-17 tidak disediakan sumber** dan harus diturunkan sendiri. Sistem melakukan klasifikasi saat ingest berdasarkan aturan kata kunci per kategori (mis. "suku bunga|BI Rate|inflasi" → Kebijakan Moneter). Aturan disimpan sebagai konfigurasi, bukan hardcode (`config/news.php`). ~~Artikel yang tidak cocok kategori mana pun masuk "Lainnya" dan tidak dibuang.~~ **Diubah (D-16):** pola dicocokkan ke judul + ringkasan; kategori kueri yang mengambilnya diutamakan bila polanya cocok, lalu kategori pertama lain yang cocok, lalu artikelnya **dibuang** bila tidak ada yang cocok. Pada pengambilan sungguhan pertama, artikel yang kata kuncinya hanya ada di isi berita hampir selalu tidak relevan (Posyandu lewat "perumahan", koin perak Romawi lewat "menabung"), dan kategori "Lainnya" akan berisi justru noise itu.
- FR-29: Ingest berita melakukan **deduplikasi berdasarkan URL** — job berjalan tiap 30–60 menit dan akan mengembalikan artikel yang sama berulang kali.
- FR-30: Artikel cache lebih lama dari N hari (default 30) dipangkas otomatis agar tabel tidak tumbuh tanpa batas.
- ~~FR-31: Panel Indeks Pasar (IHSG dsb.)~~ — **dicoret dari MVP** (keputusan D-4). Currents API hanya menyediakan berita, bukan data harga, dan panel berisi angka statis lebih buruk daripada tidak ada panel di aplikasi keuangan. Dipindah ke Fase 3 bersama integrasi data pasar.

### 6.6 Pengaturan
- FR-19: Pengguna dapat mengubah preferensi format angka/mata uang tampilan dan profil risiko investasi.

### 6.7 Pencatatan Realisasi (prasyarat dashboard progres)

> **Status (25 Sep 2026): FR-32, FR-33, dan FR-34 dipensiunkan** (commit `4537b6c`, keputusan D-10). Controller, form, dan route setoran (`goals.contributions.*`) sudah dihapus; tabel `goal_contributions` dipertahankan hanya supaya riwayat lama tidak hilang, dan nilainya sudah dipindahkan ke `financial_goals.allocated_amount` oleh migrasi `add_allocation_to_financial_goals_table`. Dana tujuan kini **ditandai** dari saldo rekening (FR-73) dan dinaikkan lewat tombol "Sudah saya sisihkan" (FR-85). FR-35 tetap berjalan (`on_track` di `DashboardSummaryService`), tetapi kini membandingkan rencana terhadap `allocated_amount`. FR-36 dibangun 29 Sep 2026 — lihat catatan di bawah FR-36. Teks di bawah dipertahankan sebagai jejak rancangan, bukan dihapus.

Tanpa bagian ini, `current_amount` tidak pernah berubah dan progress bar di FR-13 selamanya diam — dashboard hanya menampilkan hasil kalkulasi, bukan progres. Ini juga menghapus alasan pengguna untuk kembali, sehingga metrik retensi di §9 tidak akan tercapai.

- FR-32: Pengguna dapat mencatat setoran ke sebuah tujuan (nominal, tanggal, catatan opsional) secara manual, **lewat kalender aktivitas**: klik tanggalnya, lalu catat setoran di sana.

  Tidak ada form setoran tersendiri di Dashboard. Rancangan ini melewati dua putaran sebelum mendarat di sini, dan alasannya perlu dicatat supaya tidak diputar lagi:

  1. Semula form Dashboard punya kolom tanggal. Kolom itu memperlambat jalur yang paling sering dipakai — setoran hari ini — demi keperluan yang jarang.
  2. Kolom tanggal lalu dihapus dan setoran dikunci ke hari berjalan. Tetapi setoran yang baru sempat dicatat beberapa hari kemudian jadi jatuh di tanggal yang salah, dan aplikasi yang mengukur kedisiplinan menabung tidak boleh salah mencatat kapan orang menabung.
  3. Pencatatan akhirnya dipindahkan seluruhnya ke kalender. Tanggalnya tidak perlu diketik sama sekali — ia ditentukan oleh sel yang diklik, cara memilih tanggal yang paling langsung. Satu tempat, bukan dua yang harus dijaga tetap sepadan.

  Tanggal masa depan ditolak (`before_or_equal:today`): uang yang belum disetor bukan setoran. Pada tanggal masa depan, kalender menyembunyikan form dan mengarahkan pengguna membuat pengingat (FR-57) sebagai gantinya.

  Tombol "Catat Setoran" pada banner pengingat harian mengarah ke anchor `#catat-setoran`, yang kini menempel pada kartu kalender.
- FR-33: Pengguna dapat melihat, mengedit, dan menghapus riwayat setoran per tujuan.
- FR-34: `current_amount` sebuah tujuan adalah hasil turunan dari dana awal + akumulasi setoran tercatat, bukan angka yang diedit langsung.
- FR-35: Dashboard membandingkan **rencana vs realisasi**: setoran seharusnya sampai bulan ini vs yang benar-benar tercatat, beserta selisihnya (tertinggal/di depan target).
- FR-36: Bila realisasi meleset, sistem menawarkan rekalkulasi: naikkan setoran, mundurkan tanggal target, atau turunkan nominal target.

  **Dibangun 29 Sep 2026** (`GoalRecalculationService`, route `goals.recalculate`, komponen `TawaranRekalkulasi` di halaman Tujuan — tidak di Dashboard). Aturannya:
  - Ditawarkan bila `on_track` = tertinggal **dan** setoran yang dibutuhkan sekarang (dari `allocated_amount` dan sisa bulan) lebih besar dari setoran rencana. Status tertinggal diukur linear, sedangkan rencana memakai imbal hasil majemuk; tanpa syarat kedua, tujuan yang rencananya masih cukup ikut ditawari "naikkan setoran" dengan angka yang sama.
  - Tiap pilihan menahan dua hal dan mengubah satu. Tanggal baru = bulan tercepat yang tercapai dengan setoran rencana (maks. 50 tahun, bila tidak tercapai pilihannya tidak ditawarkan); target baru = nominal terbesar yang tercapai, dibulatkan ke bawah ke ribuan, dan hanya ditawarkan bila masih di atas dana terkumpul. Rumusnya `monthsToReach()` dan `affordableTarget()` di `GoalCalculatorService`.
  - Browser hanya mengirim nama pilihan; angkanya dihitung ulang di server sebelum disimpan. Hasilnya snapshot baru di `goal_calculations` dengan `calculation_snapshot.recalculation` = `{option, baseline_amount}`, plus aktivitas `goal_recalculated`.
  - **Garis awal `on_track` berpindah**: bila snapshot terakhir adalah rekalkulasi, progres linear diukur dari tanggal snapshot itu dan `baseline_amount`, bukan dari `created_at` dengan dana nol. Tanpa ini tujuan tetap "tertinggal" dengan selisih yang sama dan tawarannya tidak pernah hilang. Batasannya: menyunting tujuan lewat form ubah membuat snapshot biasa, sehingga garis awalnya kembali ke `created_at`; snapshot juga tidak ikut cadangan (lihat `BackupService`).

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

> **Status (29 Sep 2026): FR-41, FR-42, FR-43, FR-44 dibangun; FR-45 belum.** Kalkulator Pinjaman (`/kalkulator/pinjaman`) dan Investasi (`/kalkulator/investasi`) memakai `GoalCalculatorService`. **Satu penyimpangan dari "konvensi rate yang sama":** bunga pinjaman dihitung `r / 12` seperti bank, bukan konversi efektif — suku bunga pinjaman adalah angka kontrak, dan angsuran yang tidak cocok dengan brosur bank akan dikira salah (CLAUDE.md §6.8). "Jadikan Tujuan" tersedia di kalkulator Tujuan dan Investasi, tidak di Pinjaman. Riwayat kalkulasi (FR-45) sebagian sudah terjawab oleh query string: setiap hasil punya URL yang bisa disimpan, dibagikan, dan dibuka ulang.
- FR-86 *(ditambahkan 29 Sep 2026)*: **Jenis bunga & cek kesehatan cicilan di kalkulator KPR.**
  - **Jenis bunga:** tetap; **berjenjang**; dan **mengambang** (angsuran dengan bunga sekarang, diuji dengan "bunga bila naik").
  - **Berjenjang (30 Sep 2026, menggantikan "tetap lalu mengambang"):** 2–10 jenjang, tiap jenjang "bunga X% sampai tahun ke-N"; tahun mulai mengikuti jenjang sebelumnya dan jenjang terakhir berlaku sampai tenor habis, jadi tidak ada tahun terlewat atau tumpang tindih. Di awal setiap jenjang angsuran dihitung ulang dari sisa pokok, sisa tenor, dan bunga jenjang itu; sisa pokok tetap berakhir nol. "Tetap lalu mengambang" adalah dua jenjang, dan pilihan tersendirinya dihapus supaya dua pilihan tidak melakukan hal yang sama. Jenjang bisa ditandai "perkiraan (mengambang)" — hanya keterangan. Hasilnya tabel angsuran per jenjang beserta kenaikannya, dan kenaikan jenjang terakhir terhadap tahun pertama disebut terang-terangan. Contoh pengguna yang dikunci test: Rp 500 jt, 20 th, 3,75% (th 1) / 6,75% (th 2–4) / 9,75% (th 5–10) / 10,75% (th 11–20) → angsuran Rp 2.964.442 / 3.763.866 / 4.546.179 / 4.739.763, total bunga Rp 567.168.740.
  - **Cek kesehatan (opsional):** pendapatan bersih, cicilan lain, pengeluaran rutin, dan **pajak tahunan** (satu total: PBB rumah, pajak kendaraan/STNK, dan pajak tahunan lain; diubah dari "PBB" saja atas koreksi pengguna 29 Sep 2026). Dinilai dari **keadaan terberat** — jenjang dengan angsuran terbesar, atau bila bunga naik — karena KPR yang sehat hanya selama bunga promo bukan KPR yang sehat. Pada bunga berjenjang, jenjang **pertama** yang melewati batas sehat juga disebut ("mulai tahun ke-5, rasionya sudah 30,3%"), karena kapan masalahnya mulai sering lebih berguna daripada puncaknya.
  - **Ukuran:** rasio cicilan terhadap pendapatan (KPR + cicilan lain; pajak tidak ikut, seperti bank) dengan batas **≤30% sehat, 30–40% waspada, >40% berisiko**; dan sisa uang (pendapatan − cicilan − pengeluaran − pajak tahunan/12): minus berisiko, di bawah 10% pendapatan waspada. Label akhir = yang terburuk dari keduanya, disertai kalimat alasannya. Batasnya di `config/loan_health.php`, dipilih pengguna.
  - **Estimasi kenaikan gaji per tahun (opsional, 0–30%, 30 Sep 2026):** pendapatan di awal jenjang tahun ke-N = pendapatan × (1 + kenaikan)^(N−1). Akibatnya keadaan terberat dipilih dari **rasio cicilan tertinggi**, bukan angsuran terbesar — dengan gaji naik 5%, contoh di atas paling berat di tahun ke-5 (24,9%), bukan tahun ke-11. Pengeluaran, cicilan lain, dan pajak **dianggap tetap** (disebut di halaman: hasilnya lebih ringan dari kenyataan bila biaya hidup ikut naik); skenario "bila bunga naik" tidak punya tahun, jadi dinilai dengan pendapatan sekarang. Bila kenaikan gaji menutup seluruh kenaikan angsuran, halaman menyebut bahwa labelnya bergantung pada asumsi itu.
  - Simulasi edukatif, bukan penilaian kredit — disebut di halaman. Pengisian otomatis dari data Arus (anggaran, utang) untuk pengguna yang login **ditunda**, atas pilihan pengguna.
- FR-46 *(Fase 2, bukan MVP)*: **Kalkulator Pajak (PPh 21).** Ditunda karena aturan pajak berubah tiap tahun dan menuntut pemeliharaan berkelanjutan — biaya perawatannya tidak sebanding untuk MVP, dan salah hitung pajak lebih berbahaya bagi kepercayaan pengguna daripada tidak menyediakannya sama sekali.

**Yang sengaja tidak dibuat sebagai kalkulator terpisah:**

| Item di mockup | Keputusan | Alasan |
|---|---|---|
| Tabungan | Digabung ke FR-42 | Matematikanya identik dengan kalkulator investasi, hanya berbeda asumsi return. Dua menu untuk satu rumus hanya membingungkan. |
| Pensiun | Tetap sebagai **Tujuan**, bukan kalkulator | Dana pensiun perlu dipantau bertahun-tahun. Menjadikannya alat sekali pakai membuang seluruh nilai pemantauan progres. |

**Reuse:** FR-41 dan FR-42 memakai keluarga rumus yang sama dengan kalkulator tujuan (anuitas). Keduanya dibangun di atas `GoalCalculatorService` yang sudah teruji, bukan sebagai mesin hitung terpisah — lihat CLAUDE.md §6.

### 6.10 Dompet — sumber dana

Dompet menjawab satu pertanyaan: **uang pengguna sekarang ada di mana.** Ia berdiri sendiri, tidak menempel pada tujuan mana pun, dan satu daftar dipakai bersama oleh seluruh tujuan.

> **Status (25 Sep 2026):** tabel `wallets` seperti rancangan di bawah **tidak pernah dibuat**. Pertanyaan "uang saya ada di mana" kini dijawab **Rekening & aset** (FR-63): nama + jenis + saldo (FR-47), pembaruan nilai tanpa dianggap setoran lewat penilaian ulang (FR-72, padanan FR-49), dan kekayaan lintas rekening di Dashboard (FR-79..FR-82, padanan FR-50). FR-48 gugur bersama pencatatan setoran (D-10). FR-51 dibangun 27 Sep 2026 sebagai kolom `accounts.units` (gram/lot/unit) — lihat di bawah. Halaman `/dompet` — di navigasi kini bernama "Dana tujuan" — menampilkan dana terkumpul per tujuan dari `DashboardSummaryService`, bukan tabel dompet.

- FR-47: Pengguna dapat mencatat dompet — nama, **jenis instrumen**, dan saldo saat ini. Contoh: "BCA" (deposito/kas), "Emas" (emas), "Reksa Dana" (reksa dana pasar uang).
- FR-48: Saat mencatat setoran ke sebuah tujuan, pengguna memilih **dari dompet mana** uang itu diambil.
- FR-49: Pengguna dapat memperbarui saldo sebuah dompet tanpa mencatatnya sebagai setoran. Emas yang naik harga bukan uang yang baru disisihkan; menyamakan keduanya merusak riwayat setoran dan grafik kedisiplinan menabung.
- FR-50: Dashboard menampilkan total kekayaan lintas dompet, terpisah dari progres tujuan.
- FR-51: Untuk aset bersatuan (emas dalam gram, saham dalam lot), pengguna dapat mencatat jumlah satuannya. Nilai rupiah tetap menjadi sumber perhitungan — satuan bersifat informasi pelengkap. *(Dibangun 27 Sep 2026: `accounts.units`, satuan dari `AccountKind::satuan()` — gram emas, lot saham, unit reksa dana. Diisi saat menambah/mengubah rekening dan diperbarui lewat "Perbarui nilai"; kosong berarti tidak berubah. Kartu menampilkan "10,5 gram · ≈ Rp 1.450.000/gram", saham per lembar. Ikut dicadangkan.)*

**Jenis instrumen pada dompet bukan hiasan.** Ia yang membuat §6.11 bekerja tanpa input tambahan. Tanpa kolom itu, komposisi tujuan tidak bisa diturunkan dan pengguna terpaksa mencatat aset yang sama dua kali.

### 6.11 Detail Alokasi Tujuan — saran vs nyata

Hari ini kartu "Alokasi Instrumen yang Disarankan" (FR-23..27) memberi saran lalu berhenti di situ: tidak ada cara mengetahui apakah portofolio nyata pengguna sudah mendekati saran itu atau melenceng jauh. Saran tanpa tindak lanjut adalah nasihat yang tidak pernah diperiksa. Bagian ini adalah tampilan rinci dari donut alokasi tersebut.

- FR-52: Donut alokasi dapat dibuka menjadi tampilan rinci berisi perbandingan **saran vs nyata** per instrumen, dalam persen **sekaligus nominal rupiah**.
- FR-53: Komposisi nyata **diturunkan** dari setoran tujuan itu, dikelompokkan menurut jenis instrumen dompet asalnya (FR-48). Bukan angka yang diisi ulang secara terpisah.
- FR-54: Sistem menandai penyimpangan yang melewati ambang batas (usulan awal: ±10 poin persen), lengkap dengan arahnya — kelebihan atau kekurangan.
- FR-55: Penyimpangan disertai penjelasan yang bermakna, bukan sekadar angka. Contoh: "emas 20 poin di atas saran — dana darurat butuh dana yang mudah dicairkan, dan emas lebih lambat dijual daripada deposito."
- FR-56: Dashboard menampilkan ringkasan untuk tujuan utama: komposisi nyata dan penyimpangan terbesarnya.

**Kenapa diturunkan, bukan dicatat ulang.** Ini keputusan rancangan yang paling menentukan di dua bagian ini. Bila pengguna mencatat asetnya di Dompet *dan* mencatat komposisi tujuan secara terpisah, aset yang sama masuk dua kali dan kedua angka itu **akan** menyimpang tanpa ada yang menyadarinya — persoalan klasik yang sulit dilacak. Dengan menurunkannya dari setoran, hanya ada satu tempat memasukkan data, dan detail alokasi mustahil berbeda dari Dompet karena sumbernya memang sama. Sejalan dengan FR-34, yang sudah menetapkan `current_amount` sebagai nilai turunan, bukan kolom yang diedit langsung.

**Batas yang diterima secara sadar.** Komposisi dihitung dari nilai **saat menyetor**, bukan harga pasar hari ini. Emas yang dibeli Rp 300.000 lalu naik menjadi Rp 350.000 tetap terbaca Rp 300.000 pada detail tujuan, meski saldo dompetnya sendiri boleh diperbarui (FR-49). Untuk aplikasi perencana ini dinilai wajar; menampilkan nilai pasar per tujuan menuntut riwayat harga per aset dan ditunda sampai ada kebutuhan nyata.
### 6.12 Pengingat Kalender

Kalender aktivitas sudah mencatat apa yang SUDAH terjadi. Pengingat menutup sisi
lainnya: apa yang HARUS dilakukan. Tanpa itu, pengguna hanya bisa melihat ke
belakang, dan aplikasi perencanaan yang tidak pernah mengingatkan apa pun akan
dilupakan begitu semangat awal habis.

- FR-57: Pengguna dapat membuat pengingat pada tanggal dan jam tertentu, dengan judul bebas.
- FR-58: Satu tanggal boleh memuat banyak pengingat, masing-masing dengan jamnya sendiri. Berbeda dari catatan tanggal yang hanya satu per tanggal.
- FR-59: Pengingat dapat ditandai selesai, dan tandanya dapat dibatalkan lagi.
- FR-60: Menandai selesai **tidak menghapus** pengingatnya — kalender bulan lalu tetap memperlihatkan apa yang sudah dikerjakan.
- FR-61: Tanggal yang memuat pengingat diberi penanda di kalender, dan penandanya meredup bila seluruh pengingat pada tanggal itu sudah selesai.
- FR-62: Dashboard menampilkan panel pengingat **hari ini**, dengan jam yang sudah lewat tanpa ditandai selesai ditonjolkan — bukan disembunyikan.

**Batas yang disengaja: pengingat ini murni di dalam aplikasi.** Ia tampil saat
pengguna membuka Arus, dan tidak mengirim notifikasi ke perangkat saat
aplikasi tertutup. Teks di antarmuka sengaja menyebutkan hal ini apa adanya,
bukan menjanjikan lebih dari yang dilakukan.

Menjadikannya notifikasi sungguhan menuntut salah satu dari dua jalur, dan
keduanya keputusan tersendiri — jangan diselipkan sebagai "perbaikan kecil":

| Jalur | Yang dibutuhkan |
|---|---|
| Email terjadwal | Penjadwal (cron) di server + layanan SMTP sungguhan; `MAIL_MAILER` sekarang masih `log` |
| Notifikasi browser (Web Push) | Service worker, kunci VAPID, tabel langganan, izin pengguna, plus penjadwal. Di iOS hanya jalan bila aplikasi dipasang ke layar utama |

Fondasinya sudah siap untuk keduanya: `reminders.remind_at` menyimpan waktu
lengkap, jadi penjadwal apa pun tinggal membacanya.

> **Catatan penomoran §6.13–§6.18.** FR-63..FR-85 ditulis belakangan dari kode yang sudah dibangun (komentar kelas dan test yang mengutipnya). Beberapa nomor hanya pernah dikutip kode **sebagai rentang** — FR-64..FR-69, FR-74..FR-78, FR-79..FR-82 — tanpa rujukan per nomor. Di bawah, nomor yang dikutip satu per satu ditulis sendiri; sisanya ditulis sebagai satu kelompok di bawah rentangnya, bukan dibagi-bagikan ke nomor yang tidak pernah dipakai kode.

### 6.13 Rekening, Transaksi & Arus Kas

Lapisan uang di bawah Tujuan: target menandai sebagian saldo rekening, bukan menyimpan uangnya sendiri. **Saldo tidak pernah disimpan sebagai kolom** — ia selalu `opening_balance` ditambah seluruh transaksi yang menyentuh rekening itu, dihitung sekali untuk semua rekening di `AccountBalanceService` (D-12).

- FR-63: **Rekening & aset.** Pengguna mencatat rekening dengan nama, jenis (`bank`, `cash`/Tunai, `stock`/Saham, `fund`/Reksa Dana, `gold`/Emas — `App\Enums\AccountKind`), lembaga (opsional; uang tunai tidak punya lembaga), dan saldo awal ≥ 0 — saldo pada saat pengguna **mulai mencatat**, supaya ia tidak perlu memasukkan riwayat sejak rekening dibuka. Jenis boleh diubah selama rekening belum punya transaksi, lalu dikunci; nama tetap boleh diganti. Rekening yang masih punya riwayat — termasuk yang hanya pernah menerima transfer — tidak bisa dihapus dan ditolak dengan pesan yang bisa dibaca, bukan halaman 500. Hanya `bank` dan `cash` yang **likuid** dan boleh menampung dana tujuan (FR-73).
- FR-64: **Lima jenis transaksi** (`App\Enums\TransactionType`), sengaja dibedakan karena masing-masing memperlakukan saldo dan arus kas secara berbeda:

  | Jenis | Saldo rekening asal | Arus kas |
  |---|---|---|
  | `income` — Pemasukan | bertambah | masuk |
  | `expense` — Pengeluaran | berkurang | masuk |
  | `transfer` — Transfer | berkurang; rekening tujuan bertambah | **tidak** — uang hanya berpindah tempat |
  | `adjustment` — Penyesuaian nilai | bertambah/berkurang (satu-satunya yang boleh negatif) | **tidak** |
  | `payment` — Pembayaran pokok utang | berkurang, sisa utang ikut berkurang | masuk |

  Bunga utang dicatat terpisah sebagai `expense`; mencampurnya ke pembayaran pokok membuat utang tampak lunas lebih cepat daripada kenyataannya.
- FR-65..FR-69 *(dikutip kode sebagai satu rentang bersama FR-64, di `TransactionController`, `AccountBalanceService`, `TransactionTest`, dan `AccountBalanceTest`)*: invarian buku besar yang dijaga saat menulis dan saat menghitung:
  - **Saldo tidak boleh minus** setelah perubahan apa pun — mencatat, menyunting, maupun menghapus (menghapus pemasukan bisa membuat saldo minus karena pengeluaran sesudahnya tetap ada). Diperiksa **sesudah** penulisan di dalam transaksi database yang sama oleh `LedgerGuard`, lalu dibatalkan bila dilanggar (D-13). Pesannya menyebut kekurangannya: "Saldo tidak mencukupi: BCA (kurang Rp …)."
  - **Transfer** wajib punya rekening tujuan yang berbeda dari rekening asal, dan tidak mengubah total aset. `to_account_id` hanya untuk transfer dan `debt_id` hanya untuk pembayaran — keduanya ditolak (`prohibited`) pada jenis lain, bukan sekadar diabaikan.
  - **Nominal** tidak boleh nol; nilai negatif hanya untuk penyesuaian nilai. **Tanggal** tidak boleh di masa depan.
  - **Pembayaran pokok tidak boleh melebihi sisa pokok**; menghapus pembayaran mengembalikan sisa utangnya.
  - **Kekayaan bersih** = total nilai aset − sisa pokok utang. Pembayaran pokok tidak mengubah angka ini.
  - **Arus kas bulanan** = pemasukan − pengeluaran − pembayaran pokok untuk satu bulan `YYYY-MM`; transfer dan penyesuaian nilai tidak dihitung.
  - Halaman Transaksi menampilkan satu bulan (`?bulan=YYYY-MM`), disaring di basis data dan dipaginasi 20 baris, beserta ringkasan arus kas bulan itu. Kategori berupa teks bebas opsional, belum menjadi tabel.
  - Rekening, utang, dan transaksi milik pengguna lain tidak bisa dibaca, dipakai mencatat, maupun disunting.

### 6.14 Utang & Cicilan

- FR-70: Pengguna mencatat utang dengan nama, **sisa pokok saat mulai mencatat** (> 0, bukan nilai pinjaman aslinya), rencana pokok per bulan (≥ 0), dan jatuh tempo (opsional; tanggal yang sudah lewat diterima). Aturannya:
  - Mencatat utang **tidak menambah saldo** rekening mana pun — pinjaman yang benar-benar baru diterima dicatat terpisah, kalau tidak uangnya terhitung sebagai penghasilan.
  - Pembayaran pokok **tidak punya route sendiri**: tombol "Catat pembayaran" di kartu utang hanyalah pintasan ke transaksi berjenis `payment` (FR-64), supaya invarian saldo dan sisa pokok hanya dijaga di satu tempat.
  - Sisa utang = pokok − seluruh pembayaran; status **lunas diturunkan dari riwayat**, bukan kolom atau tombol, dan otomatis aktif kembali bila pembayarannya dihapus.
  - Pokok boleh dinaikkan, tetapi tidak boleh diturunkan di bawah yang sudah dibayar. Utang yang punya riwayat pembayaran tidak bisa dihapus.
  - Rencana pokok bulanan dari utang yang belum lunas dipakai Rencana menabung (FR-74..FR-78) untuk menyisihkan kewajiban lebih dulu.

### 6.15 Investasi & Penilaian Ulang

- FR-71: **Investasi bukan entitas tersendiri** — ia rekening berjenis non-likuid (saham, reksa dana, emas) yang ditampilkan di halaman sendiri dengan penekanan pada "Perbarui nilai". Nilainya tetap terhitung dalam total aset dan komposisi aset. Tiap aset menampilkan tanggal penilaian terakhirnya; kosong berarti belum pernah dinilai ulang sejak dicatat, dan itu sengaja terlihat.
- FR-72: **Penilaian ulang.** Pengguna mengirim **nilai total terkini**; sistem menyimpan **selisihnya** sebagai transaksi `adjustment` bernama "Penilaian ulang …", bukan menimpa saldo. Dengan begitu perubahan nilai punya tanggal, muncul di riwayat, dan bisa dihapus bila keliru (menghapusnya mengembalikan nilai sebelumnya). Nilai yang sama dengan yang tercatat ditolak tanpa mencatat apa pun; nilai negatif dan tanggal masa depan ditolak; nilai nol diterima.

### 6.16 Dana Tujuan & Rencana Menabung

- FR-73: **Dana tujuan ditandai, tidak disetor.** Setiap tujuan punya rekening tempat dananya berada (`account_id`, hanya rekening bank/tunai), nominal yang ditandai (`allocated_amount`), dan prioritas tinggi/sedang/rendah (`App\Enums\GoalPriority`). **Alokasi hanya menandai saldo, tidak memindahkan uang** (D-11): menandai 10 juta di BCA untuk "DP rumah" tidak mengurangi saldo BCA. Aturannya:
  - Alokasi tidak boleh melebihi nominal targetnya; alokasi > 0 wajib menyebut rekeningnya.
  - Total alokasi seluruh tujuan pada satu rekening tidak boleh melebihi saldonya — diperiksa dari dua arah: saat alokasi bertambah **dan** saat saldo berkurang karena pengeluaran (`LedgerGuard`). Pesannya menyebut kedua angka: "Total dana untuk target di BCA jadi Rp …, melebihi saldonya yang Rp …."
  - Progres tujuan (`current_amount` di Dashboard, Tujuan, dan ekspor Excel) = `allocated_amount`.
- FR-74: **Anggaran bulanan** — satu baris per pengguna: perkiraan penghasilan, perkiraan pengeluaran, dan dana cadangan (`monthly_reserve`) yang sengaja tidak dialokasikan ke target. Ini **rencana** yang berlaku seterusnya, bukan catatan per bulan dan bukan kenyataan dari `transactions`.
- FR-75..FR-78 *(dikutip kode sebagai rentang FR-74..FR-78 di `SavingsPlanService`, `SavingsPlanController`, dan `SavingsPlanTest`)*: halaman Rencana menabung menjawab **"sisa uang saya cukup untuk target yang mana?"**:
  - **Kemampuan menabung** = penghasilan − pengeluaran − dana cadangan − cicilan pokok bulanan utang aktif, minimal nol. Cicilan tiap utang dibatasi sisa pokoknya (utang bersisa 300 ribu hanya menyerap 300 ribu). Tanpa anggaran, kemampuan nol dan halaman menjelaskannya lebih dulu.
  - **Kebutuhan bulanan** tiap tujuan aktif memakai rumus anuitas `GoalCalculatorService` lengkap dengan imbal hasil dan inflasi — **bukan** pembagian biasa `(target − terkumpul) / sisa bulan` seperti prototipe. Tujuan tanpa tenggat dan tujuan yang sudah tercapai tidak menyerap dana; tenggat yang sudah lewat dihitung satu bulan (seluruh kekurangannya jatuh ke bulan ini). Kebutuhan bulan ini dihitung dari dana di **awal bulan** (dana saat ini dikurangi yang sudah disisihkan bulan ini), sehingga angkanya diam sepanjang bulan.
  - **Pembagian** berurutan: prioritas tinggi lebih dulu, lalu tenggat terdekat (tanpa tenggat paling belakang). Total alokasi tidak pernah melebihi kemampuan.
  - **Kekurangan dana ditampilkan, bukan ditutupi** — per tujuan dan totalnya, tanpa mengarang asumsi imbal hasil yang lebih tinggi. Sisa kemampuan yang tidak terpakai juga dilaporkan. Tiap baris menyertakan setara hariannya (alokasi ÷ 30).
- FR-85 *(ditambahkan setelah FR-84)*: **"Sudah saya sisihkan".** Satu tombol per baris rencana yang menaikkan `allocated_amount` **sebesar nominal yang dikirim**, bukan menimpanya dengan total baru — aplikasi yang menjumlahkan. Tombol utama mengirim sisa alokasi bulan ini (`remaining_this_month` = alokasi − yang sudah disisihkan bulan ini, minimal nol); "Jumlah lain…" (atau "Sisihkan lagi…" setelah bulan ini terpenuhi) membuka isian untuk nominal tambahan yang lain. Aturannya:
  - Tujuan tanpa rekening tidak bisa memakai tombol ini; tombolnya menjelaskan alasannya alih-alih lenyap.
  - Tidak boleh melebihi nominal target, dan tetap dijaga `LedgerGuard` — uang yang belum ada di rekening tidak bisa ditandai.
  - Setiap penyisihan tercatat sebagai aktivitas `goal_set_aside` di `user_activities`, terhubung lewat `financial_goal_id` (bukan nama, supaya tidak putus saat tujuan diganti nama). Dari situ rencana melaporkan yang sudah disisihkan bulan ini (`set_aside_this_month`); yang disisihkan bulan lalu tidak ikut terhitung.

### 6.17 Dashboard — Lapisan Uang

- FR-79..FR-82 *(dikutip kode sebagai satu rentang di `DashboardWealthTest`)*: Dashboard menggabungkan dua lapisan yang dipisah di backend — `summary` soal **tujuan** (`DashboardSummaryService`) dan `wealth` soal **uang** (`AccountBalanceService`), dengan lapisan uang tampil lebih dulu:
  - **Kekayaan bersih** (aset − utang), total aset, dan total sisa utang.
  - **Arus kas** (pemasukan, pengeluaran, pokok utang, bersih) untuk **bulan yang sedang dilihat** di kalender (`?bulan=YYYY-MM`), bukan selalu bulan berjalan.
  - **Komposisi aset** per jenis rekening dalam persen dan rupiah, untuk donat.
  - **Lima transaksi terbaru**, terbaru di atas, tidak ikut tersaring bulan.
  - Penanda `has_accounts` untuk keadaan belum punya rekening.

  Sejak pencatatan setoran dipensiunkan, grafik pertumbuhan kekayaan (FR-14) juga dibangun dari **riwayat transaksi** — titik pertamanya memuat seluruh saldo awal ditambah transaksi sebelum jendela, dan transfer diabaikan.

### 6.18 Data, Cadangan & Riwayat

- FR-83: **Cadangan.** Pengguna mengunduh seluruh data keuangannya sebagai satu berkas JSON bertanggal (`arus-cadangan-YYYY-MM-DD.json`, `Cache-Control: no-store`). Isinya: rekening, utang, transaksi, tujuan (termasuk alokasi, prioritas, dan riwayat penyisihannya sebagai `set_asides` — nominal dan waktunya, supaya "sudah disisihkan bulan ini" bertahan setelah pemulihan), anggaran, catatan kalender, dan pengingat lengkap dengan jam dan status selesainya. ID asli tidak ikut; rekening dan utang membawa `ref` nomor urut yang hanya berlaku di dalam berkas. **Tidak ikut:** data profil (nama, email, telepon) dan snapshot `goal_calculations`. Berbeda dari ekspor Excel (FR-38) yang dibuat untuk dibaca, berkas ini dibuat untuk dikembalikan.
- FR-84: **Pemulihan.** Mengunggah berkas cadangan **mengganti** seluruh data keuangan pengguna — bukan menggabungkan, sehingga memulihkan dua kali tidak menggandakan data. Seluruhnya dalam satu transaksi database; berkas bukan JSON, tanpa penanda versi, versi tak dikenal, berisi nilai tak masuk akal, `ref` yang menunjuk rekening tak ada, atau yang melanggar aturan keuangan (`LedgerGuard`) ditolak utuh. Batas berkas 8 MB. Di antarmuka, pemulihan memakai tombol bahaya dan dialog konfirmasi tersendiri.

**Riwayat memuat transaksi, digabung saat dibaca** (commit `06206b8`, D-15). Halaman Riwayat menggabungkan `user_activities` dan `transactions` lewat UNION di basis data, diurutkan menurut waktu pencatatan (`created_at`) dan dipaginasi 20. Transaksi **tidak disalin** ke `user_activities`, sehingga menyunting atau menghapus transaksi ikut memperbaiki riwayatnya. Tidak ada nomor FR yang dikutip kode untuk perubahan ini.

## 7. Requirement Non-Fungsional

- **NFR-1 Performa:** Waktu hitung kalkulator < 200ms di sisi backend; halaman utama first load < 2.5s pada koneksi 4G.
- **NFR-2 Keamanan:** Password di-hash (bcrypt via Laravel), autentikasi berbasis sesi (Laravel Breeze, guard `web`), CSRF ditangani otomatis oleh middleware Laravel + Inertia, validasi input di backend (lihat CLAUDE.md D-9 untuk alasan lengkap perubahan dari rencana awal token Sanctum).
- **NFR-3 Skalabilitas:** Session disimpan di `SESSION_DRIVER=database` (atau Redis di produksi) sehingga tetap mendukung horizontal scaling tanpa sticky session, meski aplikasi tidak lagi stateless murni seperti rencana arsitektur SPA+API sebelumnya; cache berita disimpan di tabel/DB atau Redis (opsional).
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
- **Metrik retensi utama yang sesungguhnya: persentase pengguna yang mencatat setoran (FR-32) minimal sekali dalam 30 hari setelah membuat tujuan (target: ≥30%).** Membuat kalkulasi hanyalah aktivitas sekali jalan; mencatat realisasi adalah alasan pengguna kembali tiap bulan. *(FR-32 sudah dipensiunkan — D-10. Padanannya kini penyisihan lewat "Sudah saya sisihkan" (FR-85), yang tercatat sebagai aktivitas `goal_set_aside`.)*

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
| **Rilis 1 — produk utuh terkecil** | Auth (+ verifikasi email, rate limit), kalkulator **2 kategori**: beli rumah & beli kendaraan, pencatatan setoran (FR-32..36 — *FR-32..34 kemudian dipensiunkan, lihat D-10*), dashboard progres, pengingat kalender (FR-57..62), hapus/ekspor akun (FR-37..38) | Matematika kedua kategori ini paling lurus: satu target nominal, satu tanggal. Sudah menjadi produk yang benar-benar bisa dipakai orang, dan sudah punya alasan pengguna kembali tiap bulan. |
| **Rilis 2 — kedalaman finansial** | Mesin rekomendasi instrumen + blended return (FR-10..12, FR-23..27), **kalkulator utilitas** Pinjaman/KPR & Investasi (FR-41..45), lalu kategori **dana darurat**, **dana pendidikan**, dan **dana pensiun** | Rekomendasi lebih dulu karena ia memberi makan estimasi return kalkulator. Kalkulator utilitas ditaruh di sini karena memakai keluarga rumus yang sama dan biayanya rendah setelah mesin kalkulator terbukti benar — sekaligus jadi pintu masuk pengguna baru lewat FR-44. Tiga kategori tujuan sisanya butuh penentu target tersendiri (FR-20..22). |
| **Rilis 3 — modul News** | Ingest + klasifikasi kategori (FR-16..18, FR-28..30), panel berita | Ditaruh terakhir karena bergantung pada pihak ketiga yang kualitasnya belum terverifikasi. **Gerbang mulai: uji kualitas hasil pencarian Currents dengan kata kunci nyata.** Bila hasilnya kurang, ganti sumber atau coret modul — jangan dipaksakan. *(27 Sep 2026: Currents tidak lolos gerbang; sumber diganti ke NewsData.io, lolos, dan modulnya dibangun — D-16.)* |

**Setelah MVP**

| Fase | Fokus |
|---|---|
| Fase 2 | **Dompet (FR-47..FR-51)** — *tabel `wallets` tidak pernah dibuat; kebutuhannya kini dijawab Rekening & aset (FR-63), lihat catatan status di §6.10* — lalu **Detail Alokasi Tujuan (FR-52..FR-56)**, Kalkulator Pajak PPh 21 (FR-46), notifikasi pengingat setoran, perhitungan return neto pajak (mengaktifkan D-3) |
| Fase 3 | Multi-currency, family sharing, integrasi data pasar real-time (sekaligus membuka kembali Panel Indeks Pasar, lihat D-4) |

> **Urutannya mengikat: Dompet dulu, Detail Alokasi menyusul.** Detail alokasi menurunkan komposisinya dari `wallets.instrument_type` (FR-53), jadi tanpa Dompet ia tidak punya bahan sama sekali. Ia juga membandingkan realisasi terhadap alokasi yang disarankan, sehingga ikut menunggu mesin rekomendasi instrumen (FR-23..27) di Rilis 2.
>
> Dompet sendiri **tidak menunggu apa pun** dan secara teknis bisa dimajukan ke Rilis 1 — nilainya bagi pengguna baru terasa penuh setelah Detail Alokasi ada, tetapi memajukannya berarti setoran mulai mencatat asal dompet lebih awal, sehingga saat Detail Alokasi menyala datanya sudah terkumpul, bukan kosong.

**Yang sudah dibangun: perluasan menjadi pencatat keuangan (24–25 Sep 2026)**

Di luar urutan rilis di atas, aplikasi berkembang dari kalkulator/simulasi menjadi pencatat uang dalam lima fase. Penomoran "Fase 1–5" di tabel ini hanya berlaku untuk perluasan ini dan **tidak sama** dengan Fase 2/Fase 3 pada tabel "Setelah MVP".

| Fase | Commit | Yang dibangun | FR |
|---|---|---|---|
| **Fase 1 — rekening, transaksi, investasi** | `a485a88`, perbaikan `6abc48e` | Tabel `accounts`, `transactions`, dan `debts` (tabel utang dibuat lebih dulu karena `transactions.debt_id` menunjuk ke sana); `AccountBalanceService` + `LedgerGuard`; halaman Rekening & aset, Transaksi, Investasi, dan penilaian ulang. `6abc48e` memperbaiki error 500 saat menambah rekening dan memindahkan form tambah investasi ke halaman Investasi | FR-63..FR-69, FR-71, FR-72 |
| **Fase 2 — utang & cicilan** | `9d0c9bf` | Halaman Utang & cicilan; pembayaran pokok lewat transaksi `payment` | FR-70 |
| **Fase 3 — alokasi & rencana menabung** | `4537b6c` | **Pencatatan setoran kalender dipensiunkan** (D-10); dana tujuan menjadi alokasi yang menandai saldo rekening (D-11); prioritas tujuan; tabel `budgets`; halaman Rencana menabung; ekspor Excel mengganti sheet Setoran dengan sheet Transaksi | FR-73..FR-78 |
| **Fase 4 — dashboard kekayaan** | `a03befd` | Dashboard baru: kekayaan bersih, arus kas bulan yang dilihat, komposisi aset, transaksi terbaru | FR-79..FR-82 |
| **Fase 5 — data & umpan balik** | `052554a`, `06206b8`, `fc6845a`, `cd3478f` | Cadangan & pemulihan JSON (halaman Data & cadangan); Riwayat memuat transaksi (D-15); tombol "Sudah saya sisihkan" + `user_activities.financial_goal_id`; pesan `LedgerGuard` yang menyebut angkanya; perbaikan istilah ("dana tujuan", "perkiraan penghasilan"). Lalu (`cd3478f`): kebutuhan bulan ini dihitung dari dana awal bulan, `remaining_this_month`, dan isian "Jumlah lain…" (`TombolSisihkan.jsx`) | FR-83, FR-84, FR-85 |

## 13. Keputusan yang Sudah Diambil

Disetujui 2026-08-23; D-10..D-15 menyusul 24–25 Sep 2026 bersama lapisan uang (§6.13–§6.18). Setiap keputusan disertai alasan agar bisa ditinjau ulang bila asumsinya berubah.

| # | Keputusan | Alasan |
|---|---|---|
| D-1 | **Inflasi menaikkan nominal target** (pendekatan A): `FV = target × (1+inflasi)^tahun`, lalu pakai return nominal. Bukan *real return*. | Nominal masa depan terlihat oleh pengguna sehingga lebih mudah dijelaskan ("rumah 800 juta hari ini ≈ 1,07 M dalam 5 tahun"). **Larangan keras: jangan sekaligus memotong return dengan inflasi** — itu perhitungan ganda. |
| D-2 | **Ordinary annuity** (setoran di akhir bulan). | Menghasilkan setoran sedikit lebih besar daripada *annuity due*, jadi lebih konservatif untuk alat perencanaan — lebih baik pengguna menabung sedikit berlebih daripada meleset. Asumsi ini ditampilkan terbuka di panel hasil. |
| D-3 | **MVP memakai return bruto**, diberi label eksplisit "sebelum pajak". Kolom pajak tetap disiapkan di master instrumen. | Menghindari kompleksitas tiga rezim pajak di rilis pertama, tanpa mengunci diri dari perhitungan neto nanti (tidak perlu migrasi ulang). |
| D-4 | **Panel Indeks Pasar dicoret dari MVP.** FR-31 ditutup. Dipindah ke Fase 3, menunggu penyedia data pasar. | Tidak ada sumber data — Currents hanya menyediakan berita. Membangun panel berisi angka statis lebih buruk daripada tidak ada panel sama sekali di aplikasi keuangan. |
| D-5 | ~~Sanctum bearer token.~~ **Digantikan D-9 (2026-08-25)** — lihat baris di bawah. Dipertahankan di sini sebagai jejak keputusan, bukan dihapus. | Alasan asli: frontend Vite dianggap beda origin dari API; mode cookie menuntut CORS berkredensial, kesamaan domain induk, dan penanganan CSRF. Asumsi ini gugur begitu repo ternyata di-scaffold sebagai Laravel+Inertia satu origin, bukan SPA terpisah. |
| D-6 | **Alokasi di-snapshot per kalkulasi.** `goal_recommended_allocations` menempel ke `goal_calculation_id`. | Saat aturan alokasi diubah admin, hasil lama tetap bisa direproduksi dan dijelaskan ke pengguna. |
| D-7 | Angka default return/inflasi disimpan **beserta `rates_as_of` dan `rates_source`**, ditinjau setahun sekali. Nilai awal wajib diverifikasi ke sumber resmi saat seeding, bukan diambil dari dokumen ini. | Angka ini langsung membentuk hasil yang dilihat pengguna. Angka tanpa sumber dan tanpa tanggal berlaku adalah utang teknis yang diam-diam menyesatkan. Sumber acuan: BPS (inflasi umum & pendidikan), Bank Indonesia (BI Rate), LPS (bunga penjaminan deposito), Kemenkeu DJPPR (kupon SBN ritel), OJK/BEI (kinerja jangka panjang indeks). |
| D-8 | **Agregasi dashboard dihitung di backend** (`DashboardSummaryService`), bukan frontend menjumlahkan sendiri daftar goals. | Satu sumber kebenaran untuk rumus total aset, keamanan kepemilikan data terikat ke user yang login, dan menghindari duplikasi agregasi time-series untuk grafik pertumbuhan aset. Detail lengkap di CLAUDE.md §6.9. |
| D-9 | **Arsitektur aplikasi adalah Laravel + Inertia.js satu origin (Breeze), bukan React SPA terpisah + REST API.** Auth memakai guard `web` (session/cookie) bawaan Breeze. `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` custom, `FRONTEND_URL`, dan konfigurasi CORS **tidak dipakai** — semua penanda pola SPA terpisah yang sudah gugur. Tidak ada `routes/api.php`; controller mengirim data lewat `Inertia::render(..., $props)`. | Repo yang di-scaffold tim ternyata sudah memakai `laravel/breeze` + `inertiajs/inertia-laravel` (dikonfirmasi lewat `composer.json`, `bootstrap/app.php`, dan `app/Http/Middleware/HandleInertiaRequests.php` yang sudah membagikan `auth.user` ke semua halaman), bukan scaffolding SPA+API kosong seperti diasumsikan D-5. Auth (register/login/logout/protected route) sudah tersedia gratis dari Breeze dan sudah teruji — membongkarnya untuk mengejar pola SPA murni berarti menulis ulang bagian paling rawan-bug dari nol tanpa manfaat yang dibutuhkan di lingkup MVP (lihat "Di Luar Lingkup" — mobile native bukan target MVP). Detail lengkap & kontrak props di CLAUDE.md §2, §3, §8, §10.1. |
| D-10 | **Pencatatan setoran kalender dipensiunkan (25 Sep 2026).** FR-32..FR-34 dan FR-48 ditutup; `GoalContributionController`, form setoran di kalender, dan route `goals.contributions.*` dihapus. Tabel `goal_contributions` **dipertahankan tanpa jalan tulis** — nilainya sudah dipindahkan ke `allocated_amount` oleh migrasi, dan model `GoalContribution` hanya dipakai saat menghapus tujuan. | Begitu rekening dan transaksi ada, setoran ke tujuan menjadi tempat kedua untuk mencatat uang yang sama, dan kedua angka itu akan menyimpang (komentar `DashboardSummaryService`). Tabelnya tidak di-drop supaya riwayat yang terlanjur tercatat tidak hilang; bila kelak pasti tidak dibutuhkan, di-drop lewat migrasi tersendiri. |
| D-11 | **Alokasi menandai saldo, tidak memindahkan uang.** Dana tujuan = `financial_goals.allocated_amount` pada satu rekening **bank/tunai**; total alokasi per rekening ≤ saldonya, diperiksa `LedgerGuard` dari dua arah. | Menandai tidak mengurangi saldo dan tidak menciptakan uang baru; tanpa batas saldo, uang yang sama bisa ditandai untuk DP rumah sekaligus dana darurat. Saham dan emas dikecualikan karena nilainya bisa turun setelah ditandai, sehingga target meleset diam-diam (`AccountKind::likuid()`). |
| D-12 | **Saldo rekening, sisa utang, dan status lunas tidak disimpan sebagai kolom** — selalu diturunkan dari `opening_balance`/`principal` ditambah riwayat transaksi. | Alasan yang sama seperti FR-34: kolom cache berarti dua sumber kebenaran yang menyimpang begitu transaksi disunting atau dihapus. `AccountBalanceService` menghitung semuanya sekali untuk semua rekening, bukan satu kueri per rekening. |
| D-13 | **Invarian buku besar diperiksa sesudah penulisan**, di dalam `DB::transaction` yang sama, lalu dibatalkan bila dilanggar (`LedgerGuard`). Pesannya dilempar sebagai `ValidationException` berbahasa Indonesia yang menyebut angkanya. | Aturannya bergantung pada keadaan **hasil**: menyunting transaksi lama, memindahkannya ke rekening lain, atau mengubah nominal pembayaran memengaruhi banyak angka sekaligus. Memeriksa di depan berarti satu cabang manual per jenis suntingan. |
| D-14 | **Investasi adalah rekening non-likuid, bukan tabel tersendiri; penilaian ulang disimpan sebagai selisih** (`adjustment`), bukan menimpa saldo. | Tabel terpisah membuat nilai investasi berhenti terhitung dalam total aset. Lewat selisih, perubahan nilai punya tanggal, muncul di riwayat, dan bisa dibatalkan. |
| D-15 | **Riwayat menggabungkan `user_activities` dan `transactions` saat dibaca** (UNION di basis data), transaksi tidak disalin ke `user_activities`. Pengecualiannya penyisihan (`goal_set_aside`), yang memang dicatat ke `user_activities`. | Salinan akan tetap menyebut angka lama setelah transaksi disunting. Penyisihan dicatat karena ia tidak terekam di mana pun: `allocated_amount` hanya angka berjalan. |
| D-16 | **Sumber berita: NewsData.io, bukan Currents API.** Satu kueri kata kunci per kategori, `country=id`, `language=id`, dibatasi ke media keuangan (Kontan, CNBC Indonesia, Liputan6, detik, Kompas); artikel yang cocok daftar pengecualian (zodiak, lowongan kerja, prakiraan cuaca) dibuang saat ingest. Disimpan dan ditampilkan: judul, ringkasan, sumber, tanggal, tautan ke artikel asli, dan (sejak 27 Sep 2026) tautan foto artikel — isi penuh tidak. | Uji gerbang Rilis 3 (27 Sep 2026) dengan kata kunci yang sama. **Currents:** tidak ada bahasa Indonesia di daftar bahasanya; "suku bunga", "IHSG", "reksa dana", "inflasi" → 0 artikel; `country=ID` + `finance` → 0; "KPR" → berita Liberia dan Kansas. **NewsData.io:** seluruhnya berbahasa Indonesia dan terbit 0–1 hari sebelumnya; "IHSG" → 22 artikel, hampir semuanya Kontan dan relevan; "BI Rate" → 7, semuanya relevan. Kategori `business` saja masih meloloskan ramalan zodiak karier, dan menggabungkan kata kunci dengan OR tanpa filter media meloloskan berita PGRI — karena itu kueri per kategori + filter media + daftar pengecualian. Kuota gratis mengizinkan metadata dipakai komersial; gambar dan isi penuh tidak. **Diubah 27 Sep 2026 — foto artikel ditampilkan** atas keputusan pengguna: hanya TAUTAN fotonya yang disimpan (`image_url`, HTTPS saja); fotonya dimuat langsung dari server penerbit, tidak diunduh atau disalin, dan kartu selalu menyebut penerbit serta menautkan ke artikel asli. **Ditinjau 29 Sep 2026 — foto dimatikan secara bawaan.** Syarat NewsData.io (newsdata.io/terms, akhirnya terbaca lewat browser) menyatakan teks dan gambar tetap milik pembuatnya, NewsData.io tidak berhak memberi izin memakainya, pengguna boleh memakai data untuk keperluan komersial asal tidak melanggar hukum hak cipta negaranya dengan risiko sendiri, dan pengguna menanggung ganti rugi NewsData.io atas klaim pihak ketiga. Jadi tidak ada izin atas foto dari mana pun, dan memuatnya dari server penerbit tidak mengubah itu. Keputusan: pengaturan `NEWS_SHOW_IMAGES` (`config/news.php` `show_images`) **bawaannya mati** — server yang tidak diatur tidak memajang foto, tautannya tidak dikirim ke browser, dan kartu memakai sampul ikon kategori buatan sendiri (`SampulKategori`). `.env` pengembangan boleh menyalakannya. Tautan foto tetap disimpan saat ingest. Menyalakannya untuk rilis publik menuntut izin penerbit; judul, ringkasan singkat, nama penerbit, dan tautan ke artikel asli tetap tampil. |
