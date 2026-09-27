/**
 * Bank dan dompet digital yang dikenali, untuk lencana rekening.
 *
 * Lencananya DIBUAT SENDIRI — singkatan di atas warna khas lembaganya — bukan
 * salinan logo resmi. Logo adalah karya dan merek dagang pemiliknya; warna
 * dan nama pendek cukup untuk mengenali rekening sekilas.
 *
 * `nama` adalah yang disimpan ke kolom `institution` saat dipilih dari daftar;
 * `label` (bila ada) hanya tulisan pendeknya di pemilih yang sempit.
 * `alias` menampung cara lain orang menuliskannya ("Bank Central Asia",
 * "Bank BCA") — kolom itu dulu teks bebas, dan isian lama tetap harus
 * dikenali.
 *
 * `dariNama`: boleh dikenali dari NAMA rekening bila lembaganya kosong. Mati
 * untuk nama yang juga kata biasa: rekening "Dana darurat" bukan dompet DANA,
 * dan "Tabungan blu" belum tentu blu.
 */
export const LEMBAGA = [
    { id: 'bca', nama: 'BCA', singkatan: 'BCA', warna: '#005BAA', teks: '#FFFFFF', alias: ['bank central asia'], dariNama: true },
    { id: 'mandiri', nama: 'Mandiri', singkatan: 'MDR', warna: '#003A70', teks: '#F8B51C', alias: ['bank mandiri'], dariNama: true },
    { id: 'bri', nama: 'BRI', singkatan: 'BRI', warna: '#00529C', teks: '#FFFFFF', alias: ['bank rakyat indonesia', 'brimo'], dariNama: true },
    { id: 'bni', nama: 'BNI', singkatan: 'BNI', warna: '#F15A23', teks: '#FFFFFF', alias: ['bank negara indonesia'], dariNama: true },
    { id: 'bsi', nama: 'BSI', singkatan: 'BSI', warna: '#00A39D', teks: '#FFFFFF', alias: ['bank syariah indonesia'], dariNama: true },
    { id: 'btn', nama: 'BTN', singkatan: 'BTN', warna: '#1B4B9B', teks: '#FFD100', alias: ['bank tabungan negara'], dariNama: true },
    { id: 'cimb', nama: 'CIMB Niaga', label: 'CIMB', singkatan: 'CIMB', warna: '#B5121B', teks: '#FFFFFF', alias: ['cimb', 'octo'], dariNama: true },
    { id: 'permata', nama: 'Permata', singkatan: 'PMT', warna: '#00843D', teks: '#FFFFFF', alias: ['permatabank', 'bank permata'], dariNama: true },
    { id: 'danamon', nama: 'Danamon', singkatan: 'DMN', warna: '#F58220', teks: '#FFFFFF', alias: ['bank danamon'], dariNama: true },
    { id: 'ocbc', nama: 'OCBC', singkatan: 'OCBC', warna: '#E30613', teks: '#FFFFFF', alias: ['ocbc nisp'], dariNama: true },
    { id: 'maybank', nama: 'Maybank', singkatan: 'MAY', warna: '#FFC72C', teks: '#1A1A1A', alias: [], dariNama: true },
    { id: 'panin', nama: 'Panin', singkatan: 'PNN', warna: '#0072BC', teks: '#FFFFFF', alias: ['panin bank'], dariNama: true },
    { id: 'jenius', nama: 'Jenius', singkatan: 'JNS', warna: '#00A0DF', teks: '#FFFFFF', alias: ['btpn', 'smbc indonesia'], dariNama: true },
    { id: 'jago', nama: 'Jago', singkatan: 'JGO', warna: '#FDB813', teks: '#1A1A1A', alias: ['bank jago'], dariNama: false },
    { id: 'seabank', nama: 'SeaBank', singkatan: 'SEA', warna: '#FF6A13', teks: '#FFFFFF', alias: ['sea bank'], dariNama: true },
    { id: 'blu', nama: 'blu', singkatan: 'blu', warna: '#00AEEF', teks: '#FFFFFF', alias: ['blu by bca', 'bca digital'], dariNama: false },

    { id: 'gopay', nama: 'GoPay', singkatan: 'GoPay', warna: '#00AED6', teks: '#FFFFFF', alias: ['go pay', 'gopay tabungan'], dariNama: true, dompet: true },
    { id: 'ovo', nama: 'OVO', singkatan: 'OVO', warna: '#4C3494', teks: '#FFFFFF', alias: [], dariNama: true, dompet: true },
    { id: 'dana', nama: 'DANA', singkatan: 'DANA', warna: '#118EEA', teks: '#FFFFFF', alias: [], dariNama: false, dompet: true },
    { id: 'shopeepay', nama: 'ShopeePay', singkatan: 'SPay', warna: '#EE4D2D', teks: '#FFFFFF', alias: ['shopee pay'], dariNama: true, dompet: true },
];

/**
 * "PT Bank Central Asia Tbk." → "central asia"; "Bank BCA" → "bca".
 * Kata "bank" dibuang, jadi alias ditulis tanpanya juga dikenali.
 */
function rapikan(teks) {
    return String(teks ?? '')
        .toLowerCase()
        .replace(/\(persero\)|\bpt\b|\btbk\b|[.,()]/g, ' ')
        .replace(/\bbank\b/g, ' ')
        .replace(/\s+/g, ' ')
        .trim();
}

const kunci = (l) => [l.id, l.nama, ...l.alias].map(rapikan).filter(Boolean);

/** Lembaga dari isian kolom `institution` — cocok penuh, bukan sebagian. */
export function cariLembaga(teks) {
    const t = rapikan(teks);
    if (t === '') return null;

    return LEMBAGA.find((l) => kunci(l).includes(t)) ?? null;
}

/**
 * Lembaga dari NAMA rekening ("BCA - Utama") — salah satu KATA-nya harus sama
 * persis dengan kunci lembaga yang `dariNama`-nya menyala.
 */
function cariDariNama(nama) {
    const kata = rapikan(nama).split(/[^a-z0-9]+/).filter(Boolean);

    return LEMBAGA.find((l) => l.dariNama && kunci(l).some((k) => !k.includes(' ') && kata.includes(k))) ?? null;
}

/**
 * Lencana untuk sebuah rekening: lembaga yang dikenali, atau ikon jenisnya.
 *
 * Saham, reksa dana, emas, dan tunai SELALU memakai ikon jenisnya, bukan
 * lembaganya: yang ingin dikenali sekilas di sana adalah macam asetnya, dan
 * lembaganya (Bibit, Ajaib, Pluang…) terlalu beragam untuk didaftar.
 *
 * @returns {{ jenis: 'lembaga', lembaga: object } | { jenis: 'ikon', ikon: string }}
 */
export function lencanaRekening({ kind, institution, name }) {
    if (kind !== 'bank') {
        return { jenis: 'ikon', ikon: kind };
    }

    const lembaga = cariLembaga(institution) ?? (institution ? null : cariDariNama(name));

    return lembaga ? { jenis: 'lembaga', lembaga } : { jenis: 'ikon', ikon: 'bank' };
}
