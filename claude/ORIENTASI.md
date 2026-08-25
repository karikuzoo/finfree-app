# ORIENTASI.md — Panduan Membaca Kode FinGoal

Dokumen ini untuk anggota tim yang **baru pertama kali** masuk ke kode ini. Tujuannya satu: setelah membaca ini, Anda tahu berkas mana yang harus dibuka saat ingin mengubah sesuatu — dan tidak lagi merasa harus memahami semuanya sekaligus.

Dokumen lain punya peran berbeda: [PRD.md](PRD.md) menjelaskan **apa** yang dibangun, [DESIGN.md](DESIGN.md) menjelaskan **tampilannya**, [CLAUDE.md](CLAUDE.md) adalah rujukan teknis lengkap, dan [CONTRIBUTING.md](CONTRIBUTING.md) mengatur **cara kerja tim**. Yang ini khusus soal membaca kode.

---

## 1. Peta folder

Kode tampilan seluruhnya ada di `resources/js/`:

```
resources/js/
├── Pages/       satu berkas = satu halaman yang bisa dibuka di browser
├── Layouts/     bingkai yang dipakai bersama beberapa halaman
├── Components/  suku cadang kecil yang dipakai berulang
└── app.jsx      titik awal — jarang perlu disentuh
```

Aturan sederhananya:

| Kalau ingin mengubah… | Buka folder |
|---|---|
| isi sebuah halaman | `Pages/` |
| header, menu, footer | `Layouts/` |
| bentuk tombol atau input di semua halaman | `Components/` |
| warna, font, ukuran | `tailwind.config.js` |
| alamat URL sebuah halaman | `routes/web.php` |

Kode di luar `resources/js/` yang ikut menentukan tampilan:

- **[tailwind.config.js](../tailwind.config.js)** — semua warna tema "Malam" didefinisikan di sini
- **[resources/css/app.css](../resources/css/app.css)** — warna dasar halaman, 28 baris
- **[resources/views/app.blade.php](../resources/views/app.blade.php)** — kerangka HTML terluar, memuat font
- **[routes/web.php](../routes/web.php)** — URL mana membuka halaman mana

---

## 2. Perjalanan satu halaman, dari URL sampai layar

Ini bagian terpenting untuk dipahami lebih dulu. Kalau alurnya sudah terbayang, sisanya jadi mudah.

Misalkan seseorang membuka `http://localhost:8000/berita`:

```
1. Browser meminta  /berita
                        ↓
2. routes/web.php   mencocokkan alamatnya
                    Route::get('/berita', fn () => Inertia::render('News/Index'))
                        ↓
3. Inertia          mencari berkas dengan nama itu
                    resources/js/Pages/News/Index.jsx
                        ↓
4. app.blade.php    menyiapkan kerangka HTML kosong
                        ↓
5. React            menggambar isi halaman ke dalam kerangka itu
                        ↓
6. Layar            halaman Berita tampil
```

Yang perlu dicatat: **nama di `Inertia::render()` adalah jalur berkas di dalam `Pages/`.** `'News/Index'` berarti `Pages/News/Index.jsx`. Tidak ada konfigurasi lain yang menghubungkan keduanya — hanya kecocokan nama.

### Data dari Laravel ke React

Kalau halaman butuh data, Laravel mengirimnya sebagai argumen kedua:

```php
Inertia::render('Dashboard', [
    'summary' => $summary,      // ← ini akan sampai ke React
]);
```

Di sisi React, data itu diterima sebagai **props**:

```jsx
export default function Dashboard({ summary }) {
    return <p>{summary.total_assets}</p>;
}
```

Selesai. Tidak ada `fetch`, tidak ada `axios`, tidak ada `useEffect`. Lihat §5 poin 2 kalau bagian ini terasa aneh.

---

## 3. Urutan baca

Mulai dari yang kecil supaya polanya terlihat lebih dulu.

| # | Berkas | Baris | Yang dipelajari |
|---|---|---|---|
| 0 | [tailwind.config.js](../tailwind.config.js) | 62 | Semua nama warna. Baca ini duluan — setelahnya `bg-bg-card` di berkas lain langsung masuk akal |
| 1 | [Components/PrimaryButton.jsx](../resources/js/Components/PrimaryButton.jsx) | 20 | Bentuk paling sederhana sebuah komponen |
| 2 | [Components/NavLink.jsx](../resources/js/Components/NavLink.jsx) | 26 | Kelas yang berubah tergantung kondisi |
| 3 | [Pages/News/Index.jsx](../resources/js/Pages/News/Index.jsx) | 80 | Satu halaman utuh, isinya masih statis |
| 4 | [Pages/Welcome.jsx](../resources/js/Pages/Welcome.jsx) | 137 | Halaman nyata: tautan, perulangan daftar |
| 5 | [Layouts/PublicLayout.jsx](../resources/js/Layouts/PublicLayout.jsx) | 145 | Bingkai bersama + menu yang bisa dibuka-tutup |

**Belum perlu dibaca sekarang:** `AuthenticatedLayout.jsx`, `Pages/Auth/`, `Pages/Profile/`, dan `Components/TextInput.jsx`. Semuanya bawaan Laravel Breeze yang sudah berfungsi. Tidak ada yang bisa dipelajari dari situ tentang FinGoal.

---

## 4. Membedah satu berkas, baris per baris

Buka [Components/PrimaryButton.jsx](../resources/js/Components/PrimaryButton.jsx). Dua puluh baris, dan hampir semua konsep dasar ada di dalamnya.

```jsx
export default function PrimaryButton({
    className = '',
    disabled,
    children,
    ...props
}) {
```

- `export default function` — berkas ini menyediakan satu komponen bernama `PrimaryButton`. Berkas lain memakainya dengan `import PrimaryButton from '@/Components/PrimaryButton'`.
- Yang di dalam `{ }` adalah **props** — nilai yang dikirim dari luar. Ditulis begini agar bisa langsung dipakai sebagai variabel.
- `className = ''` — nilai bawaan. Kalau pemakainya tidak mengirim `className`, isinya string kosong.
- `children` — nama khusus. Isinya apa pun yang ditulis **di antara** tag pembuka dan penutup. Pada `<PrimaryButton>Simpan</PrimaryButton>`, `children` bernilai `"Simpan"`.
- `...props` — "sisa props lain yang tidak saya sebutkan, kumpulkan ke sini". Disebut *rest*.

```jsx
        <button
            {...props}
```

`{...props}` menyebar seluruh sisa props itu ke elemen `<button>`. Berkat baris ini, `<PrimaryButton onClick={...} type="submit">` bekerja tanpa `onClick` dan `type` perlu ditulis satu per satu di komponen ini.

```jsx
            className={
                `inline-flex items-center rounded-lg ... ${
                    disabled && 'opacity-40'
                } ` + className
            }
```

- Rangkaian kata itu **kelas Tailwind**. Tiap kata satu aturan CSS: `px-4` padding kiri-kanan, `rounded-lg` sudut membulat, `bg-lime-500` latar lime.
- Tanda petik miring `` ` `` adalah *template string* — string yang bisa disisipi nilai lewat `${...}`.
- `disabled && 'opacity-40'` artinya: kalau `disabled` bernilai benar, hasilnya `'opacity-40'`; kalau tidak, tidak menghasilkan apa-apa. Ini cara ringkas menulis "tambahkan kelas ini hanya jika…".
- `+ className` di ujung menempelkan kelas tambahan dari pemakainya, sehingga tombol tertentu bisa diberi gaya khusus tanpa mengubah berkas ini.

```jsx
            {children}
        </button>
```

Di sinilah isi tombol digambar.

### Lalu lihat NavLink.jsx

Berkas kedua ini menambahkan satu konsep: **percabangan**.

```jsx
(active
    ? 'border-lime-500 text-text-primary'
    : 'border-transparent text-text-secondary hover:...')
```

Dibaca: kalau `active` benar pakai kelas yang pertama, kalau tidak pakai yang kedua. Bentuk `kondisi ? A : B` ini disebut *ternary*, dan akan Anda temui di mana-mana.

### Dan satu pola dari Welcome.jsx

```jsx
{[
    { title: 'Hitung, bukan menebak', body: '...' },
    { title: 'Alokasi sesuai jangka waktu', body: '...' },
].map((item) => (
    <div key={item.title}>...</div>
))}
```

`.map()` mengubah sebuah daftar menjadi sebuah daftar elemen — cara membuat tiga kartu tanpa menulis kode kartunya tiga kali. `key` wajib ada dan harus unik; React memakainya untuk melacak tiap item.

---

## 5. Empat hal yang pasti membingungkan

Ini bukan karena Anda pemula. Tiga di antaranya memang bukan React biasa.

**1. `route('home')` — ini apa?**

Bukan React, melainkan Ziggy. Ia menerjemahkan **nama** route menjadi URL: `route('news.index')` menghasilkan `/berita`. Nama-nama itu didefinisikan di [routes/web.php](../routes/web.php) lewat `->name(...)`.

Gunanya: kalau suatu hari `/berita` diubah jadi `/artikel`, cukup ubah di satu tempat dan semua tautan ikut benar. Kalau URL ditulis langsung sebagai teks di banyak berkas, Anda harus memburu satu per satu.

**2. Tidak ada `fetch`, `axios`, atau `useEffect` sama sekali.**

Kalau Anda belajar React dari tutorial umum, data biasanya diambil sendiri oleh komponen. Di sini tidak — Laravel yang mengirim datanya sebagai props (lihat §2).

Ini bukan kelalaian, melainkan keputusan arsitektur yang tercatat sebagai **D-9** di [PRD.md §13](PRD.md). Kalau suatu saat Anda melihat komponen yang mengambil datanya sendiri lewat `fetch`, itu justru pertanda ada yang salah arah.

**3. `className` yang panjangnya luar biasa.**

Itu Tailwind. Rasanya berantakan di awal, dan itu wajar. Dua hal yang membantu:

- Tidak perlu dihafal. Tebak lalu cek di [tailwindcss.com/docs](https://tailwindcss.com/docs) — pencariannya bagus.
- Bacanya per kelompok, bukan per kata: ukuran (`px-4 py-2`), lalu warna (`bg-lime-500 text-onPrimary`), lalu keadaan (`hover:bg-lime-400`).

Awalan seperti `hover:`, `focus:`, `sm:`, dan `md:` berarti "aturan ini hanya berlaku saat …". `md:flex` artinya menjadi flex hanya pada layar sedang ke atas.

**4. `forwardRef` dan `useImperativeHandle` di `TextInput.jsx`.**

Ini memang sulit, bahkan untuk yang sudah menengah. Kabar baiknya: itu kode bawaan Breeze, dan Anda **tidak perlu memahaminya** untuk memakai `TextInput`. Lewati saja. Suatu hari nanti akan masuk akal dengan sendirinya.

---

## 6. Kosakata singkat

| Istilah | Artinya di sini |
|---|---|
| **komponen** | satu fungsi yang menghasilkan tampilan; namanya selalu diawali huruf besar |
| **props** | nilai yang dikirim dari luar ke sebuah komponen |
| **children** | isi yang ditulis di antara tag pembuka dan penutup |
| **JSX** | sintaks mirip HTML di dalam berkas JavaScript |
| **state** | nilai yang bisa berubah dan menyebabkan tampilan digambar ulang (`useState`) |
| **props Inertia** | data dari controller Laravel yang sampai ke komponen halaman |
| **route name** | nama panggilan sebuah URL, dipakai lewat `route('...')` |

---

## 7. Cara belajar tercepat: ubah sesuatu

Membaca saja lambat. Lakukan ini:

```bash
composer run dev
```

Buka `http://localhost:8000/berita`, lalu buka [Pages/News/Index.jsx](../resources/js/Pages/News/Index.jsx) di editor.

1. Ganti tulisan **"Belum ada berita"** menjadi apa pun. Simpan. Perhatikan browser berubah sendiri dalam sekejap — itu Vite.
2. Pada baris yang sama, ganti `text-text-primary` menjadi `text-lime-500`. Lihat warnanya berubah.
3. Tambahkan satu kategori baru ke dalam senarai `categories` di bagian atas berkas. Perhatikan chip barunya muncul tanpa Anda menulis HTML apa pun — itulah gunanya `.map()`.
4. Kembalikan seperti semula dengan `git restore resources/js/Pages/News/Index.jsx`.

Sepuluh menit begitu lebih membekas daripada sejam membaca.

---

## 8. Kalau tersesat

- **"Halaman ini kodenya di mana?"** — lihat URL-nya, cari di [routes/web.php](../routes/web.php), ikuti nama di `Inertia::render()`.
- **"Warna ini dari mana?"** — semua nama warna ada di [tailwind.config.js](../tailwind.config.js).
- **"Kenapa dibuat begini?"** — cek komentar di berkasnya lebih dulu; komentar di kode ini sengaja menjelaskan alasan, bukan mengulang isi kodenya. Kalau belum terjawab, cari di [CLAUDE.md](CLAUDE.md) atau tabel keputusan [PRD.md §13](PRD.md).
- **Layar putih dan halaman tidak muncul?** — buka Console di browser (`F12`). Pesan error React biasanya menyebut nama berkas dan nomor barisnya.
