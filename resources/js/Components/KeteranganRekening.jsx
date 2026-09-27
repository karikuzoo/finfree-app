import { formatRupiah } from "@/utils/format";

/**
 * Keterangan di bawah pilihan rekening pada form yang MENGAMBIL uang
 * (pengeluaran, transfer keluar, pembayaran utang).
 *
 * Tanpa ini, pengguna melihat saldo Rp 50 juta, mencatat pengeluaran
 * Rp 48 juta, lalu ditolak — karena Rp 5 juta di antaranya sudah ditandai
 * untuk sebuah tujuan, dan itu tidak terlihat di mana pun sampai ditolak.
 *
 * `kembalikan`: nominal lama transaksi yang sedang disunting bila ia juga
 * mengambil dari rekening yang sama. Saat menyunting, uang itu "kembali"
 * dulu sebelum nominal barunya diambil, jadi batasnya ikut bertambah.
 *
 * Angka-angkanya datang jadi dari AccountBalanceService::accountOptions —
 * definisi yang sama persis dengan yang dipakai LedgerGuard untuk menolak.
 */
export default function KeteranganRekening({ rekening, kembalikan = 0 }) {
    if (!rekening || rekening.allocated <= 0) {
        return null;
    }

    const bebas = rekening.free + kembalikan;
    const nama = rekening.allocated_goals.map((g) => g.name).join(", ");

    return (
        <p className="num-tabular mt-1.5 text-xs leading-relaxed text-text-muted">
            Bebas dipakai{" "}
            <span className="font-semibold text-text-primary">
                {formatRupiah(bebas)}
            </span>{" "}
            — {formatRupiah(rekening.allocated)} lainnya untuk {nama}.
        </p>
    );
}
