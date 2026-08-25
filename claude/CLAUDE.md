# CLAUDE.md — Panduan Konteks Project untuk Claude Code

Dokumen ini adalah konteks kerja untuk Claude (atau developer lain) saat membangun/mengembangkan project **FinGoal**. Baca bersama `PRD.md` (requirement produk) dan `DESIGN.md` (design system) di folder `claude/` yang sama.

> **Catatan migrasi arsitektur (2026-08-25):** versi dokumen ini sebelumnya mengasumsikan React SPA terpisah + Laravel REST API (Sanctum bearer token, dua origin, dua proses dev). Repo yang sebenarnya sudah di-scaffold sebagai **Laravel Breeze + Inertia.js + React** — satu aplikasi, session auth, tanpa lapisan API terpisah. Dokumen ini ditulis ulang mengikuti kenyataan kode yang sudah ada (lihat keputusan **D-9** di §8, menggantikan D-5). Kalau kamu membaca versi lama dokumen ini dari riwayat chat/git, anggap §2, §3, §6.9, §7, §8, §9, dan §10 di bawah ini sebagai versi yang berlaku.

## 1. Ringkasan Project

FinGoal adalah aplikasi web manajemen keuangan pribadi dengan fitur utama **kalkulator tujuan finansial** (dana pensiun, beli rumah, beli kendaraan, dana darurat, dana pendidikan) yang menghasilkan nominal setoran bulanan yang dibutuhkan beserta **rekomendasi alokasi instrumen investasi** (saham, reksa dana, obligasi/SBN, deposito, emas). Dilengkapi dashboard progres tujuan dan modul berita finansial dari Currents API.

Tema visual: **"Malam"** — near-black dipadu lime listrik, dark-first (lihat `DESIGN.md` untuk token warna & komponen).

Dibangun sebagai **satu aplikasi Laravel + Inertia**, bukan dua project terpisah (frontend SPA dan backend API). React tetap dipakai penuh untuk seluruh UI, tapi routing, auth, dan pengiriman data diatur lewat Laravel & Inertia, bukan lewat fetch/axios ke endpoint JSON.

## 2. Tech Stack

| Layer | Teknologi |
|---|---|
| Full-stack framework | **Laravel 12 + Inertia.js v2 (React 18)** — satu aplikasi, bukan SPA+API terpisah |
| Rendering halaman | React 18, komponen halaman di `resources/js/Pages/**`, navigasi lewat `<Link>`/`router` dari Inertia — **bukan React Router** |
| Styling | Tailwind CSS v3 (config-based, lihat §4) |
| Komponen UI dasar | `@headlessui/react` (sudah terpasang lewat Breeze) |
| State management | State lokal React + Inertia shared props (`usePage().props`) untuk data user login & flash message. Context/Zustand hanya dipakai kalau benar-benar ada state lintas halaman yang tidak cocok dikirim sebagai props |
| Charting | Recharts atau Chart.js (belum terpasang, perlu `npm install`) |
| Icon | lucide-react (belum terpasang, perlu `npm install`) |
| Backend | Laravel 12, **PHP 8.4** (dikunci di `composer.json`: `"php": "^8.4"`) |
| Auth | **Laravel Breeze, guard `web` (session/cookie)** — bukan Sanctum bearer token. Lihat D-9 di §8. Verifikasi email **aktif** (`User implements MustVerifyEmail`); cara mencobanya di CONTRIBUTING.md §9.1 |
| Database | **PostgreSQL 17** (`.env.example` sudah `pgsql`, ekstensi `ext-pdo_pgsql` diwajibkan di `composer.json`) |
| Node | **22 LTS**, minimum 20.19 — dikunci di `package.json` → `engines` (syarat Vite 7) |
| Cache (opsional) | Redis atau tabel cache di PostgreSQL |
| API eksternal | Currents API (currentsapi.services) — free tier, untuk modul News |
| Queue/Scheduler | Laravel Scheduler + Queue. `composer run dev` sudah menjalankan `queue:listen` — lihat §9 |
| Routing tambahan | Semua route (termasuk kalkulator utilitas publik) didaftarkan sebagai route Inertia biasa di `routes/web.php`. **Tidak ada `routes/api.php`** — lihat D-9 |

## 3. Struktur Repository (mengikuti struktur nyata di repo)

```
finfree-app/                          # satu project Laravel+Inertia, bukan dua folder terpisah
├── app/
│   ├── Http/
│   │   ├── Controllers/
│   │   │   ├── Auth/                              # bawaan Breeze — jangan diubah strukturnya
│   │   │   ├── ProfileController.php               # bawaan Breeze
│   │   │   ├── DashboardController.php             # BARU — Inertia::render + DashboardSummaryService (§6.9)
│   │   │   ├── FinancialGoalController.php         # BARU
│   │   │   ├── GoalContributionController.php      # BARU
│   │   │   ├── CalculatorController.php            # BARU — kalkulator tujuan
│   │   │   ├── UtilityCalculatorController.php     # BARU — pinjaman/KPR & investasi (FR-41,42), tanpa auth
│   │   │   ├── InvestmentRecommendationController.php  # BARU
│   │   │   └── NewsController.php                  # BARU
│   │   └── Middleware/
│   │       └── HandleInertiaRequests.php           # bawaan Breeze — taruh shared props (user login dsb) di sini
│   ├── Enums/
│   │   └── RiskProfile.php         # sumber kebenaran nilai profil risiko — lihat §5
│   ├── Models/
│   │   ├── User.php                # sudah punya kolom preferensi sesuai §5
│   │   ├── FinancialGoal.php
│   │   ├── GoalContribution.php
│   │   ├── GoalCalculation.php
│   │   ├── InvestmentInstrument.php
│   │   └── NewsArticleCache.php
│   ├── Services/
│   │   ├── GoalCalculatorService.php       # rumus future value of annuity
│   │   ├── DashboardSummaryService.php     # agregasi lintas goals milik satu user (§6.9)
│   │   ├── InvestmentAllocationService.php # rule-based recommendation engine
│   │   └── CurrentsNewsService.php         # fetch + cache Currents API
│   └── Jobs/
│       └── FetchLatestNewsJob.php
├── database/
│   ├── migrations/
│   └── seeders/               # default rate return/inflasi per kategori, master instrumen investasi
├── resources/
│   ├── css/app.css
│   └── js/
│       ├── Components/         # bawaan Breeze (PrimaryButton, Modal, Dropdown, dst) — pakai ulang, jangan duplikat
│       │   └── ui/              # BARU — SummaryCard, ProgressBar, Badge dst sesuai DESIGN.md
│       ├── Layouts/
│       │   ├── AuthenticatedLayout.jsx   # bawaan — di sinilah Sidebar+Topbar DESIGN.md §4 ditempatkan
│       │   └── GuestLayout.jsx           # bawaan — dipakai halaman Auth/*
│       ├── Pages/
│       │   ├── Auth/            # bawaan Breeze (Login, Register, dll) — JANGAN dibongkar
│       │   ├── Profile/         # bawaan Breeze
│       │   ├── Dashboard.jsx    # bawaan (masih kosong) — halaman pertama yang diisi
│       │   ├── Goals/           # BARU — Index.jsx, Show.jsx
│       │   ├── Calculator/      # BARU — Index.jsx + sub-halaman per kategori tujuan
│       │   └── News/            # BARU — Index.jsx
│       └── app.jsx              # entry point Inertia — sudah ada, jangan diubah kecuali menambah provider
├── routes/
│   ├── web.php          # SEMUA route halaman (Inertia::render) didaftarkan di sini
│   ├── auth.php         # bawaan Breeze
│   └── (routes/api.php TIDAK ADA dan sengaja tidak dibuat — lihat D-9)
├── tailwind.config.js
└── claude/
    ├── CLAUDE.md         # dokumen ini
    ├── PRD.md
    ├── DESIGN.md
    ├── CONTRIBUTING.md
    └── README.md
```

## 4. Tailwind Theme Mapping (dari DESIGN.md)

Konfigurasi Tailwind sudah ada di root: `tailwind.config.js`, memakai Tailwind v3 (config-based, bukan CSS `@theme` v4) — pastikan tidak tercampur dengan paket `@tailwindcss/vite` v4 yang ikut ter-list di `package.json` devDependencies tapi **tidak dipakai** oleh `vite.config.js` saat ini; abaikan/boleh dihapus paket itu agar tidak membingungkan.

Breeze memasang font default **Figtree** di `theme.extend.fontFamily.sans` — ganti ke `Plus Jakarta Sans`/`Inter` sesuai DESIGN.md sebelum mulai styling halaman FinGoal.

Tambahkan token warna berikut ke `tailwind.config.js` (`theme.extend.colors`):

```js
colors: {
  bg: {
    base: '#0B0C0B',      // latar halaman
    surface: '#101210',   // topbar, sidebar
    card: '#16181A',
    cardAlt: '#252825',   // hover, panel hasil, skeleton
  },
  border: {
    DEFAULT: '#282C28',   // garis pemisah dekoratif saja
    strong: '#666D61',    // WAJIB untuk border input & tombol outline (3.33:1)
  },
  lime: {
    400: '#DEF76F',       // hover
    500: '#CFF04A',       // aksen utama
    600: '#A8C531',       // active
    softBg: '#1E2610',    // chip, badge, banner
  },
  onPrimary: '#10130A',   // teks di atas fill lime
  text: {
    primary: '#F0F1EC',
    secondary: '#A3A99E',
    muted: '#8B917F',
    disabled: '#5A6057',  // hanya elemen disabled, 2.75:1 — bukan untuk teks aktif
  },
  state: {
    success: '#5FD69A',   // mint, sengaja beda hue dari lime
    danger: '#F0705F',
    warning: '#F2B950',
    info: '#7CB8E8',
  },
}
```

Dua aturan yang mudah dilanggar dan sulit diperbaiki belakangan:

1. **Lime bukan warna "positif".** Ia warna aksi. Kenaikan nilai memakai `state.success` (mint). Karena lime dan hijau berada di keluarga yang sama, memakai lime untuk delta positif membuat tombol dan angka saling berebut perhatian.
2. **`border.DEFAULT` tidak boleh jadi batas field.** Kontrasnya 1.26:1 — cukup untuk garis pemisah, tidak cukup untuk menandai di mana sebuah input berakhir. Pakai `border.strong`.

Gunakan `font-variant-numeric: tabular-nums` untuk seluruh komponen angka (buat utility class `.num-tabular`); untuk angka hasil utama pertimbangkan font mono — di tema gelap angka mono terbaca lebih tegas.

## 5. Domain Model / Skema Database Inti (PostgreSQL)

Tipe kolom ditulis eksplisit karena kolom uang yang tidak ditentukan tipenya hampir selalu berakhir sebagai `float` — lihat §6.5.

```
users                                          -- SUDAH DIIMPLEMENTASIKAN
  id, name, email (unique), email_verified_at, password,
  risk_profile (enum: conservative|moderate|aggressive, default 'moderate'),
  currency_preference (char(3), default 'IDR'),
  prefers_syariah (boolean, default false),
  avatar_path (string, nullable),              -- jalur berkas, BUKAN isi gambar
  created_at, updated_at
  -- TANPA deleted_at. Rancangan awal mencantumkannya, tetapi soft delete
  -- berarti data pengguna tetap tersimpan setelah akun "dihapus" — dan itu
  -- bertentangan dengan FR-37 yang menuntut penghapusan permanen sebagai
  -- pemenuhan hak penghapusan UU 27/2022 PDP. Hapus akun = hard delete.
  --
  -- Nilai enum risk_profile TIDAK ditulis sebagai string di mana pun:
  -- sumbernya App\Enums\RiskProfile, dipakai oleh definisi kolom migrasi,
  -- aturan validasi, cast model, dan daftar pilihan di UI. Kolom
  -- financial_goals.risk_profile_override (FR-24) WAJIB memakai enum yang
  -- sama. ProfilePreferenceTest mengunci daftar nilainya.

financial_goals
  id, user_id (FK, on delete cascade),
  type (enum: retirement|house|vehicle|emergency|education|custom),
  name, target_amount numeric(18,2), initial_amount numeric(18,2),
  target_date date NULL,                      -- NULL untuk dana darurat (tanpa tenggat)
  estimated_return_rate numeric(5,2),         -- persen, mis. 7.50
  estimated_inflation_rate numeric(5,2),
  risk_profile_override (enum, nullable)      -- FR-24
  status (enum: active|achieved|archived),
  created_at, updated_at, deleted_at
  INDEX (user_id, status)

goal_contributions                            -- prasyarat FR-32..FR-36
  id, financial_goal_id (FK, on delete cascade),
  amount numeric(18,2), contributed_on date, note text NULL,
  created_at, updated_at
  INDEX (financial_goal_id, contributed_on)
  -- current_amount TIDAK disimpan sebagai kolom: ia = initial_amount + SUM(amount).
  -- Bila agregasi jadi lambat, barulah tambahkan kolom cache yang di-update via event.

goal_calculations
  id, financial_goal_id (FK, on delete cascade),
  monthly_contribution_required numeric(18,2),
  total_contribution_projection numeric(18,2),
  total_investment_growth_projection numeric(18,2),
  calculation_snapshot jsonb,                 -- seluruh parameter + versi rumus + hasil
  formula_version smallint,                   -- agar hasil lama tetap bisa dijelaskan
  created_at
  INDEX (financial_goal_id, created_at DESC)

investment_instruments
  id, name, category (enum: stock|mutual_fund|bond|deposit|gold),
  risk_level (enum: low|medium|high),
  estimated_return_min numeric(5,2), estimated_return_max numeric(5,2),
  tax_treatment (enum: none|final_withholding|transaction_levy),   -- FR-25
  tax_rate numeric(5,2) NULL,
  is_syariah boolean default false,                                -- FR-27
  liquidity (enum: high|medium|low),           -- penting untuk dana darurat
  description, rates_as_of date, rates_source text                 -- D-7

goal_recommended_allocations
  id, goal_calculation_id (FK),                -- menempel ke KALKULASI, bukan ke tujuan
  investment_instrument_id (FK),
  allocation_percentage numeric(5,2)
  -- Menempel ke kalkulasi membuat alokasi ikut ter-snapshot: saat aturan alokasi
  -- diubah admin, hasil lama tetap bisa direproduksi. Lihat keputusan D-6 di PRD.

news_article_cache
  id, source, category, title, summary, url, image_url,
  published_at, fetched_at
  UNIQUE (url)                                 -- WAJIB: job berjalan tiap 30-60 menit
                                               -- dan akan mengambil artikel yang sama
                                               -- berulang kali (FR-29)
  INDEX (category, published_at DESC)
```

Migrasi wajib memasang `ON DELETE CASCADE` dari `users` ke bawah agar FR-37 (hapus akun) benar-benar menghapus seluruh jejak data.

## 6. Kalkulator — Catatan Implementasi

### 6.1 Rumus
Gunakan **Future Value of an Ordinary Annuity** untuk mencari setoran bulanan (`PMT`).

```
n  = jumlah bulan dari sekarang sampai target_date
i  = imbal hasil per bulan (lihat 6.2)
PV = current_amount (dana awal yang sudah dimiliki)
FV = target_amount setelah penyesuaian inflasi (lihat 6.3)

PMT = (FV − PV × (1+i)^n) × i / ((1+i)^n − 1)
```

### 6.2 Konversi rate tahunan → bulanan
Tetapkan satu konvensi dan konsisten di seluruh sistem. Rekomendasi: perlakukan `estimated_return_rate` sebagai **effective annual rate**, sehingga

```
i = (1 + r_tahunan)^(1/12) − 1
```

Bukan `r/12`. Keduanya memberi hasil berbeda, dan selisihnya membesar untuk jangka panjang seperti dana pensiun. Tulis konvensi yang dipilih di komentar service dan kunci lewat test vector.

### 6.3 Inflasi — jangan dihitung dua kali
Ada dua pendekatan yang sama benar, tetapi **hanya boleh dipakai salah satu**:

- **A. Naikkan target:** `FV = target_amount × (1 + inflasi)^tahun`, lalu pakai return nominal sebagai `i`.
- **B. Pakai real return:** `FV = target_amount` (nilai hari ini), lalu `i` diturunkan dari `real = (1+return)/(1+inflasi) − 1`.

Memakai keduanya sekaligus adalah bug paling umum pada kalkulator jenis ini dan membuat setoran bulanan tampak jauh lebih besar dari seharusnya.

> **Diputuskan (D-1): pakai pendekatan A.** Nominal masa depan terlihat oleh pengguna sehingga lebih mudah dijelaskan. Pastikan `estimated_return_rate` yang masuk ke `i` adalah return **nominal**, bukan yang sudah dipotong inflasi. Tambahkan satu test vector khusus yang akan gagal bila suatu saat ada yang memotong return dengan inflasi juga.
>
> **Diputuskan (D-2): ordinary annuity** (setoran akhir bulan) — hasilnya sedikit lebih konservatif daripada *annuity due*. Tampilkan asumsi ini di panel hasil agar pengguna tahu.

### 6.4 Kasus batas yang wajib ditangani
| Kondisi | Perilaku |
|---|---|
| `i = 0` (return 0%) | Rumus utama membagi nol. Pakai `PMT = (FV − PV) / n`. |
| `PV × (1+i)^n ≥ FV` | Target sudah tercapai tanpa setoran → `PMT = 0`, tampilkan pesan khusus, jangan tampilkan angka negatif. |
| `n ≤ 0` (tanggal target hari ini/lampau) | Tolak di validasi, bukan di perhitungan. |
| `n = 1` | Harus tetap benar; masukkan ke test vector. |
| Nilai sangat besar | `(1+i)^n` untuk jangka 40 tahun tetap aman di float64, tetapi hasil akhir dibulatkan ke rupiah penuh. |

### 6.5 Aturan uang
- **Jangan gunakan tipe floating point untuk kolom uang.** Pakai `numeric(18,2)` di PostgreSQL, dan `bcmath`/integer minor unit di PHP.
- Perhitungan boleh memakai float di dalam service, tetapi hasil akhir dibulatkan **ke atas** ke rupiah penuh untuk setoran bulanan — membulatkan ke bawah membuat target meleset tipis.
- Persentase (return, inflasi, alokasi) disimpan sebagai `numeric(5,2)` dalam satuan persen (mis. `7.50`), bukan desimal `0.075`. Tetapkan satu konvensi dan patuhi di seluruh sistem.

### 6.6 Sumber kebenaran & duplikasi rumus
Implementasikan logika di `GoalCalculatorService.php` (backend) sebagai *source of truth*. Frontend boleh menduplikasi rumus untuk preview real-time (FR-8), dengan syarat:

- Berkas **test vector bersama** ada di `docs/fixtures/calculator-cases.json`, berisi pasangan input→output yang sudah diverifikasi manual.
- **Sudah diimplementasikan.** Salinan JS-nya di `resources/js/utils/goalCalculator.js`, diuji `resources/js/utils/goalCalculator.test.mjs` terhadap berkas yang sama:

  ```bash
  npm run test:js
  ```

  Memakai test runner bawaan Node (`node --test`), tanpa paket tambahan. **Jalankan bersama `php artisan test` setiap kali menyentuh rumus** — kalau hanya salah satu yang dijalankan, kedua implementasi bisa berbeda tanpa ada yang tahu.
- Salinan JS juga menyediakan dua hal yang tidak ada di backend karena hanya dibutuhkan untuk interaksi: `solveMonths()` (arah sebaliknya — berapa lama bila setorannya sekian, diselesaikan dengan pencarian biner karena inflasi membuat `n` muncul di kedua sisi persamaan) dan `projectionSeries()` untuk grafik.
- Nilai yang **disimpan** selalu hasil dari backend, dikirim lewat `useForm().post(route('goals.calculations.store', goal))` (Inertia form helper) — bukan `fetch`/`axios` manual ke endpoint JSON.

### 6.7 Mesin alokasi
`InvestmentAllocationService.php` — pemetaan berbasis aturan dari jangka waktu & profil risiko → persentase alokasi instrumen (PRD FR-10). Catatan:

- Simpan aturan sebagai konfigurasi (`config/allocation_rules.php` atau tabel), bukan hardcode.
- Validasi bahwa total alokasi setiap aturan = 100%; jadikan ini unit test, bukan asumsi.
- Keluarkan juga **blended expected return** = Σ (alokasi% × return instrumen) untuk dipakai sebagai default `estimated_return_rate` (PRD FR-23). Tanpa ini, kalkulator dan panel rekomendasi menjadi dua fitur yang tidak saling bicara.
- Profil risiko dibaca dari tujuan lebih dulu, baru jatuh ke profil akun (PRD FR-24).

### 6.8 Kalkulator utilitas — bangun di atas mesin yang sama
Kalkulator Pinjaman/KPR (FR-41) dan Investasi (FR-42) memakai keluarga rumus yang sama dengan kalkulator tujuan. **Jangan tulis mesin hitung kedua.**

- **Kalkulator Investasi** adalah arah maju dari rumus yang sudah ada: `PMT` diketahui, `FV` dicari. `FV = PV×(1+i)^n + PMT × ((1+i)^n − 1) / i`. Kasus `i = 0` tetap wajib ditangani.
- **Kalkulator Pinjaman** adalah anuitas juga, hanya berpindah sisi: `angsuran = P × i / (1 − (1+i)^−n)`. Tabel amortisasi dihitung iteratif per bulan (bunga = sisa pokok × i, pokok = angsuran − bunga), dan **saldo akhir harus tepat nol** — selisih pembulatan dibebankan ke angsuran terakhir. Ini kasus uji wajib.
- Konvensi konversi rate (§6.2) dan aturan uang (§6.5) berlaku sama. Mockup awal sudah menampilkan "Sistem Perhitungan: Anuitas Efektif" — pertahankan, metode hitung yang terbuka adalah pembeda kepercayaan yang murah.
- **Route** kalkulator utilitas (`/kalkulator/pinjaman`, `/kalkulator/investasi` di `UtilityCalculatorController`) didaftarkan **di luar** grup middleware `auth` di `routes/web.php` — halaman Inertia biasa yang bisa diakses tanpa login (PRD FR-44), bukan endpoint JSON terpisah. Pasang middleware `throttle` Laravel di grup route ini: halaman publik tanpa batas laju tetap jadi beban gratis bagi siapa pun yang ingin menyalahgunakannya.

### 6.9 Ringkasan Dashboard — agregasi di backend, dikirim lewat props Inertia

> **Diputuskan (D-8): agregasi dashboard dihitung di backend**, bukan frontend menjumlahkan sendiri daftar goals yang diterima. Frontend hanya menampilkan apa yang diterima sebagai props.

Alasan (tetap berlaku terlepas dari mekanisme pengiriman datanya):
- **Satu sumber kebenaran.** Rumus "total aset" (`initial_amount + SUM(goal_contributions.amount)` per goal, lalu dijumlahkan lintas goal) sudah didefinisikan di §5 untuk `goal_contributions`. Menghitungnya ulang di JavaScript berarti dua implementasi dari rumus yang sama — persis masalah yang coba dihindari di §6.6 untuk kalkulator.
- **Keamanan kepemilikan data.** Agregasi backend otomatis terikat ke `auth()->user()` dari sesi yang login (lihat §10.1). Kalau frontend menjumlahkan dari daftar goals, ia harus menerima *seluruh* baris goals dulu sebagai props — boros payload untuk sekadar angka ringkasan.
- **Konsistensi dengan pola servis yang sudah ada.** `GoalCalculatorService` dan `InvestmentAllocationService` sama-sama backend-first (§6.6, §6.7); `DashboardSummaryService` mengikuti pola yang sama, bukan pengecualian.
- Menghindari duplikasi agregasi *time-series* untuk grafik pertumbuhan aset (FR-14), yang butuh `GROUP BY` bulanan atas `goal_contributions` — lebih murah dilakukan satu kali sebagai query SQL daripada di-reduce dari array besar di client.

**Bukan endpoint JSON terpisah** — `DashboardController@index` memanggil service lalu mengirim hasilnya sebagai props ke halaman Inertia:

```php
// app/Http/Controllers/DashboardController.php
public function index(DashboardSummaryService $summary)
{
    return Inertia::render('Dashboard', [
        'summary' => $summary->forUser(auth()->user()),
    ]);
}
```

Bentuk `summary` (dipakai langsung sebagai `props.summary` di `Dashboard.jsx`):

```jsonc
{
  "total_assets": 15750000,              // Σ (initial_amount + SUM(contributions)) goals aktif
  "total_target": 450000000,             // Σ target_amount goals aktif
  "overall_progress_percentage": 3.50,
  "active_goals_count": 3,
  "goals": [                             // ringkasan per goal untuk GoalProgressList
    {
      "id": 12,
      "name": "DP Rumah",
      "type": "house",
      "current_amount": 15000000,
      "target_amount": 200000000,
      "progress_percentage": 7.50,
      "target_date": "2029-06-01"
    }
  ],
  "asset_growth_series": [               // untuk AssetGrowthChart, sudah di-agregasi per bulan
    { "month": "2026-06", "cumulative_amount": 5000000 },
    { "month": "2026-07", "cumulative_amount": 9750000 }
  ],
  "recent_activity": [                   // FR-15, gabungan goal_calculations & goal_contributions terbaru
    { "type": "contribution_recorded", "goal_name": "DP Rumah", "amount": 2500000, "occurred_at": "2026-08-20T09:00:00Z" },
    { "type": "goal_calculation_completed", "goal_name": "Dana Darurat", "occurred_at": "2026-08-18T14:22:00Z" }
  ]
}
```

- Route `/dashboard` sudah ada dari Breeze di dalam grup middleware `auth` — **jangan** dikeluarkan dari grup itu. Karena Laravel yang menjaga akses (bukan pengecekan token di frontend), pengguna belum login otomatis diarahkan ke halaman login, bukan menerima 401 JSON.
- Bila `active_goals_count = 0`, `DashboardSummaryService` tetap mengembalikan struktur yang sama dengan array/nilai kosong (bukan melempar exception atau `null`) — `Dashboard.jsx` memakainya sebagai sinyal untuk menampilkan empty state (DESIGN.md §9.1), bukan error state (§9.3).
- `asset_growth_series` dan `recent_activity` masing-masing dibatasi (mis. 12 bulan terakhir, 10 aktivitas terakhir) di dalam service — props Inertia dikirim utuh setiap render halaman, jadi jangan biarkan array ini tumbuh tanpa batas.
- Uji `DashboardSummaryService` dengan kasus: user tanpa goals, goals dengan `target_date` `NULL` (dana darurat, tidak masuk hitungan "progress ke tanggal"), dan goals lintas beberapa `status` (pastikan hanya `active` yang masuk agregasi utama, `achieved`/`archived` dikecualikan kecuali diminta eksplisit).

## 7. Integrasi Currents API

- Endpoint: `https://api.currentsapi.services/v1/search` (free tier — perhatikan rate limit harian). **Verifikasi domain ini di dokumentasi resmi sebelum implementasi** — dokumen versi awal sempat menulis `api.currentsapi.io`, yang tidak sama dengan `currentsapi.services` yang disebut di tabel tech stack.
- Currents adalah API **berita umum dunia**, bukan API keuangan Indonesia. Konsekuensi yang harus diantisipasi: cakupan berita finansial berbahasa Indonesia kemungkinan tipis, dan kategori pada FR-17 tidak tersedia dari sumber sehingga harus diklasifikasi sendiri saat ingest (FR-28). Uji kualitas hasil pencarian lebih dulu dengan beberapa kata kunci nyata sebelum modul News dianggap layak rilis; siapkan rencana cadangan (RSS media ekonomi lokal) bila hasilnya kurang.
- Backend melakukan fetch berkala via `FetchLatestNewsJob` (Laravel Scheduler, misal tiap 30–60 menit) dan menyimpan ke tabel `news_article_cache`, bukan fetch langsung tiap request pengguna.
- `NewsController@index` membaca dari `news_article_cache` dan mengirimkannya sebagai props Inertia ke halaman News — React **tidak pernah** memanggil Currents API atau endpoint `/api/news` sendiri lewat fetch/axios (tidak ada endpoint semacam itu). `CURRENTS_API_KEY` hanya pernah disentuh oleh job backend, tidak pernah terkirim ke browser dalam bentuk apa pun.
- Sediakan fallback: jika fetch job gagal (limit habis/error), `NewsController` tetap mengirim props dari cache terakhir + `stale: true`, ditampilkan sebagai banner di halaman.

## 8. Environment Variables

Satu `.env` di root project — **tidak ada `.env` terpisah untuk frontend**, karena Vite hanya bertugas sebagai asset bundler yang diintegrasikan lewat `@vite` (Blade) dan `createInertiaApp` (JS), selalu satu origin dengan Laravel.

> **Diputuskan (D-9, menggantikan D-5): auth pakai guard `web` (session/cookie) bawaan Breeze, bukan Sanctum bearer token.** Premis D-5 sebelumnya (frontend beda origin dari API, butuh CORS berkredensial) tidak berlaku lagi karena arsitektur berubah dari SPA+API terpisah menjadi Laravel+Inertia satu origin. CSRF ditangani otomatis oleh middleware Laravel standar bersama axios instance bawaan Inertia (otomatis mengirim header `X-XSRF-TOKEN` dari cookie `XSRF-TOKEN`).
>
> **Jangan pasang ulang** `SANCTUM_STATEFUL_DOMAINS`, `SESSION_DOMAIN` custom, `FRONTEND_URL`, atau konfigurasi CORS — semua itu penanda pola SPA terpisah yang sudah tidak dipakai dan hanya akan menyesatkan pembaca berikutnya. Paket `laravel/sanctum` tetap ada di `composer.json` (bawaan Breeze) tapi menganggur untuk saat ini; baru relevan kalau kelak dibutuhkan token API untuk klien non-browser (mis. app mobile native — di luar lingkup MVP per README).
>
> **Tindak lanjut:** baris keputusan **D-5 di `PRD.md` §13** mencatat keputusan lama ini dan perlu diperbarui juga supaya kedua dokumen tidak saling bertentangan — lihat catatan di bagian akhir dokumen ini.

**Kondisi sekarang:** `.env.example` sudah diperbarui ke `APP_NAME=FinGoal` dan `DB_CONNECTION=pgsql` dengan kredensial PostgreSQL default. Variabel `CURRENTS_*` belum ditambahkan karena modul News baru dikerjakan di Rilis 3.

**`.env` lengkap untuk development FinGoal:**
```
APP_NAME=FinGoal
APP_ENV=local
APP_URL=http://localhost:8000

DB_CONNECTION=pgsql
DB_HOST=127.0.0.1
DB_PORT=5432
DB_DATABASE=fingoal
DB_USERNAME=postgres
DB_PASSWORD=

CURRENTS_API_KEY=xxxx
CURRENTS_API_BASE_URL=https://api.currentsapi.services/v1
CURRENTS_FETCH_INTERVAL_MINUTES=60
NEWS_CACHE_RETENTION_DAYS=30

SESSION_DRIVER=database
QUEUE_CONNECTION=database
CACHE_STORE=database
```

Mengganti `.env` di atas (terutama `DB_CONNECTION`, `APP_NAME`, dan variabel `CURRENTS_*`) adalah hal pertama yang perlu dilakukan sebelum development sungguhan dimulai — bukan sekadar catatan referensi di dokumen ini.

## 9. Perintah Umum

Satu project, satu perintah setup — tidak ada lagi dua terminal terpisah (backend & frontend):

```bash
composer install
npm install
cp .env.example .env      # lalu edit sesuai §8: DB_*, APP_NAME, CURRENTS_*
php artisan key:generate
php artisan migrate --seed

# Jalankan semuanya sekaligus — sudah disediakan lewat script "dev" di composer.json:
composer run dev
```

`composer run dev` menjalankan **3 proses** bersamaan lewat `concurrently`: `php artisan serve`, `php artisan queue:listen --tries=1 --timeout=0`, dan `npm run dev` (Vite). Aplikasi ada di `http://localhost:8000`; port 5173 adalah server aset Vite, bukan alamat aplikasi.

### Dua penyesuaian khusus Windows
Keduanya sudah ada di repo — disebutkan agar tidak dikira kesalahan dan tidak dibatalkan tanpa sengaja:

1. **`laravel/pail` dikeluarkan dari skrip `dev`.** Pail membutuhkan ekstensi `pcntl` yang tidak tersedia di Windows, dan karena `concurrently` memakai `--kill-others`, matinya Pail menyeret server, queue, dan Vite ikut berhenti. Pengguna macOS/Linux tetap bisa menjalankan `php artisan pail` di terminal terpisah.
2. **`AppServiceProvider` mendaftarkan `SystemRoot` dkk ke `ServeCommand::$passthroughVariables`.** `ServeCommand` membuang env var yang tidak terdaftar, dan daftar bawaan Laravel menulis `SYSTEMROOT` huruf besar sementara Windows memakai `SystemRoot`; karena `in_array()` peka huruf besar-kecil, variabelnya terbuang. Tanpa `SystemRoot`, winsock gagal membuka socket dan `artisan serve` melapor `Failed to listen on 127.0.0.1:8000 (reason: ?)` di semua port. Kode ini dibungkus `PHP_OS_FAMILY === 'Windows'` sehingga tidak aktif di Linux/macOS.

Untuk menjalankan job fetch berita terjadwal (`FetchLatestNewsJob`) selama development, jalankan `php artisan schedule:work` di terminal tambahan — belum termasuk di script `composer run dev` bawaan.

## 10. Konvensi Kode

- **Backend:** ikuti konvensi Laravel standar (PSR-12), gunakan Form Request untuk validasi. Kelas API Resource opsional — tetap berguna untuk merapikan bentuk props yang dikirim ke `Inertia::render`, terutama untuk data koleksi besar seperti daftar goals, tapi tidak wajib seperti pada arsitektur REST API murni.
- **Frontend:** komponen fungsional React + hooks, satu komponen per file, styling murni via Tailwind utility classes (hindari inline style kecuali untuk nilai dinamis seperti progress bar width). Halaman di `resources/js/Pages/**` menerima data lewat **props dari controller**, bukan lewat pemanggilan API manual di `useEffect`/axios — kalau ada komponen halaman yang mem-fetch datanya sendiri, itu tanda arsitekturnya keliru arah (kembali ke pola SPA lama yang sudah tidak dipakai).
- **Penamaan route:** route Laravel biasa di `routes/web.php`, konvensi resource controller standar, semua route diberi `->name(...)` karena frontend memanggilnya lewat `route()` (Ziggy, sudah terpasang via `tightenco/ziggy`), bukan hardcode string path. Contoh:
  ```php
  Route::middleware('auth')->group(function () {
      Route::get('/goals', [FinancialGoalController::class, 'index'])->name('goals.index');
      Route::get('/goals/create', [FinancialGoalController::class, 'create'])->name('goals.create');
      Route::post('/goals', [FinancialGoalController::class, 'store'])->name('goals.store');
      Route::get('/goals/{goal}', [FinancialGoalController::class, 'show'])->name('goals.show');
  });

  Route::get('/kalkulator/pinjaman', [UtilityCalculatorController::class, 'loan'])->name('calculator.loan');
  ```
- **Bahasa UI:** Bahasa Indonesia (mengikuti referensi produk), format angka menggunakan pemisah ribuan titik dan mata uang `Rp`.

### 10.1 Kontrak Props Inertia

Karena hampir seluruh transfer data lewat **props Inertia** (bukan response JSON yang di-fetch terpisah oleh frontend), "kontrak" yang perlu disepakati di awal adalah **bentuk props tiap halaman**, bukan bentuk endpoint REST. Tulis daftar props sebuah halaman sebagai bagian dari perencanaan halaman itu (lihat §6.9 untuk contoh Dashboard) sebelum Controller dan `Page.jsx`-nya dikerjakan paralel — prinsipnya sama seperti kontrak API pada arsitektur lama, cuma lokasinya berpindah dari dokumen endpoint ke bentuk props.

Aturan yang tetap berlaku:
- Uang dikirim sebagai **angka**, bukan string terformat. Pemformatan `Rp` adalah urusan frontend.
- Tanggal memakai format ISO 8601.
- Persentase dikirim dalam satuan persen (`7.5`), konsisten dengan penyimpanan di DB.
- Response berita menyertakan `stale: true` beserta `fetched_at` bila cache tidak segar (PRD NFR-4).

Yang berubah dari versi arsitektur lama:
- **Paginasi** cukup lempar Laravel paginator langsung sebagai prop (`goals: $paginator`) — frontend membaca `.data`, `.links`, `.meta` dari situ. Bentuk ini datang otomatis dari Laravel, tidak perlu didesain manual seperti kontrak `{ data, meta }` versi API lama.
- **Validasi gagal** ditangkap otomatis oleh Inertia lewat redirect-back + `errors` bag, dibaca lewat `useForm().errors` di komponen React. Ini **menggantikan** pola respons `422` + `{ message, errors }` yang didokumentasikan sebelumnya — kalau memakai `useForm()` dari `@inertiajs/react`, penanganan error sudah otomatis, jangan dibuat ulang manual.
- **Kepemilikan data** tetap sama pentingnya — gunakan Policy Laravel (`Gate::authorize(...)` atau `authorize()` di Form Request) di tiap controller method. Kegagalan otorisasi otomatis menghasilkan halaman error 403 (ditangani Inertia), bukan body JSON `{ message: "..." }` yang perlu di-parse manual seperti sebelumnya. Selalu periksa kepemilikan tujuan terhadap user yang login — ini titik rawan kebocoran data antar pengguna, terlepas dari arsitekturnya.

### 10.2 Konvensi Penanganan Error Form

Disepakati agar setiap form di aplikasi ini berperilaku sama. Implementasi acuannya ada di `resources/js/Pages/Calculator/Goal.jsx` dan `app/Http/Controllers/GoalCalculatorController.php` — tiru pola itu, jangan mengarang pola baru.

**Di backend**

1. Validasi **selalu** di backend, meskipun frontend sudah membatasi lewat `min`/`max` pada input. Batasan di frontend adalah kenyamanan, bukan pengamanan — siapa pun bisa mengirim request langsung tanpa lewat halaman.
2. Tulis pesan error dalam **Bahasa Indonesia** secara eksplisit. Pesan bawaan Laravel berbahasa Inggris dan menyebut nama kolom mentah (`target_amount`), yang tidak berarti apa-apa bagi pengguna.
3. Pesan menjelaskan **apa yang harus dilakukan**, bukan sekadar menyatakan yang salah. "Jangka waktu minimal 1 bulan" lebih berguna daripada "Jangka waktu tidak valid".
4. Untuk form yang lebih dari beberapa field, pindahkan aturannya ke Form Request agar controller tetap ringkas.

**Di frontend**

1. Selalu pakai `useForm()` dari `@inertiajs/react`. **Jangan** membuat state error sendiri dengan `useState` — Inertia sudah mengisi `form.errors` otomatis dari redirect-back Laravel.
2. Setiap field diikuti `<InputError message={form.errors.nama_field} />` tepat di bawahnya. Komponen itu tidak menampilkan apa pun bila tidak ada error, jadi aman ditulis selalu.
3. Tombol submit memakai `disabled={form.processing}` dan teksnya berubah selama proses ("Menghitung…", "Menyimpan…"). Tanpa itu pengguna akan menekan tombolnya dua kali.
4. **Jangan** menampilkan pesan error mentah dari server ke pengguna (DESIGN.md §9.3). Yang boleh tampil hanya pesan yang memang ditulis untuk dibaca manusia.

```jsx
<InputLabel htmlFor="target_amount" value="Nominal target" />
<CurrencyInput
    id="target_amount"
    value={form.data.target_amount}
    onChange={(v) => form.setData('target_amount', v)}
/>
<InputError className="mt-1.5" message={form.errors.target_amount} />
```

**Yang belum diputuskan:** error yang tidak terikat ke field mana pun — misalnya kegagalan koneksi ke layanan luar. Rencananya ditampilkan sebagai banner di atas form mengikuti DESIGN.md §9.3, tetapi belum ada komponennya karena belum ada kasus nyata. Buat saat dibutuhkan, lalu catat di sini.

### 10.3 Pengujian

**Test berjalan di PostgreSQL (`fingoal_test`), bukan SQLite in-memory.** `phpunit.xml` sudah diarahkan ke sana. Jangan mengembalikannya ke SQLite demi kecepatan: skema kita memakai `jsonb` dan `enum` yang tidak didukung SQLite dan akan diam-diam jatuh jadi `text`, sehingga migrasi bisa lolos seluruh test lalu gagal saat pertama dijalankan sungguhan. Lagipula tidak ada yang dihemat — pengukuran nyata menunjukkan suite berjalan ~4 detik di PostgreSQL.

- `GoalCalculatorService` adalah fungsi matematis murni tanpa efek samping — cakupan pengujiannya harus paling tinggi di seluruh aplikasi. Uji terhadap `docs/fixtures/calculator-cases.json` (§6.6), termasuk semua kasus batas di §6.4. Kelas test ini meng-extend `PHPUnit\Framework\TestCase` (bukan `Tests\TestCase`) karena tidak menyentuh database sama sekali — pertahankan begitu agar tetap cepat.
- **Rumus kalkulator punya dua implementasi** (PHP dan JS). Menjalankan `php artisan test` saja tidak cukup — sertakan `npm run test:js`. Lihat §6.6.
- `InvestmentAllocationService`: uji bahwa setiap aturan berjumlah tepat 100% dan setiap kombinasi (jangka waktu × profil risiko) menghasilkan alokasi.
- `DashboardSummaryService`: uji kasus user tanpa goals (harus mengembalikan struktur kosong, bukan error), goals dengan `target_date NULL`, dan filter status `active` (lihat §6.9).
- `CurrentsNewsService`: uji dengan HTTP palsu (`Http::fake`) — jangan pernah memanggil API sungguhan dari test suite; kuota gratis akan habis.
- Uji feature untuk otorisasi: pengguna A tidak boleh membaca/mengubah tujuan milik pengguna B. Pakai helper `assertInertia(fn (Assert $page) => $page->component('Goals/Show')->has('goal'))` bawaan `inertiajs/inertia-laravel` untuk memeriksa nama komponen halaman & props di test, **bukan** `assertJson` seperti pada arsitektur API murni.

### 10.4 Keamanan
- Jangan pernah menaruh `CURRENTS_API_KEY` di kode frontend atau di props yang dikirim ke halaman mana pun.
- **Verifikasi email aktif.** `User` mengimplementasikan `MustVerifyEmail`, dan itulah satu-satunya hal yang membuat middleware `verified` berfungsi. Tanpa baris tersebut, middleware itu tetap terpasang tetapi meloloskan semua orang — gagal diam-diam, tanpa error. Dijaga `tests/Feature/Auth/AccessControlTest.php`.
- **Batas laju endpoint tamu** (PRD FR-40), semuanya di `routes/auth.php`:

  | Endpoint | Batas |
  |---|---|
  | `POST register` | 5 / menit per IP |
  | `POST login` | 10 / menit per IP, **di samping** pembatas bawaan Breeze di `LoginRequest` (5 percobaan per email+IP) |
  | `POST forgot-password` | 5 / menit per IP |
  | `POST reset-password` | 5 / menit per IP |

  Batas per-IP di lapisan route bukan duplikasi dari pembatas Breeze: pembatas Breeze dihitung per kombinasi email+IP, sehingga penyerang yang mengganti-ganti alamat email dari satu IP tidak tertahan olehnya.
- Rate limit kalkulator utilitas publik ada di `routes/web.php` (§6.8).
- Jangan mencatat (log) nominal keuangan pengguna beserta identitasnya dalam log aplikasi.
- **Foto profil ditulis ulang, tidak disimpan apa adanya** (`app/Services/AvatarService.php`). Berkas yang diunggah dibaca ulang lalu ditulis kembali dari nol sebagai WebP 256×256. Dua alasannya: seluruh metadata ikut hilang — termasuk koordinat GPS yang sering tertanam di foto ponsel dan akan menjadi kebocoran lokasi rumah pengguna — dan berkas yang menyamar sebagai gambar gagal di tahap ini. Batas dimensi 3000×3000 ada demi memori: GD memakai 4 byte per piksel saat membuka gambar. Saat akun dihapus, berkasnya wajib ikut dihapus (FR-37); dijaga `ProfileAvatarTest`.
- CSRF ditangani otomatis lewat cookie `XSRF-TOKEN` + middleware bawaan Laravel/Inertia — jangan menonaktifkannya untuk "menyederhanakan" pemanggilan dari luar. Kalau kelak benar-benar butuh API stateless (mis. untuk app mobile native), itu pekerjaan terpisah yang memakai Sanctum sebagai **token guard tambahan**, bukan modifikasi terhadap guard `web` yang dipakai halaman-halaman Inertia.

## 11. Referensi Dokumen Terkait

- `PRD.md` — requirement fungsional & non-fungsional lengkap, user stories, metrik sukses, tabel keputusan §13 (**catatan:** baris D-5 di sana masih mencatat keputusan Sanctum bearer token yang sudah digantikan D-9 di dokumen ini — perlu diperbarui agar kedua dokumen konsisten).
- `DESIGN.md` — palet warna, tipografi, spesifikasi komponen UI (sidebar, card, form kalkulator, panel rekomendasi, chart, news card).
- `ORIENTASI.md` — panduan membaca kode untuk anggota tim yang baru masuk: peta folder, alur satu halaman dari URL sampai layar, urutan baca, dan pembedahan berkas baris per baris. Arahkan orang baru ke sini lebih dulu, bukan ke dokumen ini.

Saat mengimplementasikan fitur baru, selalu cek dua dokumen di atas terlebih dahulu agar konsisten dengan requirement produk dan design system yang sudah ditetapkan.
