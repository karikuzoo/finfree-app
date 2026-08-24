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

Cakupan Fase 0:

- [ ] Scaffolding `backend/` (Laravel) + `frontend/` (Vite React)
- [ ] `tailwind.config.js` dengan token warna dari CLAUDE.md §4
- [ ] Komponen primitif `ui/` — Button, Input, Card, Badge, Slider
- [ ] Layout shell — Sidebar, Topbar, PageContainer
- [ ] Auth utuh: register, login, logout, Sanctum bearer token, `AuthContext`, protected route
- [ ] Pembungkus API + penanganan error terpusat sesuai CLAUDE.md §10.1
- [ ] **`docs/fixtures/calculator-cases.json`** — test vector kalkulator

Test vector ditulis **berdua**. Di berkas itulah keputusan D-1 (inflasi menaikkan target) dan D-2 (ordinary annuity) berubah dari kalimat di dokumen menjadi angka yang mengikat. Setelah disepakati, siapa pun yang menyentuh rumus tidak bisa menyimpang tanpa ketahuan.

---

## 3. Fase 1 — Pembagian Paralel (Rilis 1)

| | **Dev A — Kalkulator** | **Dev B — Tujuan & Realisasi** |
|---|---|---|
| Backend | `GoalCalculatorService`, `CalculatorController` | `FinancialGoal` CRUD, `GoalContribution`, agregasi progres |
| Frontend | `GoalForm`, `ResultPanel`, `ProjectionChart`, slider "bagaimana jika" | `DashboardPage`, `SummaryCard`, `GoalProgressList`, form catat setoran |
| FR terkait | FR-4..FR-9 | FR-13..FR-15, FR-32..FR-36 |
| Risiko utama | matematika salah tanpa ketahuan | otorisasi bocor antar pengguna |

**Satu-satunya sambungan antar keduanya** adalah bentuk payload yang dikembalikan endpoint `POST /api/goals/{id}/calculate` dan disimpan sebagai tujuan. Sepakati bentuknya di Fase 0, lalu keduanya bisa jalan tanpa saling tunggu.

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

- `backend/routes/api.php`
- `frontend/src/App.jsx` (definisi router)
- `frontend/tailwind.config.js`
- daftar menu di `Sidebar`

Migrasi relatif aman karena bernama timestamp, tapi **jangan pernah mengubah migrasi yang sudah di-merge** — buat migrasi baru.

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
- [ ] Endpoint yang mengakses data tujuan memeriksa kepemilikan terhadap user yang login
- [ ] Ada test untuk logika non-trivial; kode kalkulator wajib lolos `calculator-cases.json`
- [ ] Empty state, loading state, dan error state tertangani (DESIGN.md §9) — bukan hanya jalur sukses
- [ ] Tidak ada nilai uang bertipe float, tidak ada rupiah diformat di backend
- [ ] Dokumen terkait sudah diperbarui bila ada keputusan yang berubah
- [ ] Sudah di-review orang lain

---

## 7. Keamanan — Tidak Bisa Ditawar

- **Jangan commit `.env`.** Pastikan ada di `.gitignore` sebelum commit pertama backend.
- `CURRENTS_API_KEY` tidak boleh muncul di kode frontend maupun di response API.
- Jangan mencatat nominal keuangan pengguna beserta identitasnya ke dalam log.
- Setiap query data tujuan disaring berdasarkan user yang login. Ini titik kebocoran data antar pengguna yang paling mudah terjadi dan paling sulit dimaafkan pada aplikasi keuangan.

---

## 8. Menjalankan Project

Lihat CLAUDE.md §9 untuk perintah setup backend dan frontend.

---

## 9. Ketika Tidak Sepakat

Untuk keputusan teknis yang tidak selesai dalam 15 menit diskusi: tulis dua opsi beserta konsekuensinya di PR atau issue, pilih satu, catat alasannya di PRD §13. Keputusan yang tercatat dengan alasan bisa ditinjau ulang nanti; keputusan yang hanya diucapkan akan diperdebatkan lagi bulan depan.
