# DESIGN.md — FinGoal (Kalkulator Tujuan Finansial)

> Dokumen desain UI/UX untuk aplikasi manajemen keuangan pribadi dengan kalkulator tujuan finansial (dana pensiun, beli rumah, beli kendaraan) beserta rekomendasi instrumen investasi.

**Tema: "Malam" — near-black + lime listrik.** Dark-first. Mockup awal yang memakai tema terang + teal digantikan oleh arah ini; struktur layout dan komponennya tetap dipakai, hanya paletnya yang berganti.

---

## 1. Prinsip Desain

1. **Tenang di latar, tajam di aksen** — permukaan nyaris hitam yang rata dan tidak berisik, dengan lime dipakai sangat hemat. Kekuatan tema ini justru datang dari seberapa jarang lime muncul; begitu ia dipakai di mana-mana, layar berubah jadi melelahkan.
2. **Data-first** — angka, grafik, dan progres adalah elemen utama. Angka besar memakai tabular figures dan menjadi objek paling terang di layar.
3. **Clarity over decoration** — whitespace lega, hierarki tipografi jelas, ikon outline. Tanpa gradasi berat, tanpa bayangan tebal, tanpa glow.
4. **Konsisten lintas modul** — pola card, radius, dan spacing sama antara Dashboard, Kalkulator, News, dan Pengaturan.
5. **Aksesibel** — seluruh pasangan warna diverifikasi terhadap WCAG AA, bukan diperkirakan. Hasil pengukuran ada di §8.

> Catatan karakter: palet ini berani dan mudah diingat, tetapi condong ke nuansa "trading/kripto". Untuk aplikasi perencanaan jangka panjang, imbangi dengan bahasa yang tenang, animasi seperlunya, dan tata letak yang lapang — jangan ditambah efek neon, glow, atau angka yang berkedip.

---

## 2. Palet Warna — "Malam"

### 2.1 Permukaan
| Token | Hex | Penggunaan |
|---|---|---|
| `--color-bg-base` | `#0B0C0B` | Latar halaman |
| `--color-bg-surface` | `#101210` | Topbar, sidebar |
| `--color-bg-card` | `#16181A` | Card / panel |
| `--color-bg-card-alt` | `#252825` | Hover, panel hasil, skeleton |
| `--color-border` | `#282C28` | Garis pemisah dekoratif |
| `--color-border-strong` | `#666D61` | Batas input, tombol outline, komponen interaktif (≥3:1) |

### 2.2 Aksen
| Token | Hex | Penggunaan |
|---|---|---|
| `--color-lime-500` | `#CFF04A` | Aksen utama: tombol primer, angka hasil, fill progress, item aktif |
| `--color-lime-400` | `#DEF76F` | Hover |
| `--color-lime-600` | `#A8C531` | Active / pressed |
| `--color-lime-soft-bg` | `#1E2610` | Background chip, badge, banner bernuansa lime |
| `--color-on-primary` | `#10130A` | Teks/ikon di atas fill lime |

### 2.3 Teks
| Token | Hex | Penggunaan |
|---|---|---|
| `--color-text-primary` | `#F0F1EC` | Teks utama |
| `--color-text-secondary` | `#A3A99E` | Label, teks sekunder |
| `--color-text-muted` | `#8B917F` | Caption, placeholder, disclaimer |
| `--color-text-disabled` | `#5A6057` | **Hanya** elemen disabled — 2.75:1, tidak untuk teks yang harus terbaca |

### 2.4 State
| Token | Hex | Penggunaan |
|---|---|---|
| `--color-success` | `#5FD69A` | Positif — mint, sengaja **bukan** hijau kekuningan |
| `--color-danger` | `#F0705F` | Negatif, error |
| `--color-warning` | `#F2B950` | Peringatan lunak |
| `--color-info` | `#7CB8E8` | Info netral, dipakai minim |

> **Jebakan utama tema ini:** lime adalah warna merek **dan** berada di keluarga hijau, sementara hijau juga berarti "positif" di aplikasi keuangan. Karena itu `--color-success` sengaja dipilih mint (`#5FD69A`) yang jelas berbeda hue dari lime. Jangan pernah memakai lime untuk menandakan kenaikan/penurunan nilai — lime adalah warna **aksi**, mint adalah warna **hasil positif**.

### 2.5 Aturan Pemakaian Lime
- Maksimal **satu** elemen berisi lime penuh per layar — biasanya tombol primer.
- Angka hasil kalkulasi boleh lime, tapi hanya satu angka utama per panel; angka pendukung tetap `--color-text-primary`.
- Untuk area bernuansa lime yang luas (banner, chip, baris terpilih), pakai `--color-lime-soft-bg`, bukan lime penuh.
- Jangan memakai lime sebagai warna teks panjang. Ia untuk angka, label pendek, ikon, dan garis.

### 2.6 Mode Terang
Ditunda. Tema ini dirancang dark-first dan mode terang bukan bagian dari MVP.

Bila nanti dibuat, satu hal harus diingat: **lime tidak bisa menjadi teks di atas putih** — `#CFF04A` pada `#FFFFFF` hanya 1.30:1. Mode terang butuh token terpisah `--color-lime-text` = `#5F7A0F` (4.91:1) untuk teks/ikon, sementara `#CFF04A` tetap dipakai sebagai area terisi dengan teks gelap di atasnya.

---

## 3. Tipografi

- **Font utama:** `Plus Jakarta Sans` atau `Inter` (fallback: system-ui, sans-serif).
- **Angka:** wajib `font-variant-numeric: tabular-nums` di seluruh komponen finansial. Untuk angka hasil utama, pertimbangkan font mono (`JetBrains Mono`, `IBM Plex Mono`) — pada tema gelap, angka mono terbaca lebih tegas dan memperkuat kesan presisi.

| Level | Size / Weight | Contoh |
|---|---|---|
| Display (hasil kalkulasi utama) | 32px / 700 | `Rp 4.666.136` |
| H1 (judul halaman) | 24px / 700 | "Kalkulator Tujuan Finansial" |
| H2 (judul card) | 16px / 600 | "Parameter Tujuan" |
| Body | 14px / 400–500 | teks deskripsi |
| Caption / label | 12px / 500 | label input, timestamp |
| Micro | 11px / 500 uppercase, letter-spacing 0.04em | label kategori sidebar |

Di latar gelap, teks tipis terlihat lebih kurus daripada di latar terang. Hindari weight 300, dan jangan turunkan body di bawah 14px.

---

## 4. Layout & Grid

Struktur mengikuti mockup yang sudah ada:

- **Topbar** (64px): logo + nama app di kiri, navigasi utama di tengah — **Dashboard · Tujuan · Kalkulator · News · Pengaturan** — kartu profil pengguna di kanan. Item aktif ditandai underline lime 2px.
- **Sidebar kiri** (220–240px): daftar kategori kalkulator utilitas (Pinjaman/KPR, Investasi; Pajak menyusul di Fase 2). **Hanya tampil di halaman Kalkulator.** Di mockup awal sidebar ini ikut muncul di Dashboard dan News padahal isinya tidak relevan di sana — jangan diulang.

> **Tujuan dan Kalkulator adalah dua hal berbeda dan harus terlihat berbeda** (lihat PRD §1). Tujuan menghasilkan objek yang dipantau berbulan-bulan; Kalkulator menghasilkan angka sekali pakai. Halaman Kalkulator karena itu tidak menampilkan progress bar atau status, dan halaman Tujuan tidak menampilkan sidebar kategori. Satu-satunya penghubung adalah tombol **"Jadikan Tujuan"** di panel hasil kalkulator (FR-43) — beri bobot visual tombol sekunder, bukan primer, agar tidak bersaing dengan aksi "Hitung".
- **Konten utama**: fluid, max-width 1440px.
- **Panel kanan** (320px): tips, riwayat, aktivitas terbaru. Muncul di Dashboard dan Kalkulator.
- **Grid konten:** 12 kolom, gutter 24px, padding container 32px (desktop) / 16px (mobile).
- **Card:** radius `16px`, padding `20–24px`, border `1px solid var(--color-border)`. **Tanpa shadow** — di latar nyaris hitam, bayangan tidak terlihat dan hanya mengotori. Kedalaman dibentuk lewat perbedaan permukaan (`base` → `card` → `card-alt`), bukan lewat bayangan.
- **Breakpoints:** mobile `<640px` (nav jadi drawer/bottom nav, panel kanan turun ke bawah konten), tablet `640–1024px` (sidebar icon-only), desktop `>1024px` (penuh).

---

## 5. Komponen Utama

### 5.1 Navigasi
- **Topbar** — item aktif: teks `--color-text-primary` + underline `--color-lime-500` 2px. Item non-aktif `--color-text-secondary`.
- **Sidebar kategori** — item aktif: teks & ikon `--color-lime-500`, background chip `--color-lime-soft-bg`, garis vertikal lime 2px di tepi kiri. Item non-aktif: ikon & teks `--color-text-secondary`.
- Kartu profil di topbar: avatar, nama, label keanggotaan kecil `--color-text-muted`.

### 5.2 Card Ringkasan (Summary Card)
Dipakai di Dashboard untuk Total Aset, Total Hutang, Rasio Hutang, Tabungan Bulanan.
- Label kecil `--color-text-secondary` di atas.
- Angka besar bold `--color-text-primary`. **Jangan** memakai lime di sini — bila keempat kartu berlime, tidak ada lagi yang menonjol.
- Delta indikator (▲/▼ + persen) berwarna `--color-success` / `--color-danger`, selalu disertai tanda `+`/`−`.
- Kondisi perlu perhatian → badge `--color-warning` dengan teks, bukan sekadar angka merah.

### 5.3 Form Kalkulator Tujuan
Layout dua kolom: **Parameter** (kiri) + **Hasil** (kanan).
- Input: label di atas field, border `1px solid var(--color-border-strong)`, radius 10px, background `--color-bg-base` (lebih gelap dari card, sehingga field terbaca sebagai lubang bukan tonjolan).
- Focus: ring lime 2px dengan offset 2px.
- Slider untuk parameter seperti estimasi return: track `--color-border`, fill dan thumb lime.
- Tombol utama "Hitung Sekarang": full-width, background `--color-lime-500`, teks `--color-on-primary`. **Satu-satunya area lime penuh berukuran besar di layar.**
- Panel Hasil: background `--color-bg-card-alt`, border kiri lime 2px, angka hasil ukuran Display warna `--color-lime-500`.

### 5.4 Panel Rekomendasi Instrumen (fitur khas produk ini)
Card berjudul **"Strategi Mencapai Target Ini"**. Panel ini belum ada di mockup awal dan perlu didesain.
- List instrumen (Saham, Reksa Dana, Obligasi/SBN, Deposito, Emas), tiap baris berisi:
  - Ikon instrumen outline; lime hanya untuk instrumen dengan alokasi terbesar.
  - Nama + alokasi (%) sebagai mini progress bar horizontal, fill lime, track `--color-border`.
  - Estimasi return tahunan sebagai badge kecil (`--color-lime-soft-bg`, teks lime), dengan label "sebelum pajak" sesuai PRD FR-25.
  - Level risiko sebagai dot: `--color-success` (rendah) / `--color-warning` (sedang) / `--color-danger` (tinggi), **selalu disertai teks** Rendah/Sedang/Tinggi.
- Disclaimer di bawah panel, 12px, `--color-text-muted`: "Estimasi bersifat simulasi, bukan saran investasi personal."

### 5.5 Grafik
- **Line chart** (pertumbuhan aset / proyeksi): garis lime 2px, area fill lime dengan opasitas rendah menuju transparan, grid `--color-border` tipis. Di latar gelap, grid harus jauh lebih redup daripada di tema terang — grid yang terlalu terang akan bersaing dengan garis data.
- **Bar chart** (amortisasi / alokasi): batang utama lime, batang pembanding abu hangat `#4A4F47`.
- **Donut chart** (alokasi aset): satu keluarga lime-monokrom — `#CFF04A` → `#A8C531` → `#7E9628` → `#566620` → `#4A4F47`. Hindari warna pelangi; urutan gelap-terang sudah cukup membedakan segmen, dan tiap segmen tetap diberi label teks.

### 5.6 Kartu Berita (News)
- Thumbnail 16:9 radius 12px, badge kategori (`--color-lime-soft-bg`, teks lime) di pojok thumbnail.
- Judul bold maksimal 2 baris, ringkasan 2–3 baris `--color-text-secondary`, meta waktu baca & tanggal `--color-text-muted`.
- Filter kategori sebagai tab horizontal; tab aktif berlatar `--color-lime-soft-bg` dengan teks lime.
- Gambar berita datang dari sumber luar dan warnanya tidak bisa dikendalikan. Beri overlay gelap tipis pada thumbnail agar tidak menyilaukan di tema gelap, dan pastikan badge tetap terbaca di atas gambar apa pun.

### 5.7 Progress Target (goal tracking)
- Bar horizontal: label kiri (nama target), nilai kanan (saat ini / target), fill lime, track `--color-border`.
- Badge persentase di ujung kanan.
- Bila realisasi tertinggal dari rencana (PRD FR-35), tampilkan penanda kedua pada bar dengan warna `--color-warning` — posisi "seharusnya di sini".

---

## 6. Ikonografi
- Outline/line icon, stroke 1.5–2px, sudut membulat. Library: `lucide-react`.
- Warna default `--color-text-secondary`; state aktif `--color-lime-500`.
- Di latar gelap, stroke 1.5px pada ikon kecil (16px) bisa hilang. Untuk ikon ≤16px pakai stroke 2px.

## 7. Motion / Interaksi
- Transisi 150–200ms ease-out untuk hover, active, tab switch.
- Hasil kalkulasi muncul dengan fade + slide-up ringan (200ms).
- Progress bar mengisi dari 0 → nilai aktual saat pertama render (600ms ease-out).
- **Tanpa glow, pulse, atau efek neon.** Godaan terbesar palet lime-di-atas-hitam adalah menambahkan cahaya; itu yang membuat sebuah aplikasi keuangan terlihat seperti dasbor kripto.

## 8. Aksesibilitas

### 8.1 Hasil Pengukuran Kontras (WCAG 2.1)
Diukur terhadap `--color-bg-card` `#16181A` kecuali disebut lain. Ambang: teks normal 4.5:1, teks besar & komponen UI 3:1.

| Pasangan | Rasio | Status |
|---|---|---|
| `#F0F1EC` / `#0B0C0B` | 17.26 | AAA — teks utama di latar halaman |
| `#F0F1EC` / `#16181A` | 15.68 | AAA — teks utama di card |
| `#A3A99E` / `#16181A` | 7.40 | AAA — teks sekunder |
| `#8B917F` / `#16181A` | 5.47 | AA — muted, aman untuk disclaimer |
| `#CFF04A` / `#16181A` | 13.75 | AAA — lime sebagai teks/angka |
| `#DEF76F` / `#16181A` | 14.95 | AAA — hover |
| `#A8C531` / `#16181A` | 9.07 | AAA — active |
| `#10130A` / `#CFF04A` | 14.48 | AAA — teks di atas tombol lime |
| `#CFF04A` / `#1E2610` | 12.10 | AAA — teks lime di dalam chip |
| `#5FD69A` / `#16181A` | 9.81 | AAA — success |
| `#F0705F` / `#16181A` | 6.09 | AA — danger |
| `#F2B950` / `#16181A` | 10.03 | AAA — warning |
| `#7CB8E8` / `#16181A` | 8.37 | AAA — info |
| `#666D61` / `#16181A` | 3.33 | lolos ambang komponen UI — border input |
| `#282C28` / `#16181A` | 1.26 | dekoratif saja, dikecualikan |
| `#5A6057` / `#16181A` | 2.75 | **hanya disabled** — jangan untuk teks aktif |

### 8.2 Aturan
- Disclaimer dan caption memakai `--color-text-muted` `#8B917F` minimal 12px. Jangan turunkan ke 11px untuk teks yang membawa arti hukum.
- Border input dan tombol outline wajib `--color-border-strong` `#666D61`. `--color-border` `#282C28` hanya untuk garis pemisah dekoratif — bila dipakai sebagai batas field, pengguna tidak bisa melihat di mana field berakhir.
- Focus visible: ring `--color-lime-500` 2px, offset 2px. Wajib pada semua elemen interaktif.
- Semua state warna disertai ikon atau label teks, tidak hanya warna. Angka finansial selalu memakai tanda `+`/`−` eksplisit.
- Lime tidak boleh menjadi satu-satunya penanda "aktif" pada navigasi — sertakan underline atau garis tepi.
- Hormati `prefers-reduced-motion`: matikan animasi fill progress bar dan fade+slide hasil kalkulasi.

## 9. State Komponen (empty, loading, error)

### 9.1 Empty State
Layar pertama pengguna baru adalah dashboard **tanpa satu pun tujuan** — justru layar terpenting untuk konversi.
- Ikon outline besar (64px) `--color-text-muted`.
- Judul singkat + satu kalimat penjelas + **satu** tombol primer lime ("Buat Tujuan Pertama").
- Empty state per modul: Dashboard, Tujuan, News (tidak ada berita untuk filter ini), riwayat kalkulasi.
- Jangan menampilkan card berisi `Rp 0` — itu terbaca sebagai bug, bukan keadaan awal.

### 9.2 Loading State
- **Skeleton** berlatar `--color-bg-card-alt` dengan shimmer halus 1.2s untuk konten berbentuk tetap. Pada tema gelap, shimmer harus sangat halus — kilau terang di latar hitam sangat mengganggu.
- Spinner lime kecil hanya di dalam tombol ("Menghitung…"), tombol disabled selama proses.
- Chart: tampilkan grid kosong + skeleton pada angka, jangan mengecilkan tinggi container.

### 9.3 Error State
- **Error field**: border `--color-danger`, pesan 12px warna danger di bawah field, ikon peringatan. Fokus otomatis ke field error pertama saat submit gagal.
- **Error modul**: card dengan ikon, pesan non-teknis, tombol "Coba lagi". Jangan tampilkan error mentah dari server.
- **Data basi** (News `stale: true`): banner tipis berlatar `--color-lime-soft-bg` + timestamp `fetched_at`.
- Kegagalan satu modul tidak boleh mengosongkan modul lain di halaman yang sama.

### 9.4 Spesifikasi Validasi Form Kalkulator
- Validasi saat `blur`; validasi ulang seluruh form saat submit.
- Aturan minimum: nominal target > 0; jangka waktu ≥ 1 bulan dan tanggal target di masa depan; dana awal ≥ 0; estimasi return 0–30%; estimasi inflasi 0–20%.
- Input nominal diformat ribuan saat blur (`1.500.000`), disimpan sebagai angka murni.
- Kondisi yang **bukan** error tapi wajib diberi tahu: dana awal sudah ≥ target ("Target Anda sudah tercapai"), dan setoran hasil hitung yang tidak wajar besar (peringatan lunak, bukan blokir).

### 9.5 Interaksi "Bagaimana Jika" (FR-8)
- Dua slider terkait: jangka waktu dan nominal setoran; menggeser satu memperbarui yang lain secara live.
- Perhitungan live di frontend dengan debounce 150ms; nilai yang **disimpan** selalu dari backend (CLAUDE.md §6).
- Angka hasil beranimasi count-up 300ms, tidak melompat.
- Tampilkan baseline sebagai teks kecil di bawah angka ("semula Rp 4.666.136").

## 10. Referensi Struktur Halaman
1. **Dashboard** — kartu ringkasan, grafik pertumbuhan aset, ringkasan target aktif, panel kanan berisi aktivitas terbaru & analisis singkat.
2. **Tujuan** — daftar tujuan berjalan dengan progress bar masing-masing, tombol buat tujuan baru. *(Menggantikan halaman "Portofolio" yang direncanakan terpisah — keduanya menampilkan hal yang sama.)*
3. **Detail Tujuan** — parameter, hasil kalkulasi, panel rekomendasi instrumen, grafik proyeksi, riwayat setoran, perbandingan rencana vs realisasi.
4. **Kalkulator** — sidebar kategori utilitas, form parameter, hasil kalkulasi, grafik amortisasi/proyeksi, tombol sekunder "Jadikan Tujuan". Dapat diakses tanpa login.
5. **News** — grid berita dengan filter kategori. *(Panel indeks pasar dicoret dari MVP — tidak ada sumber data, lihat keputusan D-4 di PRD. Tanpa panel itu, News memakai lebar penuh.)*
6. **Pengaturan** — profil, preferensi mata uang & format angka, profil risiko, notifikasi.
