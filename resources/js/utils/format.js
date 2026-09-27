/**
 * Pemformatan angka untuk tampilan.
 *
 * Backend selalu mengirim uang sebagai angka mentah, bukan string terformat
 * (CLAUDE.md §10.1). Merapikannya adalah tugas frontend, dan seluruhnya
 * dikerjakan di berkas ini supaya formatnya seragam di semua halaman.
 */

const idNumber = new Intl.NumberFormat('id-ID');

/** 1500000 -> "1.500.000" */
export function formatNumber(value) {
    if (value === null || value === undefined || value === '') return '';

    return idNumber.format(Math.round(Number(value)));
}

/** 1500000 -> "Rp 1.500.000" */
export function formatRupiah(value) {
    if (value === null || value === undefined || value === '') return '';

    return `Rp ${formatNumber(value)}`;
}

/**
 * Bentuk ringkas untuk sumbu grafik dan ruang sempit.
 * 1500000 -> "Rp 1,5 jt" ; 2750000000 -> "Rp 2,75 M"
 */
export function formatCompactRupiah(value) {
    const n = Number(value) || 0;
    const abs = Math.abs(n);

    // Membuang nol di belakang koma saja: "2,0 jt" -> "2 jt", "1,50" -> "1,5".
    //
    // Versi sebelumnya memakai /\.?0+$/ — titiknya opsional, sehingga pola itu
    // juga memakan nol pada bilangan BULAT: 50 menjadi "5", dan 100 menjadi
    // "1". Akibatnya Rp 100.000 tampil sebagai "Rp 1 rb", meleset seratus kali
    // lipat. Titik kini wajib ada agar hanya bagian desimal yang tersentuh.
    const trim = (x, digits) =>
        x
            .toFixed(digits)
            .replace(/(\.\d*?)0+$/, '$1') // nol berlebih setelah titik
            .replace(/\.$/, '') // titik yang jadi menggantung
            .replace('.', ',');

    if (abs >= 1_000_000_000) return `Rp ${trim(n / 1_000_000_000, 2)} M`;
    if (abs >= 1_000_000) return `Rp ${trim(n / 1_000_000, 1)} jt`;
    if (abs >= 1_000) return `Rp ${trim(n / 1_000, 0)} rb`;

    return `Rp ${Math.round(n)}`;
}

/**
 * Membacakan nominal dengan KATA satuannya: 125000000000 -> "125 miliar".
 *
 * Bukan sekadar versi lain dari formatCompactRupiah. Yang itu memakai "M",
 * yang bisa dibaca sebagai miliar maupun million; yang ini sengaja mengeja
 * satuannya karena justru di situlah gunanya — ia dipasang di bawah kolom
 * nominal supaya salah ketik jumlah nol ketahuan SAAT mengetik.
 *
 * Kasus nyata yang melatarbelakanginya: sebuah target terisi
 * Rp 125.000.000.000 padahal maksudnya 125 juta. Deretan titiknya terlalu
 * mirip untuk dihitung sekilas, dan barunya ketahuan setelah rencana
 * menabungnya menuntut Rp 2,26 miliar per bulan.
 *
 * Mengembalikan string kosong di bawah satu juta: "delapan ratus ribu" tidak
 * menambah kejelasan apa pun, dan keterangan yang selalu muncul berhenti
 * dibaca.
 *
 * Naik satuan bila pembulatannya mencapai 1000: 999.999.999 dibaca
 * "1 miliar", bukan "1000 juta" — sedangkan 999.990.000 tetap "999,99 juta".
 */
export function spellRupiah(value) {
    const n = Math.abs(Number(value) || 0);

    const satuan = [
        [1_000_000, 'juta'],
        [1_000_000_000, 'miliar'],
        [1_000_000_000_000, 'triliun'],
    ];

    let i = satuan.findLastIndex(([nilai]) => n >= nilai);

    if (i === -1) {
        return '';
    }

    let bulat = Number((n / satuan[i][0]).toFixed(2));

    if (bulat >= 1000 && i < satuan.length - 1) {
        i += 1;
        bulat = Number((n / satuan[i][0]).toFixed(2));
    }

    const angka = bulat
        .toFixed(2)
        .replace(/(\.\d*?)0+$/, '$1')
        .replace(/\.$/, '')
        .replace('.', ',');

    return `${angka} ${satuan[i][1]}`;
}

const idDesimal = new Intl.NumberFormat('id-ID', { maximumFractionDigits: 4 });

/** 1234.5678 -> "1.234,5678"; 10.5 -> "10,5". Sampai empat desimal. */
export function formatDesimal(value) {
    if (value === null || value === undefined || value === '') return '';

    return idDesimal.format(Number(value));
}

/**
 * Teks angka berdesimal gaya Indonesia -> angka. "10,5" -> 10.5;
 * "1.234,5678" -> 1234.5678; "1.000" -> 1000.
 *
 * Titik tanpa koma ambigu: "1.000" hampir pasti seribu, tetapi "10.5" hampir
 * pasti sepuluh setengah (diketik dari papan ketik berformat Inggris). Titik
 * tunggal yang diikuti TEPAT tiga angka dibaca pemisah ribuan; selain itu
 * dibaca koma desimal.
 *
 * Mengembalikan '' untuk isian kosong atau bukan angka.
 */
export function parseDesimal(text) {
    let s = String(text ?? '').trim().replace(/\s/g, '');
    if (s === '') return '';

    if (s.includes(',')) {
        s = s.replace(/\./g, '').replace(',', '.');
    } else if (/^\d+\.\d+$/.test(s) && !/^\d{1,3}\.\d{3}$/.test(s)) {
        // "10.5" — titik sebagai desimal. Biarkan.
    } else {
        s = s.replace(/\./g, '');
    }

    const n = Number(s);

    return Number.isFinite(n) ? n : '';
}

/**
 * Keterangan jumlah satuan aset untuk kartu (PRD FR-51):
 * { jumlah: "10,5 gram", perSatuan: "≈ Rp 1.450.000/gram" }.
 *
 * Harga per satuan diturunkan dari nilai rupiah yang tercatat — bukan harga
 * pasar — jadi ia hanya sepeka nilai terakhir yang dimasukkan. Justru itu
 * gunanya: "Rp 3.000.000/gram" untuk emas langsung terbaca janggal.
 *
 * Saham dihitung per LEMBAR (1 lot = 100 lembar), karena harga saham selalu
 * disebut per lembar.
 */
export function keteranganSatuan({ units, unit, value }) {
    const n = Number(units);
    if (!unit || units === null || units === undefined || units === '' || !(n > 0)) return null;

    const pembagi = unit === 'lot' ? n * 100 : n;
    const perNama = unit === 'lot' ? 'lembar' : unit;

    return {
        jumlah: `${formatDesimal(n)} ${unit}`,
        perSatuan: `≈ ${formatRupiah(Number(value) / pembagi)}/${perNama}`,
    };
}

/** "1.500.000" atau "Rp 1.500.000" -> 1500000 */
export function parseNumber(text) {
    const digits = String(text).replace(/[^\d]/g, '');

    return digits === '' ? '' : Number(digits);
}

/**
 * 126 -> "10 tahun 6 bulan". Dipakai untuk menerjemahkan input jangka waktu
 * yang satuannya bulan menjadi kalimat yang lebih mudah dibayangkan.
 */
export function formatDuration(months) {
    const n = Number(months);
    if (!n || n < 1) return '';

    const years = Math.floor(n / 12);
    const rest = n % 12;

    if (years === 0) return `${rest} bulan`;
    if (rest === 0) return `${years} tahun`;

    return `${years} tahun ${rest} bulan`;
}
