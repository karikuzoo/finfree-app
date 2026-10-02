/**
 * Penentu target (PRD FR-20..22): membantu pengguna yang belum tahu nominal
 * targetnya, lalu mengisikan hasilnya ke form tujuan.
 *
 * SHELL + STRATEGI, bukan satu form generik (PRD §6.2.1). Ketiga penentu
 * meminta isian yang berbeda dan menghasilkan bentuk yang berbeda — pensiun
 * butuh usia, darurat tidak punya tenggat, pendidikan punya inflasinya
 * sendiri. Setiap strategi di bawah membawa isiannya sendiri dan satu fungsi
 * `compute`; `Components/TargetPlanner.jsx` hanya menggambar isian dan
 * menampilkan hasil apa pun strateginya. Penentu keempat cukup menambah satu
 * strategi, tanpa `if/else` di komponennya.
 *
 * Hasilnya HANYA mengisi form. Pengguna memeriksa lalu menyimpan sendiri, dan
 * setoran bulanannya tetap dihitung ulang server lewat GoalCalculatorService.
 * Karena itu rumusnya cukup di sini, diuji `targetPlanners.test.mjs`.
 *
 * NOMINAL TARGET SELALU NILAI HARI INI. Form tujuan memakai pendekatan D-1:
 * inflasi menaikkan target yang tersimpan menjadi nilai masa depan. Penentu
 * yang mengeluarkan nominal masa depan akan membuat inflasi terhitung dua
 * kali — jadi inflasinya diserahkan ke kolom inflasi form, bukan dilipat ke
 * dalam nominal.
 *
 * TANPA ANGKA BAWAAN. Usia harapan hidup, inflasi pendidikan, dan imbal hasil
 * masa pensiun semuanya diisi pengguna. Angka bawaan cenderung diterima apa
 * adanya, dan aturan D-7 melarang angka yang tidak bersumber (lihat komentar
 * kolom imbal hasil di Goal/Create.jsx).
 *
 * Bentuk keluaran `compute(nilai, konteks)`:
 *  - `{ status: 'belum' }` — isian wajib belum lengkap; tidak ada yang ditampilkan.
 *  - `{ status: 'galat', errors: { kolom: pesan } }` — isian saling bertentangan.
 *  - `{ status: 'siap', isian, nama, rincian, catatan }`:
 *      `isian`   = `{ target_amount, months, mode, estimated_inflation_rate? }`
 *                  — `months` null bila `mode` = 'tanpa'; inflasi hanya ada
 *                  bila penentunya menanyakan inflasi.
 *      `nama`    = usulan nama tujuan, dipakai hanya bila kolom nama kosong.
 *      `rincian` = `[{ istilah, nilai, jenis: 'rupiah' | 'durasi' }]`.
 */

/** Sama dengan BATAS_BULAN di Goal/Create.jsx (StoreGoalRequest: < 60 tahun). */
export const BATAS_TAHUN = 59;

/** '' dan isian non-angka dianggap belum diisi. */
const angka = (v) => {
    if (v === '' || v === null || v === undefined) return null;
    const n = Number(v);

    return Number.isFinite(n) ? n : null;
};

/** Rate tahunan efektif -> bulanan, konvensi yang sama dengan goalCalculator.js. */
const bulanan = (persenTahunan) => Math.pow(1 + persenTahunan / 100, 1 / 12) - 1;

/**
 * FR-20 Dana pensiun.
 *
 * Kebutuhannya adalah dana yang cukup untuk membayar pengeluaran bulanan
 * sepanjang masa pensiun, sementara sisanya tetap berkembang. Dihitung dalam
 * NILAI HARI INI memakai imbal hasil riil — (1 + imbal hasil) / (1 + inflasi)
 * − 1 — karena pengeluaran ikut naik bersama inflasi selama pensiun:
 *
 *   kebutuhan = E × (1 − (1+i)^−m) / i × (1+i)      (i = 0: E × m)
 *
 * E = pengeluaran bulanan hari ini, m = bulan masa pensiun, i = imbal hasil
 * riil bulanan. Penarikan di AWAL bulan (× (1+i)): uang belanja bulan itu
 * diambil dulu baru sisanya berkembang — lebih hati-hati daripada akhir bulan.
 *
 * Inflasinya diisikan ke form, sehingga form menaikkan nominal ini ke nilai
 * saat pensiun tiba. Imbal hasil SELAMA pensiun sengaja terpisah dari imbal
 * hasil form, yang berlaku selama mengumpulkan — keduanya jarang sama.
 */
const pensiun = {
    key: 'pensiun',
    label: 'Dana pensiun',
    description:
        'Dari pengeluaran bulanan yang Anda inginkan saat pensiun, dalam nilai uang hari ini.',
    fields: [
        { key: 'usia', label: 'Usia sekarang', type: 'number', suffix: 'tahun' },
        { key: 'usia_pensiun', label: 'Usia pensiun', type: 'number', suffix: 'tahun' },
        {
            key: 'usia_harapan',
            label: 'Perkiraan usia harapan hidup',
            type: 'number',
            suffix: 'tahun',
            hint: 'Dana disiapkan sampai usia ini. Lebih aman melebihkan daripada kurang.',
        },
        {
            key: 'pengeluaran',
            label: 'Pengeluaran bulanan saat pensiun',
            type: 'currency',
            hint: 'Dalam nilai uang hari ini — berapa yang Anda butuhkan sebulan bila pensiun sekarang.',
        },
        {
            key: 'inflasi',
            label: 'Estimasi inflasi (% / tahun)',
            type: 'percent',
            max: 20,
            hint: 'Ikut diisikan ke kolom inflasi di form.',
        },
        {
            key: 'imbal_pensiun',
            label: 'Imbal hasil selama pensiun (% / tahun)',
            type: 'percent',
            max: 30,
            hint: 'Biarkan 0 bila dana pensiun tidak lagi diinvestasikan.',
        },
    ],
    initial: {
        usia: '',
        usia_pensiun: '',
        usia_harapan: '',
        pengeluaran: '',
        inflasi: '0',
        imbal_pensiun: '0',
    },
    compute(nilai) {
        const usia = angka(nilai.usia);
        const usiaPensiun = angka(nilai.usia_pensiun);
        const usiaHarapan = angka(nilai.usia_harapan);
        const pengeluaran = angka(nilai.pengeluaran);
        const inflasi = angka(nilai.inflasi) ?? 0;
        const imbal = angka(nilai.imbal_pensiun) ?? 0;

        if ([usia, usiaPensiun, usiaHarapan].includes(null) || !(pengeluaran > 0)) {
            return { status: 'belum' };
        }

        const errors = {};
        if (!Number.isInteger(usia) || usia < 1) {
            errors.usia = 'Isi usia dalam tahun penuh.';
        }
        if (!Number.isInteger(usiaPensiun) || usiaPensiun <= usia) {
            errors.usia_pensiun = 'Usia pensiun harus lebih tua dari usia sekarang.';
        } else if (usiaPensiun - usia > BATAS_TAHUN) {
            errors.usia_pensiun = `Jarak ke pensiun paling lama ${BATAS_TAHUN} tahun.`;
        }
        if (!Number.isInteger(usiaHarapan) || usiaHarapan <= usiaPensiun) {
            errors.usia_harapan = 'Usia harapan hidup harus lebih tua dari usia pensiun.';
        }
        if (inflasi < 0 || inflasi > 20) errors.inflasi = 'Inflasi antara 0 dan 20%.';
        if (imbal < 0 || imbal > 30) errors.imbal_pensiun = 'Imbal hasil antara 0 dan 30%.';

        if (Object.keys(errors).length) return { status: 'galat', errors };

        const bulanPensiun = (usiaHarapan - usiaPensiun) * 12;
        const riil = (1 + imbal / 100) / (1 + inflasi / 100) - 1;
        const i = bulanan(riil * 100);

        // |i| sangat kecil = imbal hasil menyamai inflasi; rumus anuitas
        // membagi hampir-nol, jadi pakai bentuk tanpa pertumbuhannya.
        const kebutuhan = Math.ceil(
            Math.abs(i) < 1e-12
                ? pengeluaran * bulanPensiun
                : (pengeluaran * (1 - Math.pow(1 + i, -bulanPensiun))) / i * (1 + i),
        );

        const bulanMenuju = (usiaPensiun - usia) * 12;

        return {
            status: 'siap',
            isian: {
                target_amount: kebutuhan,
                months: bulanMenuju,
                mode: 'waktu',
                estimated_inflation_rate: inflasi,
            },
            nama: 'Dana pensiun',
            rincian: [
                { istilah: 'Masa pensiun', nilai: bulanPensiun, jenis: 'durasi' },
                { istilah: 'Waktu menuju pensiun', nilai: bulanMenuju, jenis: 'durasi' },
                { istilah: 'Dana dibutuhkan (nilai hari ini)', nilai: kebutuhan, jenis: 'rupiah' },
                {
                    istilah: 'Setara saat pensiun tiba',
                    nilai: Math.round(kebutuhan * Math.pow(1 + inflasi / 100, bulanMenuju / 12)),
                    jenis: 'rupiah',
                },
            ],
            catatan:
                'Pengeluaran dianggap naik mengikuti inflasi selama pensiun, dan diambil di awal tiap bulan.',
        };
    },
};

/**
 * FR-21 Dana darurat: kelipatan pengeluaran bulanan menurut keadaan.
 *
 * Tanpa tenggat — targetnya "secepat mungkin", jadi form dipindah ke mode
 * "Tanpa tenggat". Sebagai gantinya ditampilkan perkiraan kapan tercapai dari
 * setoran yang sanggup disisihkan (kebalikan arah kalkulator lain), tanpa
 * imbal hasil: dana darurat disimpan di tempat yang bisa dicairkan kapan saja.
 */
export const KELIPATAN_DARURAT = {
    lajang: 3,
    menikah: 6,
    tidak_tetap: 12,
};

const darurat = {
    key: 'darurat',
    label: 'Dana darurat',
    description: 'Kelipatan pengeluaran bulanan, menurut keadaan Anda.',
    fields: [
        {
            key: 'pengeluaran',
            label: 'Pengeluaran bulanan saat ini',
            type: 'currency',
            hint: 'Seluruh kebutuhan rutin sebulan, termasuk cicilan.',
        },
        {
            key: 'keadaan',
            label: 'Keadaan Anda',
            type: 'choice',
            options: [
                { value: 'lajang', label: 'Lajang, tanpa tanggungan', hint: '3× pengeluaran' },
                { value: 'menikah', label: 'Menikah atau punya tanggungan', hint: '6× pengeluaran' },
                { value: 'tidak_tetap', label: 'Penghasilan tidak tetap', hint: '12× pengeluaran' },
            ],
        },
        {
            key: 'sanggup',
            label: 'Sanggup disisihkan per bulan (opsional)',
            type: 'currency',
            hint: 'Untuk memperkirakan kapan dana daruratnya terkumpul.',
        },
    ],
    initial: { pengeluaran: '', keadaan: '', sanggup: '' },
    compute(nilai, konteks = {}) {
        const pengeluaran = angka(nilai.pengeluaran);
        const kelipatan = KELIPATAN_DARURAT[nilai.keadaan];

        if (!(pengeluaran > 0) || !kelipatan) return { status: 'belum' };

        const target = Math.ceil(pengeluaran * kelipatan);
        const sanggup = angka(nilai.sanggup);
        const danaAwal = angka(konteks.initial_amount) ?? 0;

        const rincian = [{ istilah: `Target (${kelipatan}× pengeluaran)`, nilai: target, jenis: 'rupiah' }];

        if (sanggup > 0) {
            const kurang = Math.max(0, target - danaAwal);
            rincian.push({
                istilah: 'Perkiraan terkumpul dalam',
                nilai: Math.max(1, Math.ceil(kurang / sanggup)),
                jenis: 'durasi',
            });
        }

        return {
            status: 'siap',
            isian: { target_amount: target, months: null, mode: 'tanpa' },
            nama: 'Dana darurat',
            rincian,
            catatan:
                'Dana darurat dikumpulkan tanpa tenggat, jadi form beralih ke "Tanpa tenggat". Perkiraan waktunya tanpa imbal hasil, dan memperhitungkan dana awal di form.',
        };
    },
};

/**
 * FR-22 Dana pendidikan — satu jenjang per tujuan (MVP yang diizinkan PRD).
 * Beberapa jenjang dibuat sebagai beberapa tujuan.
 *
 * Biayanya diisi dalam nilai hari ini, dan inflasi PENDIDIKAN diisikan ke
 * kolom inflasi form — biaya sekolah biasanya naik lebih cepat daripada
 * inflasi umum, jadi angkanya ditanyakan tersendiri, bukan disamakan.
 */
const JENJANG = {
    sd: 'SD',
    smp: 'SMP',
    sma: 'SMA',
    kuliah: 'Kuliah',
};

const pendidikan = {
    key: 'pendidikan',
    label: 'Dana pendidikan',
    description: 'Biaya satu jenjang sekolah. Untuk beberapa jenjang, buat satu tujuan per jenjang.',
    fields: [
        {
            key: 'jenjang',
            label: 'Jenjang',
            type: 'choice',
            options: Object.entries(JENJANG).map(([value, label]) => ({ value, label })),
        },
        {
            key: 'biaya',
            label: 'Biaya yang disiapkan',
            type: 'currency',
            hint: 'Menurut biaya hari ini — mis. uang pangkal dan biaya tahun pertama di sekolah yang dituju.',
        },
        {
            key: 'tahun',
            label: 'Mulai masuk dalam',
            type: 'number',
            suffix: 'tahun lagi',
        },
        {
            key: 'inflasi',
            label: 'Kenaikan biaya pendidikan (% / tahun)',
            type: 'percent',
            max: 20,
            hint: 'Ikut diisikan ke kolom inflasi di form. Tanyakan ke sekolahnya bila ragu.',
        },
    ],
    initial: { jenjang: '', biaya: '', tahun: '', inflasi: '0' },
    compute(nilai) {
        const biaya = angka(nilai.biaya);
        const tahun = angka(nilai.tahun);
        const inflasi = angka(nilai.inflasi) ?? 0;
        const jenjang = JENJANG[nilai.jenjang];

        if (!jenjang || !(biaya > 0) || tahun === null) return { status: 'belum' };

        const errors = {};
        if (!Number.isInteger(tahun) || tahun < 1) {
            errors.tahun = 'Isi minimal 1 tahun, dalam tahun penuh.';
        } else if (tahun > BATAS_TAHUN) {
            errors.tahun = `Paling lama ${BATAS_TAHUN} tahun lagi.`;
        }
        if (inflasi < 0 || inflasi > 20) errors.inflasi = 'Kenaikan biaya antara 0 dan 20%.';

        if (Object.keys(errors).length) return { status: 'galat', errors };

        const target = Math.ceil(biaya);
        const months = tahun * 12;

        return {
            status: 'siap',
            isian: {
                target_amount: target,
                months,
                mode: 'waktu',
                estimated_inflation_rate: inflasi,
            },
            nama: `Dana pendidikan ${jenjang}`,
            rincian: [
                { istilah: 'Biaya hari ini', nilai: target, jenis: 'rupiah' },
                {
                    istilah: 'Perkiraan biaya saat masuk',
                    nilai: Math.round(target * Math.pow(1 + inflasi / 100, tahun)),
                    jenis: 'rupiah',
                },
            ],
            catatan: null,
        };
    },
};

export const TARGET_PLANNERS = [pensiun, darurat, pendidikan];
