<?php

namespace App\Support;

use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

/**
 * Parameter `?bulan=YYYY-MM` di halaman yang menampilkan satu bulan
 * (Dashboard, Transaksi). SATU tempat, karena dua salinan sudah menyimpang:
 * Dashboard memvalidasinya, Transaksi tidak — dan `/transaksi?bulan=abc`
 * berakhir 500 dengan pesan SQL (PostgreSQL menolak tanggal "abc-01").
 *
 * Nilai yang tidak sah DIABAIKAN, lalu jatuh ke bulan berjalan — bukan
 * ditolak dengan galat validasi. Ini parameter tampilan, bukan isian form:
 * tidak ada yang bisa diperbaiki pengguna, dan menolaknya di halaman GET
 * mengembalikan ke "halaman sebelumnya", yang bisa jadi alamat yang sama
 * lalu berputar (lihat ValidatesCalculatorQuery).
 */
final class MonthQuery
{
    /** Bulan yang diminta, atau bulan berjalan. Selalu tanggal 1 pukul 00:00. */
    public static function from(Request $request, string $key = 'bulan'): Carbon
    {
        $nilai = $request->query($key);

        // is_string: `?bulan[]=x` datang sebagai larik.
        if (is_string($nilai) && preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $nilai)) {
            // Tanda seru pada '!Y-m' mereset bagian tanggal yang tidak
            // disebutkan ke tanggal 1 pukul 00:00. Tanpa itu PHP mengisinya
            // dari HARI INI — pada tanggal 31, "2026-06" menjadi "31 Juni",
            // lalu meluber ke 1 Juli.
            return Carbon::createFromFormat('!Y-m', $nilai, config('app.timezone'));
        }

        return now(config('app.timezone'))->startOfMonth();
    }
}
