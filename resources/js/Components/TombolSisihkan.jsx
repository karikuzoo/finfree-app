import CurrencyInput from "@/Components/CurrencyInput";
import InputError from "@/Components/InputError";
import { formatRupiah } from "@/utils/format";
import { useForm } from "@inertiajs/react";
import { useState } from "react";

/**
 * "Sudah saya sisihkan" — satu klik menaikkan dana tujuan sebesar SISA
 * alokasi bulan ini (`remaining_this_month`), bukan alokasi penuhnya: yang
 * sudah disisihkan bulan ini tidak disarankan dua kali.
 *
 * Sebelum ini pengguna harus membuka form alokasi dan mengetik ulang TOTAL
 * barunya: menghitung sendiri 10.000.000 + 3.750.000. Aritmetika yang memang
 * tugas aplikasi.
 *
 * "Jumlah lain…" untuk bulan yang tidak pas dengan saran — separuhnya, atau
 * lebih dari itu. Yang diketik tetap TAMBAHANNYA, bukan total baru; endpoint
 * yang sama yang menjumlahkan.
 *
 * Yang sudah disisihkan bulan ini ditampilkan apa adanya, termasuk bila
 * jumlahnya belum sebanyak yang disarankan — rencana yang hanya mengenal
 * "sudah" dan "belum" memaksa orang berbohong pada dirinya sendiri di bulan
 * yang cuma sanggup separuh.
 */
export default function TombolSisihkan({ baris }) {
    const form = useForm({ amount: "" });
    const [jumlahLain, setJumlahLain] = useState(false);

    const kirim = (nominal) => {
        // transform() dipanggil TERPISAH, tidak dirantai.
        //
        // Di adapter React, transform() mengembalikan undefined — ia hanya
        // memasang callback-nya ke sebuah ref. Merantainya seperti di adapter
        // Vue (`form.transform(...).post(...)`) melempar TypeError, dan
        // tombolnya diam sepenuhnya: tidak ada yang terkirim, tidak ada pesan
        // galat di layar.
        //
        // Dan memang harus lewat transform: tombol utama membaca nominalnya
        // dari props SAAT dikirim. Setelah sekali berhasil, sisa bulan ini
        // berubah, sedangkan state useForm tidak ikut diperbarui — klik kedua
        // akan mengirim angka yang sudah basi.
        form.transform(() => ({ amount: nominal }));
        form.post(route("goals.set-aside", baris.goal_id), {
            preserveScroll: true,
            onSuccess: () => {
                setJumlahLain(false);
                form.reset();
            },
        });
    };

    const bukaJumlahLain = () => {
        form.clearErrors();
        form.setData("amount", baris.remaining_this_month || "");
        setJumlahLain(true);
    };

    const kirimJumlahLain = (e) => {
        e.preventDefault();
        kirim(form.data.amount);
    };

    const sudah = baris.set_aside_this_month;
    const sisa = baris.remaining_this_month;

    if (baris.achieved) {
        return null;
    }

    // Tombolnya TIDAK pernah lenyap begitu saja. Versi pertama
    // menyembunyikannya saat alokasinya nol, dan hasilnya sebuah halaman yang
    // menyuruh menyisihkan uang tanpa menyediakan caranya — tanpa satu kata
    // pun menjelaskan sebabnya.
    if (!baris.can_set_aside) {
        return (
            <p className="text-xs leading-relaxed text-text-muted">
                Tentukan dulu rekening tempat dananya berada lewat
                &ldquo;Sesuaikan target&rdquo;.
            </p>
        );
    }

    const tautanJumlahLain = (
        <button
            type="button"
            onClick={bukaJumlahLain}
            className="rounded-md text-sm font-medium text-text-secondary underline decoration-dotted underline-offset-2 transition hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
        >
            {sisa > 0 ? "Jumlah lain…" : "Sisihkan lagi…"}
        </button>
    );

    return (
        <div className="min-w-0">
            {jumlahLain ? (
                <form
                    onSubmit={kirimJumlahLain}
                    className="flex flex-wrap items-center gap-2"
                >
                    <label htmlFor={`sisihkan-${baris.goal_id}`} className="sr-only">
                        Nominal yang disisihkan untuk {baris.name}
                    </label>
                    <div className="w-44">
                        <CurrencyInput
                            id={`sisihkan-${baris.goal_id}`}
                            className="py-1.5 text-sm"
                            value={form.data.amount}
                            onChange={(v) => form.setData("amount", v)}
                            autoFocus
                        />
                    </div>
                    <button
                        type="submit"
                        disabled={form.processing || !form.data.amount}
                        className="rounded-lg bg-lime-500 px-3 py-1.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                    >
                        {form.processing ? "Menyimpan…" : "Sisihkan"}
                    </button>
                    <button
                        type="button"
                        onClick={() => {
                            setJumlahLain(false);
                            form.clearErrors();
                        }}
                        className="rounded-md text-sm font-medium text-text-muted transition hover:text-text-primary focus:outline-none focus:ring-2 focus:ring-lime-500"
                    >
                        Batal
                    </button>
                    <p className="w-full text-xs text-text-muted">
                        Ditambahkan ke dana yang sudah terkumpul, bukan
                        menggantinya.
                    </p>
                </form>
            ) : (
                <div className="flex flex-wrap items-center gap-x-3 gap-y-1.5">
                    {sisa > 0 ? (
                        <button
                            type="button"
                            onClick={() => kirim(sisa)}
                            disabled={form.processing}
                            className="rounded-lg bg-lime-500 px-3 py-1.5 text-sm font-semibold text-onPrimary transition hover:bg-lime-400 disabled:opacity-60 focus:outline-none focus:ring-2 focus:ring-lime-500 focus:ring-offset-2 focus:ring-offset-bg-card"
                        >
                            {form.processing
                                ? "Menyimpan…"
                                : `Sudah saya sisihkan ${formatRupiah(sisa)}`}
                        </button>
                    ) : sudah > 0 ? (
                        <p className="text-xs leading-relaxed text-state-success">
                            Rencana bulan ini sudah terpenuhi.
                        </p>
                    ) : (
                        <p className="text-xs leading-relaxed text-text-muted">
                            Belum ada dana yang bisa dialokasikan untuk target
                            ini bulan ini.
                        </p>
                    )}
                    {tautanJumlahLain}
                </div>
            )}

            {sudah > 0 && (
                <p className="num-tabular mt-1.5 text-xs text-state-success">
                    Bulan ini sudah disisihkan {formatRupiah(sudah)}
                </p>
            )}

            <InputError message={form.errors.amount} className="mt-1.5" />
        </div>
    );
}
