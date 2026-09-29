<?php

/**
 * Modul berita (PRD §6.5, keputusan D-16).
 *
 * Seluruh aturan yang menentukan ISI halaman Berita ada di sini, bukan di
 * kode (FR-28): kata kunci per kategori, media yang dipercaya, dan daftar
 * pengecualian. Semuanya lahir dari uji gerbang 27 Sep 2026 dan akan
 * disetel ulang seiring waktu — menyetelnya tidak boleh menuntut mengubah
 * kelas mana pun.
 */
return [

    /*
     * Kunci NewsData.io. Hanya pernah dibaca oleh job di backend
     * (NewsIngestService) — tidak pernah dikirim ke browser dalam bentuk apa
     * pun, dan tidak pernah dicatat ke log.
     */
    'api_key' => env('NEWSDATA_IO_API_KEY'),

    'endpoint' => 'https://newsdata.io/api/1/latest',

    'country' => 'id',
    'language' => 'id',

    /*
     * Hanya media ini yang diambil. Tanpa filter ini kueri "properti"
     * meloloskan harta mantan kepala lapas dan prakiraan cuaca — media umum
     * memakai kata-kata keuangan untuk berita yang bukan berita keuangan.
     * NewsData.io menerima paling banyak 5 domain per kueri pada paket gratis.
     */
    'domains' => ['kontan_co_id', 'cnbcindonesia', 'liputan6', 'detik', 'kompas'],

    /*
     * Kategori FR-17, urutannya urutan tampil. Tiap kategori punya:
     *
     * - `query`: kueri ke NewsData.io. SATU kueri per kategori, bukan satu
     *   kueri gabungan — uji gerbang menunjukkan gabungan OR tanpa batas
     *   kategori menurunkan ketepatan (berita konferensi guru ikut masuk).
     * - `pattern`: pola (regex, tanpa pembatas) untuk klasifikasi saat ingest,
     *   dicocokkan ke judul + ringkasan. Kategori kueri yang mengambil
     *   artikel itu diutamakan bila polanya cocok; bila tidak, kategori
     *   PERTAMA lain yang cocok. Bila tidak ada yang cocok sama sekali,
     *   artikelnya DIBUANG — artinya kata kuncinya hanya muncul di isi
     *   artikel, dan pada data sungguhan itu hampir selalu berita yang tidak
     *   relevan (Posyandu lolos lewat "perumahan", koin Romawi lewat
     *   "menabung"). Lihat PRD D-16.
     */
    'categories' => [
        'kebijakan-moneter' => [
            'label' => 'Kebijakan Moneter',
            'query' => '"BI Rate" OR "suku bunga" OR inflasi OR rupiah',
            'pattern' => 'BI[ -]?Rate|suku bunga|inflasi|rupiah|Bank Indonesia|The Fed|moneter',
        ],
        'pasar-saham' => [
            'label' => 'Pasar Saham',
            'query' => 'IHSG OR saham OR BEI',
            'pattern' => 'IHSG|saham|BEI\b|bursa|emiten|IPO',
        ],
        'properti' => [
            'label' => 'Properti',
            // Tanpa "perumahan": kata itu terutama muncul sebagai nama
            // Kementerian Pekerjaan Umum dan Perumahan, bukan pasar rumah.
            'query' => 'properti OR KPR OR "rumah subsidi"',
            'pattern' => 'properti|\bKPR\b|rumah subsidi|harga rumah|hunian|apartemen|pengembang|developer',
        ],
        'investasi' => [
            'label' => 'Investasi',
            // "emas" saja menangkap medali Asian Games.
            'query' => '"reksa dana" OR obligasi OR SBN OR "harga emas" OR "emas Antam"',
            'pattern' => 'reksa ?dana|obligasi|\bSBN\b|\bSBR\d*|\bORI\d*|sukuk|harga emas|emas Antam|logam mulia|investor|investasi',
        ],
        'tips-keuangan' => [
            'label' => 'Tips Keuangan',
            'query' => '"keuangan pribadi" OR "atur keuangan" OR menabung OR "dana darurat"',
            'pattern' => 'keuangan pribadi|atur(an)? keuangan|mengatur (gaji|keuangan)|menabung|dana darurat|literasi keuangan|gajian|tips (hemat|keuangan)',
        ],
    ],

    /*
     * Artikel yang judulnya cocok pola ini DIBUANG saat ingest. Semuanya
     * ditemukan lolos pada uji gerbang meski kueri dan kategorinya benar:
     * NewsData.io memberi kategori `business` pada ramalan zodiak "karier".
     * Berita harta pejabat ("Intip Deretan Properti …", LHKPN) lolos pola
     * Properti pada pengambilan sungguhan pertama.
     */
    'exclude' => 'zodiak|ramalan|shio|lowongan kerja|loker\b|prakiraan cuaca|cuaca hari ini|LHKPN|harta kekayaan|deretan (harta|properti|aset)',

    /*
     * Tampilkan foto artikel dari server penerbit? (PRD D-16)
     *
     * BAWAANNYA MATI. Syarat NewsData.io (dibaca 29 Sep 2026) menyatakan
     * gambar tetap milik penerbitnya dan NewsData.io tidak berhak memberi
     * izin memakainya — risikonya sepenuhnya di pihak Arus. Dimatikan secara
     * bawaan supaya server yang lupa diatur tidak diam-diam memajang foto
     * orang lain; pengembangan lokal menyalakannya lewat .env.
     *
     * Mati berarti tautan fotonya tidak dikirim ke browser sama sekali, dan
     * kartu memakai sampul kategori buatan sendiri. Tautannya tetap disimpan
     * saat ingest, jadi menyalakannya kembali tidak menuntut mengambil ulang.
     */
    'show_images' => (bool) env('NEWS_SHOW_IMAGES', false),

    // FR-30: artikel yang lebih tua dari ini dipangkas.
    'retention_days' => 30,

    /*
     * Cache dianggap BASI bila pengambilan terakhir yang berhasil lebih tua
     * dari ini. Job berjalan tiap jam; dua kali gagal berturut-turut sudah
     * layak diberi tahu ke pengguna lewat banner.
     */
    'stale_after_minutes' => 150,
];
