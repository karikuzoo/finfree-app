# CLAUDE.md — Panduan Konteks Project untuk Claude Code

Dokumen ini adalah konteks kerja untuk Claude (atau developer lain) saat membangun/mengembangkan project **FinGoal**. Baca bersama `PRD.md` (requirement produk) dan `DESIGN.md` (design system) di folder yang sama.

## 1. Ringkasan Project

FinGoal adalah aplikasi web manajemen keuangan pribadi dengan fitur utama **kalkulator tujuan finansial** (dana pensiun, beli rumah, beli kendaraan, dana darurat, dana pendidikan) yang menghasilkan nominal setoran bulanan yang dibutuhkan beserta **rekomendasi alokasi instrumen investasi** (saham, reksa dana, obligasi/SBN, deposito, emas). Dilengkapi dashboard progres tujuan dan modul berita finansial dari Currents API.

Tema visual: **"Malam"** — near-black dipadu lime listrik, dark-first (lihat `DESIGN.md` untuk token warna & komponen). Arah ini menggantikan dua versi sebelumnya: mockup terang + teal, dan konsep soft black & gold.

## 2. Tech Stack

| Layer | Teknologi |
|---|---|
| Frontend | React (Vite), React Router |
| Styling | Tailwind CSS |
| State management | React Context / Zustand (untuk state ringan seperti auth & preferensi user) |
| Charting | Recharts atau Chart.js (line/bar/donut untuk proyeksi & alokasi) |
| Icon | lucide-react |
| Backend | Laravel (REST API), PHP 8.2+ |
| Auth API | Laravel Sanctum (token-based) |
| Database | PostgreSQL |
| Cache (opsional) | Redis (cache hasil Currents API) atau tabel cache di PostgreSQL |
| API eksternal | Currents API (currentsapi.services) — free tier, untuk modul News |
| Queue/Scheduler | Laravel Scheduler + Queue (fetch berita berkala, hindari rate limit) |

## 3. Struktur Repository (disarankan)

```
fingoal/
├── backend/                 # Laravel app
│   ├── app/
│   │   ├── Http/Controllers/Api/
│   │   │   ├── AuthController.php
│   │   │   ├── FinancialGoalController.php
│   │   │   ├── GoalContributionController.php
│   │   │   ├── CalculatorController.php            # kalkulator tujuan
│   │   │   ├── UtilityCalculatorController.php     # pinjaman/KPR & investasi (FR-41,42)
│   │   │   ├── InvestmentRecommendationController.php
│   │   │   ├── NewsController.php
│   │   │   └── UserPreferenceController.php
│   │   ├── Models/
│   │   │   ├── User.php
│   │   │   ├── FinancialGoal.php
│   │   │   ├── GoalContribution.php
│   │   │   ├── GoalCalculation.php
│   │   │   ├── InvestmentInstrument.php
│   │   │   └── NewsArticleCache.php
│   │   ├── Services/
│   │   │   ├── GoalCalculatorService.php      # rumus future value of annuity
│   │   │   ├── InvestmentAllocationService.php # rule-based recommendation engine
│   │   │   └── CurrentsNewsService.php         # fetch + cache Currents API
│   │   └── Jobs/
│   │       └── FetchLatestNewsJob.php
│   ├── database/
│   │   ├── migrations/
│   │   └── seeders/ (default rate return/inflasi per kategori, master instrumen investasi)
│   └── routes/api.php
│
├── frontend/                 # React app
│   ├── src/
│   │   ├── components/
│   │   │   ├── layout/ (Sidebar, Topbar, PageContainer)
│   │   │   ├── dashboard/ (SummaryCard, AssetGrowthChart, GoalProgressList)
│   │   │   ├── calculator/ (GoalForm, ResultPanel, RecommendationPanel, ProjectionChart)
│   │   │   ├── news/ (NewsCard, NewsFilterTabs)   # MarketIndexPanel dicoret, lihat D-4
│   │   │   └── ui/ (Button, Input, Slider, Badge, Card — design primitives sesuai DESIGN.md)
│   │   ├── pages/
│   │   │   ├── DashboardPage.jsx
│   │   │   ├── GoalsPage.jsx        # daftar tujuan (menggantikan Portofolio)
│   │   │   ├── GoalDetailPage.jsx
│   │   │   ├── CalculatorPage.jsx   # kalkulator utilitas, sidebar kategori
│   │   │   ├── NewsPage.jsx
│   │   │   └── SettingsPage.jsx
│   │   ├── hooks/
│   │   ├── services/ (api client — axios/fetch wrapper ke Laravel API)
│   │   ├── context/ (AuthContext, PreferenceContext)
│   │   └── styles/ (tailwind.config.js theme extension — token warna dari DESIGN.md)
│   └── tailwind.config.js
│
├── docs/
│   ├── PRD.md
│   ├── DESIGN.md
│   └── CLAUDE.md
└── README.md
```

## 4. Tailwind Theme Mapping (dari DESIGN.md)

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

Font: `Plus Jakarta Sans` atau `Inter`. Gunakan `font-variant-numeric: tabular-nums` untuk seluruh komponen angka (buat utility class `.num-tabular`); untuk angka hasil utama pertimbangkan font mono — di tema gelap angka mono terbaca lebih tegas.

## 5. Domain Model / Skema Database Inti (PostgreSQL)

Tipe kolom ditulis eksplisit karena kolom uang yang tidak ditentukan tipenya hampir selalu berakhir sebagai `float` — lihat §6.5.

```
users
  id, name, email (unique), email_verified_at, password,
  risk_profile (enum: conservative|moderate|aggressive),
  prefers_syariah (boolean, default false),
  currency_preference (char(3), default 'IDR'), created_at, updated_at, deleted_at

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

goal_contributions                            -- BARU, prasyarat FR-32..FR-36
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
- Persentase (return, inflasi, alokasi) disimpan sebagai `numeric(5,2)` dalam satuan persen (mis. `7.50`), bukan desimal `0.075`. Tetapkan satu konvensi dan patuhi di API maupun DB.

### 6.6 Sumber kebenaran & duplikasi rumus
Implementasikan logika di `GoalCalculatorService.php` (backend) sebagai *source of truth*. Frontend boleh menduplikasi rumus untuk preview real-time (FR-8), dengan syarat:

- Sediakan berkas **test vector bersama**, mis. `docs/fixtures/calculator-cases.json`, berisi pasangan input→output yang sudah diverifikasi manual.
- Uji PHP dan JavaScript terhadap berkas yang sama. Bila keduanya bisa berbeda diam-diam, cepat atau lambat akan berbeda.
- Nilai yang **disimpan** selalu hasil dari backend.

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
- Endpoint kalkulator utilitas **tidak memerlukan autentikasi** (PRD FR-44), jadi pasang rate limit di sana. Endpoint publik tanpa batas laju adalah beban gratis bagi siapa pun yang ingin menyalahgunakannya.

## 7. Integrasi Currents API

- Endpoint: `https://api.currentsapi.services/v1/search` (free tier — perhatikan rate limit harian). **Verifikasi domain ini di dokumentasi resmi sebelum implementasi** — dokumen versi awal sempat menulis `api.currentsapi.io`, yang tidak sama dengan `currentsapi.services` yang disebut di tabel tech stack.
- Currents adalah API **berita umum dunia**, bukan API keuangan Indonesia. Konsekuensi yang harus diantisipasi: cakupan berita finansial berbahasa Indonesia kemungkinan tipis, dan kategori pada FR-17 tidak tersedia dari sumber sehingga harus diklasifikasi sendiri saat ingest (FR-28). Uji kualitas hasil pencarian lebih dulu dengan beberapa kata kunci nyata sebelum modul News dianggap layak rilis; siapkan rencana cadangan (RSS media ekonomi lokal) bila hasilnya kurang.
- Backend melakukan fetch berkala via `FetchLatestNewsJob` (Laravel Scheduler, misal tiap 30–60 menit) dan menyimpan ke tabel `news_article_cache`, bukan fetch langsung tiap request dari frontend.
- Frontend selalu memanggil endpoint internal Laravel (`/api/news`), tidak pernah memanggil Currents API langsung, agar API key tidak terekspos di client.
- Sediakan fallback: jika fetch job gagal (limit habis/error), endpoint tetap mengembalikan cache terakhir + flag `stale: true`.

## 8. Environment Variables (contoh)

**Backend (`.env`)**
```
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

# Mode auth: bearer token (lihat catatan di bawah)
FRONTEND_URL=http://localhost:5173
```

> **Diputuskan (D-5): Sanctum mode bearer token.** Frontend Vite berjalan di origin berbeda (`:5173` vs `:8000`), dan mode cookie SPA menuntut CORS berkredensial, kesamaan domain induk, serta penanganan CSRF — tidak sepadan untuk SPA ini.
>
> Konsekuensi konfigurasi: `SANCTUM_STATEFUL_DOMAINS` dan `SESSION_DOMAIN` **tidak dipakai** dan jangan ditambahkan kembali — keduanya penanda mode cookie dan hanya akan menyesatkan. Cukup `FRONTEND_URL` untuk konfigurasi CORS. Token disimpan di memori aplikasi frontend (bukan `localStorage`, agar tidak terbaca skrip pihak ketiga); konsekuensinya sesi hilang saat refresh, jadi sediakan alur re-auth yang mulus atau refresh token bila terasa mengganggu.

**Frontend (`.env`)**
```
VITE_API_BASE_URL=http://localhost:8000/api
```

## 9. Perintah Umum

```bash
# Backend
cd backend
composer install
php artisan migrate --seed
php artisan serve
php artisan schedule:work   # untuk menjalankan job fetch berita secara berkala (dev)

# Frontend
cd frontend
npm install
npm run dev
```

## 10. Konvensi Kode

- **Backend:** ikuti konvensi Laravel standar (PSR-12), gunakan Form Request untuk validasi, Resource class untuk shaping response JSON.
- **Frontend:** komponen fungsional React + hooks, satu komponen per file, styling murni via Tailwind utility classes (hindari inline style kecuali untuk nilai dinamis seperti progress bar width).
- **Penamaan route API:** REST konsisten, contoh `GET /api/goals`, `POST /api/goals`, `POST /api/goals/{id}/calculate`, `GET /api/news`.
- **Bahasa UI:** Bahasa Indonesia (mengikuti referensi produk), format angka menggunakan pemisah ribuan titik dan mata uang `Rp`.

### 10.1 Kontrak API
Sepakati bentuk response sebelum frontend dan backend dikerjakan paralel — ini sumber gesekan terbesar bila ditunda.

```jsonc
// Sukses (koleksi)
{ "data": [ ... ], "meta": { "page": 1, "per_page": 20, "total": 57 } }

// Sukses (tunggal)
{ "data": { ... } }

// Gagal
{ "message": "Data yang diberikan tidak valid.",
  "errors": { "target_amount": ["Nominal target wajib diisi."] } }
```

- Uang dikirim sebagai **angka**, bukan string terformat. Pemformatan `Rp` adalah urusan frontend.
- Tanggal memakai format ISO 8601.
- Persentase dikirim dalam satuan persen (`7.5`), konsisten dengan penyimpanan di DB.
- `GET /api/news` dan `GET /api/goals` wajib berpaginasi sejak awal.
- Response berita menyertakan `stale: true` beserta `fetched_at` bila cache tidak segar (PRD NFR-4).
- Kode status: 422 validasi, 401 belum login, 403 bukan pemilik, 404 tidak ada. Selalu periksa kepemilikan tujuan terhadap user yang login — ini titik rawan kebocoran data antar pengguna.

### 10.2 Pengujian
- `GoalCalculatorService` adalah fungsi matematis murni tanpa efek samping — cakupan pengujiannya harus paling tinggi di seluruh aplikasi. Uji terhadap `docs/fixtures/calculator-cases.json` (§6.6), termasuk semua kasus batas di §6.4.
- `InvestmentAllocationService`: uji bahwa setiap aturan berjumlah tepat 100% dan setiap kombinasi (jangka waktu × profil risiko) menghasilkan alokasi.
- `CurrentsNewsService`: uji dengan HTTP palsu (`Http::fake`) — jangan pernah memanggil API sungguhan dari test suite; kuota gratis akan habis.
- Uji feature untuk otorisasi: pengguna A tidak boleh membaca/mengubah tujuan milik pengguna B.

### 10.3 Keamanan
- Jangan pernah menaruh `CURRENTS_API_KEY` di kode frontend atau di response API.
- Rate limit pada endpoint auth (PRD FR-40) memakai `throttle` middleware Laravel.
- Jangan mencatat (log) nominal keuangan pengguna beserta identitasnya dalam log aplikasi.

## 11. Referensi Dokumen Terkait

- `PRD.md` — requirement fungsional & non-fungsional lengkap, user stories, metrik sukses.
- `DESIGN.md` — palet warna, tipografi, spesifikasi komponen UI (sidebar, card, form kalkulator, panel rekomendasi, chart, news card).

Saat mengimplementasikan fitur baru, selalu cek dua dokumen di atas terlebih dahulu agar konsisten dengan requirement produk dan design system yang sudah ditetapkan.
