<?php

namespace App\Http\Controllers;

use App\Models\Transaction;
use App\Models\UserActivity;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Riwayat — linimasa PENUH apa yang pengguna lakukan di Arus, dipaginasi.
 *
 * Menggabungkan DUA sumber: peristiwa seputar tujuan (`user_activities`) dan
 * transaksi (`transactions`).
 *
 * Transaksi sengaja TIDAK disalin ke `user_activities` saat dicatat.
 * Menyalinnya akan melahirkan sumber kebenaran kedua yang langsung menyimpang:
 * menyunting nominal sebuah transaksi memperbaiki tabelnya, tetapi baris
 * riwayatnya tetap menyebut angka lama — dan menghapus transaksi meninggalkan
 * jejak tentang sesuatu yang sudah tidak ada. Digabung saat dibaca, riwayatnya
 * selalu ikut terkoreksi dengan sendirinya.
 *
 * Penggabungannya lewat UNION di basis data, bukan memuat kedua tabel lalu
 * menyatukannya di PHP. Riwayat transaksi tumbuh tanpa batas atas; memuat
 * seluruhnya hanya untuk menampilkan dua puluh baris akan melambat diam-diam
 * seiring pemakaian.
 *
 * Yang dipakai mengurutkan adalah `created_at` — KAPAN pengguna melakukannya,
 * bukan tanggal transaksinya. Ini catatan perbuatan, bukan buku besar;
 * transaksi bertanggal mundur yang baru dicatat hari ini memang termasuk
 * kegiatan hari ini. Tanggal transaksinya sendiri tetap ikut dikirim supaya
 * bisa disebut bila berbeda.
 *
 * Pengelompokan per HARI dilakukan di FRONTEND (History/Index.jsx):
 * mengelompokkan dulu baru memaginasi, supaya batas halaman selalu jatuh tepat
 * di antara dua hari, itu rumit tanpa manfaat nyata — aplikasi lain juga
 * membiarkan satu hari terpotong di batas halaman.
 */
class HistoryController extends Controller
{
    private const PER_PAGE = 20;

    public function index(Request $request): Response
    {
        $userId = $request->user()->id;

        $tujuan = UserActivity::query()
            ->where('user_id', $userId)
            ->selectRaw("type, goal_name AS label, amount, NULL::date AS occurred_on, created_at");

        $transaksi = Transaction::query()
            ->where('user_id', $userId)
            // Diawali "transaction:" supaya tidak pernah bertabrakan dengan
            // jenis dari user_activities bila keduanya suatu saat memakai kata
            // yang sama — frontend memisahkannya dari prefiks ini.
            ->selectRaw("CONCAT('transaction:', type) AS type, name AS label, amount, occurred_on, created_at");

        $riwayat = DB::query()
            ->fromSub($tujuan->unionAll($transaksi), 'riwayat')
            ->orderByDesc('created_at')
            ->orderByDesc('label')
            ->paginate(self::PER_PAGE)
            ->through(fn ($baris) => [
                'type' => $baris->type,
                'label' => $baris->label,
                'amount' => $baris->amount !== null ? (float) $baris->amount : null,
                'occurred_on' => $baris->occurred_on,
                'occurred_at' => \Illuminate\Support\Carbon::parse($baris->created_at)->toIso8601String(),
            ]);

        return Inertia::render('History/Index', [
            'activities' => $riwayat,
        ]);
    }
}
