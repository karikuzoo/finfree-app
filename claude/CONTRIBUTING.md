# CONTRIBUTING.md — Cara Kerja Tim FinGoal

Dokumen ini mengatur **bagaimana** tim bekerja. Untuk **apa** yang dibangun, baca [PRD.md](PRD.md); untuk konteks teknis, [CLAUDE.md](CLAUDE.md); untuk tampilan, [DESIGN.md](DESIGN.md).

Tim saat ini berjumlah 2 orang. Aturan di bawah dirancang untuk ukuran itu — kalau tim bertambah, tinjau ulang.

---

## 1. Prinsip Pembagian Kerja

**Bagi per fitur (vertical slice), bukan per layer.**

Setiap orang mengantar fitur dari form sampai database. Bukan "kamu backend, aku frontend".

Alasannya: pembagian per layer membuat frontend menunggu API atau membangun di atas kontrak khayalan lalu kerja ulang; tidak ada yang memiliki satu fitur secara utuh sehingga bug jadi lempar-lemparan; dan integrasi menumpuk di akhir, justru saat waktu paling sempit.

Konsekuensinya: kedua anggota tim perlu bisa Laravel **dan** React pada tingkat yang cukup. Kalau salah satu jauh lebih kuat di satu sisi, jangan paksakan — sesuaikan pembagiannya dan catat penyesuaiannya di dokumen ini.

---

## 2. Fase 0 — Fondasi (dikerjakan bersama)

**Jangan pecah tugas sebelum bagian ini selesai.** Kalau dibagi terlalu cepat, hasilnya dua komponen Button yang berbeda, dua format error, dan dua cara memformat rupiah. Menyatukannya belakangan lebih lama daripada menyepakatinya di awal.

> **Catatan (2026-08-25):** repo ternyata sudah di-scaffold sebagai Laravel Breeze + Inertia.js + React (lihat CLAUDE.md, keputusan D-9), bukan project kosong. Beberapa item di bawah sudah selesai secara gratis lewat Breeze — ditandai (sudah ada). Jangan bangun ulang dari nol, cukup verifikasi dan lanjutkan dari situ.

Cakupan Fase 0:

- [x] Scaffolding satu project Laravel + Inertia (React) — **sudah ada** dari Breeze, bukan `backend/` + `frontend/` terpisah seperti rencana awal
- [x] `tailwind.config.js` token warna tema Malam — **selesai**. Figtree diganti Plus Jakarta Sans, plus JetBrains Mono untuk angka finansial dan utility `.num-tabular`
- [~] Komponen primitif — **sebagian**. 12 komponen Breeze sudah digayakan ulang (PrimaryButton, TextInput, Dropdown, Modal, dst) dan `CurrencyInput` ditambahkan. Card, Badge, dan Slider belum jadi komponen tersendiri, masih ditulis sebagai kelas berulang di halaman — **ini disengaja**, lihat catatan di bawah daftar
- [~] Layout shell — **sebagian**. `PublicLayout` (halaman publik) dan `AuthenticatedLayout` (setelah login) sudah ada beserta topbar dan menunya. Sidebar kategori kalkulator dan PageContainer belum — juga disengaja, lihat catatan di bawah
- [x] Auth register/login/logout/reset password/verifikasi email/protected route (middleware `auth`) — **sudah ada** dari Breeze, session-based. Tidak perlu Sanctum bearer token maupun `AuthContext` — `auth.user` sudah otomatis dibagikan ke semua halaman lewat `HandleInertiaRequests` (props global, lihat `usePage().props.auth.user`)
- [x] Konvensi penanganan error form — **selesai**, ditulis di CLAUDE.md §10.2 dengan implementasi acuan di halaman kalkulator. Ringkasnya: validasi selalu di backend, pesan Bahasa Indonesia yang menyebut tindakan, `useForm().errors` + `<InputError>` di frontend, tombol disabled selama `form.processing`
- [x] **`docs/fixtures/calculator-cases.json`** — test vector kalkulator, **sudah ada** (9 kasus) bersama `app/Services/GoalCalculatorService.php` dan `tests/Unit/GoalCalculatorServiceTest.php`. D-1 dan D-2 kini dijaga dua test khusus yang gagal bila rumusnya diubah diam-diam.

Test vector ditulis **berdua**. Di berkas itulah keputusan D-1 (inflasi menaikkan target) dan D-2 (ordinary annuity) berubah dari kalimat di dokumen menjadi angka yang mengikat. Setelah disepakati, siapa pun yang menyentuh rumus tidak bisa menyimpang tanpa ketahuan.

### Kenapa dua item sengaja dibiarkan setengah

**Fase 0 dianggap selesai meski Card, Badge, Slider, dan Sidebar belum dibuat.** Ini keputusan, bukan kelalaian.

Membuat komponen sebelum ada yang memakainya berarti menebak bentuk yang dibutuhkan — dan tebakan itu hampir selalu meleset, sehingga komponennya tetap harus dibongkar begitu bertemu kasus nyata yang kedua. Aturan praktis yang dipakai di repo ini: **jadikan komponen setelah pola yang sama muncul tiga kali**, bukan sebelumnya.

Hal yang sama berlaku untuk Sidebar kategori kalkulator. Ia baru masuk akal setelah ada lebih dari satu kalkulator yang benar-benar jadi; sekarang baru satu.

Yang **tidak** boleh ditunda adalah kesepakatan yang mempengaruhi cara dua orang menulis kode — token warna, konvensi error form, dan test vector. Ketiganya sudah selesai. Itulah sebabnya tugas sudah boleh dipecah.

---

## 3. Fase 1 — Pembagian Paralel (Rilis 1)

| | **Dev A — Kalkulator** | **Dev B — Tujuan & Realisasi** |
|---|---|---|
| Backend | `GoalCalculatorService`, `CalculatorController` | `FinancialGoal` CRUD, `GoalContribution`, agregasi progres |
| Frontend | `GoalForm`, `ResultPanel`, `ProjectionChart`, slider "bagaimana jika" | `DashboardPage`, `SummaryCard`, `GoalProgressList`, form catat setoran |
| FR terkait | FR-4..FR-9 | FR-13..FR-15, FR-32..FR-36 |
| Risiko utama | matematika salah tanpa ketahuan | otorisasi bocor antar pengguna |

**Satu-satunya sambungan antar keduanya** adalah bentuk props yang dikirim `CalculatorController` (route `goals.calculations.store`, lihat CLAUDE.md §10.1) saat hasil kalkulasi disimpan sebagai tujuan. Sepakati bentuknya di Fase 0, lalu keduanya bisa jalan tanpa saling tunggu.

Isi Rilis 1, 2, dan 3 ada di PRD §12.

---

## 4. Empat Kesepakatan Wajib

### 4.1 Rumus punya satu pemilik
Seluruh logika perhitungan finansial dimiliki **Dev A**, termasuk duplikat di frontend untuk preview real-time. Dua orang mengimplementasikan future value of annuity secara terpisah akan menghasilkan dua jawaban yang berbeda tipis dan sangat sulit dilacak.

Perubahan pada rumus **selalu** disertai perubahan pada `calculator-cases.json` di PR yang sama.

### 4.2 Branch pendek, merge tiap hari
Branch berumur lebih dari 2 hari akan menyakitkan saat digabung, karena kedua orang bekerja di atas fondasi yang sama. Kalau sebuah fitur butuh lebih lama, pecah jadi beberapa PR yang masing-masing tetap bisa di-merge.

### 4.3 Review silang wajib
Tidak ada yang merge PR sendiri. Maksimal tunggu 1 hari kerja; kalau reviewer tidak sempat, bicarakan langsung, jangan diamkan.

Ini bukan formalitas. Dengan tim 2 orang, reviewer adalah satu-satunya pembaca kedua yang ada — terutama untuk kode kalkulator dan pemeriksaan kepemilikan data.

### 4.4 Dokumen ikut berubah di PR yang sama
Kalau sebuah keputusan berubah saat implementasi, perbarui PRD/CLAUDE/DESIGN **di PR itu juga**. Dokumen yang tertinggal dari kode lebih berbahaya daripada tidak ada dokumen sama sekali, karena orang masih mempercayainya.

---

## 5. Alur Git

```bash
git switch -c feat/kalkulator-form
```

- Branch dari `main`, nama: `feat/`, `fix/`, `docs/`, `chore/` + deskripsi singkat.
- Commit message: `tipe: ringkasan singkat` (`feat:`, `fix:`, `docs:`, `refactor:`, `test:`, `chore:`).
- PR ke `main`, review silang, merge setelah disetujui.
- `main` harus selalu bisa dijalankan. Jangan merge sesuatu yang membuat aplikasi gagal start.

### Zona rawan konflik
Berkas berikut disentuh hampir setiap fitur. Perlakukan sebagai **append-only** dan sering-sering merge `main` ke branch Anda:

- `routes/web.php` (semua route halaman didaftarkan di sini — tidak ada `routes/api.php`)
- `resources/js/app.jsx` (entry point Inertia)
- `tailwind.config.js`
- daftar menu di `Sidebar` (di dalam `resources/js/Layouts/AuthenticatedLayout.jsx`)

Migrasi relatif aman karena bernama timestamp, tapi **jangan pernah mengubah migrasi yang sudah di-merge** — buat migrasi baru.

### Nilai yang dipakai bersama dua fitur
Beberapa nilai muncul di lebih dari satu fitur yang dikerjakan orang berbeda. Nilai semacam itu **tidak boleh ditulis sebagai string di masing-masing tempat** — cukup satu kelas, dipakai bersama.

| Nilai | Sumber kebenaran | Dipakai oleh |
|---|---|---|
| Profil risiko | `app/Enums/RiskProfile.php` | `users.risk_profile` (Dev A) dan `financial_goals.risk_profile_override` FR-24 (Dev B) |

Kalau satu orang mengetik `konservatif` dan yang lain `conservative`, keduanya tidak akan cocok — dan itu baru ketahuan saat kedua fitur disambungkan, ketika sudah ada data telanjur tersimpan. Memakai kelas enum yang sama membuat ketidakcocokan seperti itu mustahil terjadi.

Bila nanti muncul nilai bersama lain (kategori tujuan, status tujuan, kategori instrumen), buat enum-nya dengan pola yang sama dan tambahkan barisnya ke tabel di atas.

### Identitas git
Setelah clone, pastikan identitas commit benar (setel lokal di repo ini, bukan `--global`):

```bash
git config user.name "Nama Anda"
```

---

## 6. Definition of Done

Sebuah PR dianggap selesai bila:

- [ ] Fitur berjalan end-to-end (bukan hanya backend, bukan hanya UI)
- [ ] Validasi ada di **backend**, tidak hanya di frontend
- [ ] Route/controller yang mengakses data tujuan memeriksa kepemilikan terhadap user yang login (Policy Laravel, bukan pengecekan di frontend)
- [ ] Ada test untuk logika non-trivial; kode kalkulator wajib lolos `calculator-cases.json` — `php artisan test` **dan** `npm run test:js`, karena rumusnya ada dua implementasi
- [ ] Empty state, loading state, dan error state tertangani (DESIGN.md §9) — bukan hanya jalur sukses
- [ ] Tidak ada nilai uang bertipe float, tidak ada rupiah diformat di backend
- [ ] Dokumen terkait sudah diperbarui bila ada keputusan yang berubah
- [ ] Sudah di-review orang lain

---

## 7. Keamanan — Tidak Bisa Ditawar

- **Jangan commit `.env`.** Sudah ada di `.gitignore` bawaan Laravel (dikonfirmasi) — tetap periksa sebelum commit pertama yang menyentuh konfigurasi.
- `CURRENTS_API_KEY` tidak boleh muncul di kode frontend maupun di props yang dikirim ke halaman mana pun.
- Jangan mencatat nominal keuangan pengguna beserta identitasnya ke dalam log.
- Setiap query data tujuan disaring berdasarkan user yang login. Ini titik kebocoran data antar pengguna yang paling mudah terjadi dan paling sulit dimaafkan pada aplikasi keuangan.

---

## 8. Prasyarat Lingkungan

Versi berikut **wajib sama** untuk semua anggota tim. Yang **tidak** perlu sama adalah cara memasangnya — Herd, Laragon, XAMPP, atau PHP mentah semuanya boleh, itu hanya alat pemasang.

| Komponen | Versi | Dikunci di |
|---|---|---|
| PHP | **8.4.x** | `composer.json` → `require.php: ^8.4` |
| Ekstensi PHP | `pdo_pgsql` wajib | `composer.json` → `require: ext-pdo_pgsql` |
| PostgreSQL | **17.x** | hanya dokumen ini — tidak bisa dikunci manifest |
| Node.js | **22 LTS** (min. 20.19) | `package.json` → `engines` |
| Composer | 2.x | — |

Versi *paket* tidak perlu didiskusikan: `composer.lock` dan `package-lock.json` sudah ada di repo, jadi `composer install` dan `npm install` menghasilkan dependensi yang identik di semua mesin. **Jangan** jalankan `composer update` atau `npm update` tanpa kesepakatan — itu mengubah lock untuk semua orang.

Alasan versinya dikunci di manifest, bukan sekadar disepakati lisan: salah versi akan tertangkap saat `composer install` dengan pesan yang jelas, bukan muncul tiga minggu kemudian sebagai bug aneh yang hanya terjadi di satu mesin.

### Catatan khusus Windows
Dua hal ini sudah ditangani di repo, disebutkan agar tidak membingungkan saat dibaca:

- `AppServiceProvider` mendaftarkan `SystemRoot` dan beberapa env var Windows lain ke `ServeCommand::$passthroughVariables`. Tanpa itu `php artisan serve` gagal bind dan melapor `Failed to listen ... (reason: ?)` di semua port. Kode ini hanya aktif saat `PHP_OS_FAMILY === 'Windows'`.
- `laravel/pail` dikeluarkan dari skrip `dev` karena membutuhkan ekstensi `pcntl` yang tidak ada di Windows. Pengguna macOS/Linux tetap bisa menjalankan `php artisan pail` secara terpisah.

## 9. Menjalankan Project

Pertama kali:

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan storage:link
```

`storage:link` membuat pranala dari `public/storage` ke `storage/app/public`, tempat foto profil disimpan. **Tanpa perintah ini foto tidak akan tampil** — unggahannya berhasil, berkasnya tersimpan, tetapi setiap gambar muncul sebagai ikon rusak. Cukup dijalankan sekali per mesin; pranalanya sendiri tidak ikut masuk git.

Buat **dua** database di PostgreSQL — satu untuk development, satu untuk test:

```bash
createdb -U postgres fingoal
createdb -U postgres fingoal_test
```

Sesuaikan `DB_USERNAME` dan `DB_PASSWORD` di `.env`, lalu:

```bash
php artisan migrate
```

`fingoal_test` wajib ada karena test berjalan di PostgreSQL, bukan SQLite in-memory bawaan Laravel (lihat `phpunit.xml`). Alasannya: skema kita memakai `jsonb` dan `enum` yang tidak didukung SQLite dan akan diam-diam jatuh jadi `text` — migrasi bisa lolos semua test lalu gagal saat dijalankan sungguhan. Isinya dibuat ulang otomatis tiap kali test berjalan, jadi tidak perlu dimigrasi manual.

Sehari-hari cukup satu perintah — menjalankan server, queue, dan Vite sekaligus:

```bash
composer run dev
```

Aplikasi ada di `http://localhost:8000`. Port 5173 adalah server aset Vite, bukan alamat aplikasi.

### 9.1 Mencoba alur verifikasi email

Verifikasi email **aktif** — pengguna yang belum memverifikasi alamatnya tidak bisa membuka dashboard. Tapi di development emailnya tidak benar-benar terkirim ke mana pun, dan ini yang biasanya membuat orang mengira fiturnya rusak.

Penyebabnya ada di `.env`:

```
MAIL_MAILER=log
```

Dengan setelan itu, Laravel **menulis isi email ke berkas log** alih-alih mengirimkannya. Itu justru yang kita mau saat development: tidak perlu akun SMTP, tidak ada risiko email uji coba nyasar ke alamat orang sungguhan.

**Langkah mencobanya:**

1. Daftar akun baru di `http://localhost:8000/register`
2. Anda akan diarahkan ke halaman "Verifikasi Email" — ini normal, bukan error
3. Buka berkas `storage/logs/laravel.log`, gulir ke bagian paling bawah
4. Cari baris berisi `verify-email`. Bentuknya kira-kira:

   ```
   http://localhost:8000/verify-email/1/abc123...?expires=...&signature=...
   ```

5. Salin **seluruh** URL itu — termasuk `?expires=` dan `&signature=`, karena tautannya bertanda tangan dan akan ditolak bila terpotong
6. Tempel di browser. Anda akan diarahkan ke dashboard, dan akun itu kini terverifikasi

Cara cepat mengambil tautannya tanpa membuka berkas log:

```bash
Select-String -Path storage\logs\laravel.log -Pattern "verify-email" | Select-Object -Last 1
```

**Melewati verifikasi saat mengembangkan fitur lain.** Kalau Anda sedang menggarap dashboard dan tidak ingin bolak-balik memverifikasi, buat akun yang langsung terverifikasi lewat tinker:

```bash
php artisan tinker --execute="App\Models\User::factory()->create(['email' => 'saya@contoh.test']);"
```

Factory bawaan membuat akun yang sudah terverifikasi, dengan kata sandi `password`.

**Untuk produksi nanti**, `MAIL_MAILER` diganti ke SMTP sungguhan beserta kredensialnya. Selama masih `log`, tidak ada satu pun email yang benar-benar keluar.

> **Jangan menonaktifkan verifikasi untuk "menyederhanakan" development.** Baris `implements MustVerifyEmail` di `app/Models/User.php` adalah satu-satunya hal yang membuat middleware `verified` berfungsi. Tanpa baris itu, middleware tersebut tetap terpasang di route tetapi meloloskan semua orang — tanpa error, tanpa peringatan. Repo ini sempat berada dalam keadaan tersebut. `tests/Feature/Auth/AccessControlTest.php` sekarang menjaganya; kalau baris itu hilang, testnya langsung merah.

### 9.2 Mencoba alur reset kata sandi

Prinsipnya sama dengan §9.1 — emailnya juga hanya ditulis ke log. Tetapi **tautannya berbeda**, dan ini yang paling sering tertukar:

| | Bentuk tautan |
|---|---|
| Verifikasi email | `/verify-email/{id}/{hash}?expires=…&signature=…` |
| Reset kata sandi | `/reset-password/{token}?email=…` |

Keduanya email yang berbeda untuk keperluan yang berbeda. Verifikasi membuktikan alamat email itu benar milik Anda; reset menggantikan kata sandi yang terlupa.

**Langkah mencobanya:**

1. Buka `http://localhost:8000/forgot-password`
2. Masukkan email akun yang terdaftar, tekan **Kirim Tautan Atur Ulang**
3. Ambil tautannya dari log:

   ```bash
   Select-String -Path storage\logs\laravel.log -Pattern "reset-password" | Select-Object -Last 1
   ```

4. Tempel di browser — halaman pengaturan kata sandi baru akan terbuka dengan email sudah terisi
5. Isi kata sandi baru, simpan. Anda langsung login dengan kata sandi itu

**Tiga hal yang perlu diketahui:**

- **Token berlaku 60 menit.** Diatur di `config/auth.php` (`passwords.users.expire`). Lewat dari itu, tautannya ditolak dan Anda perlu meminta yang baru.
- **Permintaan berulang ditahan 60 detik.** Menekan tombolnya dua kali beruntun tidak menghasilkan email kedua — Laravel menahannya. Kalau tautan baru tidak muncul di log, kemungkinan besar ini penyebabnya, bukan kerusakan.
- **Reset kata sandi tidak menuntut email terverifikasi.** Keduanya alur terpisah: pengguna yang belum memverifikasi email tetap boleh mengatur ulang kata sandinya. Ini memang disengaja — orang yang lupa kata sandi seringkali juga belum sempat memverifikasi.

> **`APP_URL` harus menyertakan port.** Seluruh tautan di email dibangun dari nilai `APP_URL` di `.env`. Bila isinya `http://localhost` tanpa `:8000`, tautan yang tertulis di log tidak akan bisa dibuka — padahal aplikasinya berjalan normal. Nilai yang benar untuk development:
>
> ```
> APP_URL=http://localhost:8000
> ```

---

## 10. Ketika Tidak Sepakat

Untuk keputusan teknis yang tidak selesai dalam 15 menit diskusi: tulis dua opsi beserta konsekuensinya di PR atau issue, pilih satu, catat alasannya di PRD §13. Keputusan yang tercatat dengan alasan bisa ditinjau ulang nanti; keputusan yang hanya diucapkan akan diperdebatkan lagi bulan depan.
