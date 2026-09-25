import AuthenticatedLayout from "@/Layouts/AuthenticatedLayout";
import DangerButton from "@/Components/DangerButton";
import InputError from "@/Components/InputError";
import Modal from "@/Components/Modal";
import SecondaryButton from "@/Components/SecondaryButton";
import { Head, useForm } from "@inertiajs/react";
import { useRef, useState } from "react";

/**
 * Data & pengaturan — cadangan dan pemulihan.
 *
 * Dua tombol dengan bobot yang sangat berbeda. Mengunduh cadangan tidak
 * mengubah apa pun; memulihkan MENGGANTI seluruh data keuangan dan tidak bisa
 * dibatalkan. Perbedaan itu harus terbaca dari tampilannya, bukan hanya dari
 * teksnya — karena itu pemulihan memakai tombol berwarna bahaya, dialog
 * konfirmasi tersendiri, dan menyebutkan apa yang akan hilang.
 */
export default function DataIndex({ counts }) {
    const [konfirmasi, setKonfirmasi] = useState(false);
    const berkasRef = useRef(null);

    const form = useForm({ berkas: null });

    const pilihBerkas = (e) => {
        const berkas = e.target.files?.[0] ?? null;
        form.setData("berkas", berkas);

        if (berkas) {
            setKonfirmasi(true);
        }
    };

    const batal = () => {
        setKonfirmasi(false);
        form.setData("berkas", null);
        form.clearErrors();

        // Direset supaya memilih berkas YANG SAMA lagi tetap memicu onChange.
        // Tanpa ini, pengguna yang membatalkan lalu berubah pikiran harus
        // memilih berkas lain dulu sebelum berkas aslinya mau terbaca.
        if (berkasRef.current) {
            berkasRef.current.value = "";
        }
    };

    const pulihkan = () => {
        form.post(route("data.restore"), {
            preserveScroll: true,
            forceFormData: true,
            onSuccess: batal,
        });
    };

    const adaData =
        counts.accounts + counts.transactions + counts.goals + counts.debts > 0;

    return (
        <AuthenticatedLayout>
            <Head title="Data & pengaturan" />

            <div className="mx-auto max-w-5xl px-4 py-10 sm:px-6 lg:px-8">
                <div>
                    <h1 className="text-2xl font-bold tracking-tight text-text-primary">
                        Data &amp; pengaturan
                    </h1>
                    <p className="mt-2 max-w-xl text-sm leading-relaxed text-text-secondary">
                        Kelola cadangan dan pahami bagaimana datamu dicatat.
                    </p>
                </div>

                <div className="mt-8 grid grid-cols-1 gap-6 lg:grid-cols-2">
                    <div className="rounded-card border border-border bg-bg-card p-5">
                        <h2 className="text-base font-semibold text-text-primary">
                            Cadangkan datamu
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                            Simpan seluruh rekening, transaksi, target, utang,
                            anggaran, catatan kalender, dan pengingat dalam satu
                            berkas JSON yang bisa dipulihkan kembali kapan saja.
                        </p>

                        <dl className="num-tabular mt-4 space-y-1.5 border-t border-border pt-3 text-xs">
                            {[
                                ["Rekening & aset", counts.accounts],
                                ["Transaksi", counts.transactions],
                                ["Target", counts.goals],
                                ["Utang", counts.debts],
                            ].map(([label, jumlah]) => (
                                <div key={label} className="flex justify-between gap-2">
                                    <dt className="text-text-muted">{label}</dt>
                                    <dd className="text-text-secondary">{jumlah}</dd>
                                </div>
                            ))}
                        </dl>

                        <div className="mt-5">
                            {/*
                                <a download>, BUKAN <Link> Inertia. Link
                                mengambil responsnya lewat XHR lalu berharap
                                menerima halaman Inertia; berhadapan dengan
                                unduhan berkas ia hanya diam — tidak ada yang
                                terunduh dan tidak ada pesan kesalahan.
                            */}
                            <a
                                href={route("data.download")}
                                download
                                className="inline-block rounded-lg bg-lime-500 px-5 py-2.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-base"
                            >
                                Unduh cadangan
                            </a>
                        </div>

                        <p className="mt-4 text-xs leading-relaxed text-text-muted">
                            Berkas ini berisi catatan keuanganmu tanpa enkripsi
                            tambahan. Simpan di tempat pribadi. Data profil —
                            nama, email, telepon — tidak ikut disertakan.
                        </p>
                    </div>

                    <div className="rounded-card border border-border bg-bg-card p-5">
                        <h2 className="text-base font-semibold text-text-primary">
                            Pulihkan dari berkas
                        </h2>
                        <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                            Memulihkan akan <strong className="text-text-primary">mengganti
                            seluruh</strong> data keuangan yang ada sekarang — termasuk
                            catatan kalender dan pengingat — dengan isi berkas. Bukan
                            menggabungkannya.
                        </p>

                        {adaData && (
                            <p className="mt-3 rounded-lg border border-border bg-bg-cardAlt p-3 text-xs leading-relaxed text-state-warning">
                                Akun ini sudah berisi data. Unduh cadangannya
                                terlebih dahulu bila belum — pemulihan tidak bisa
                                dibatalkan.
                            </p>
                        )}

                        <div className="mt-5">
                            <label
                                htmlFor="berkas"
                                className="inline-block cursor-pointer rounded-lg border border-border-strong px-5 py-2.5 text-sm font-semibold text-text-secondary transition hover:border-text-muted hover:text-text-primary focus-within:ring-2 focus-within:ring-lime-500"
                            >
                                Pilih berkas cadangan
                                <input
                                    id="berkas"
                                    ref={berkasRef}
                                    type="file"
                                    accept="application/json,.json"
                                    className="sr-only"
                                    onChange={pilihBerkas}
                                />
                            </label>
                        </div>

                        <InputError message={form.errors.berkas} className="mt-3" />

                        <p className="mt-4 text-xs leading-relaxed text-text-muted">
                            Hanya menerima berkas cadangan Arus. Riwayat
                            perhitungan tidak ikut dipulihkan — ia jejak audit
                            kalkulasi lama, bukan data yang Anda masukkan.
                        </p>
                    </div>
                </div>

                <CaraMenghitung />
            </div>

            <Modal show={konfirmasi} onClose={batal} maxWidth="md">
                <div className="p-6">
                    <h2 className="text-base font-semibold text-text-primary">
                        Ganti seluruh data dengan isi berkas?
                    </h2>
                    <p className="mt-2 text-sm leading-relaxed text-text-secondary">
                        Seluruh rekening, transaksi, target, utang, anggaran,
                        catatan kalender, dan pengingat yang ada sekarang akan
                        dihapus lalu diganti. Tindakan ini tidak bisa dibatalkan.
                    </p>

                    {form.data.berkas && (
                        <p className="mt-3 truncate rounded-lg border border-border bg-bg-cardAlt px-3 py-2 text-xs text-text-secondary">
                            {form.data.berkas.name}
                        </p>
                    )}

                    <InputError message={form.errors.berkas} className="mt-3" />

                    <div className="mt-5 flex justify-end gap-2">
                        <SecondaryButton onClick={batal}>Batal</SecondaryButton>
                        <DangerButton onClick={pulihkan} disabled={form.processing}>
                            {form.processing ? "Memulihkan…" : "Ganti data saya"}
                        </DangerButton>
                    </div>
                </div>
            </Modal>
        </AuthenticatedLayout>
    );
}

/**
 * Aturan hitung ditulis di layar, bukan hanya di kode.
 *
 * Angka keuangan yang tidak dijelaskan cara perolehannya membuat pengguna
 * menebak — dan tebakan yang keliru soal uang menghancurkan kepercayaan lebih
 * cepat daripada bug apa pun.
 */
function CaraMenghitung() {
    const aturan = [
        [
            "Kekayaan bersih",
            "Saldo awal + seluruh transaksi + penyesuaian nilai aset − sisa pokok utang. Transfer antar rekening tidak mengubah total kekayaan.",
        ],
        [
            "Arus kas",
            "Pemasukan − pengeluaran − pembayaran pokok. Transfer dan penyesuaian nilai tidak dihitung: keduanya tidak memindahkan uang ke luar atau ke dalam kekayaanmu.",
        ],
        [
            "Dana tujuan",
            "Dana tujuan adalah sebagian saldo rekening yang Anda tetapkan punya tujuan — uangnya tidak dipindahkan ke mana-mana. Gabungan seluruh tujuan pada satu rekening tidak boleh melebihi saldonya, dan hanya rekening bank atau tunai yang boleh menyimpannya.",
        ],
        [
            "Kebutuhan bulanan",
            "Rumus anuitas dengan imbal hasil dan inflasi yang kamu tetapkan pada tiap target — sama persis dengan Kalkulator, bukan pembagian rata.",
        ],
        [
            "Utang & bunga",
            "Mencatat utang tidak menambah saldo rekening. Pembayaran pokok mengurangi kas dan utang bersamaan; bunga dicatat terpisah sebagai pengeluaran.",
        ],
        [
            "Investasi",
            "Nilai diisi manual dalam rupiah. Arus tidak mengambil harga pasar, tidak menghitung kuantitas lot, dan tidak memberi nasihat investasi.",
        ],
    ];

    return (
        <div className="mt-6 rounded-card border border-border bg-bg-card p-5">
            <h2 className="text-base font-semibold text-text-primary">
                Cara Arus menghitung
            </h2>

            <dl className="mt-4 grid grid-cols-1 gap-x-8 gap-y-5 sm:grid-cols-2">
                {aturan.map(([judul, isi]) => (
                    <div key={judul}>
                        <dt className="text-sm font-semibold text-text-primary">
                            {judul}
                        </dt>
                        <dd className="mt-1 text-sm leading-relaxed text-text-secondary">
                            {isi}
                        </dd>
                    </div>
                ))}
            </dl>
        </div>
    );
}
