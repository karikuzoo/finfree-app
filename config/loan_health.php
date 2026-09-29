<?php

/**
 * Batas penilaian kesehatan cicilan di kalkulator KPR (LoanHealthService).
 *
 * Disimpan di sini, bukan di kode, karena ini PATOKAN yang bisa disetel —
 * bukan rumus. Angkanya dipilih pengguna 29 Sep 2026.
 */
return [

    /*
     * Rasio cicilan terhadap pendapatan (Debt Service Ratio): seluruh cicilan
     * per bulan (KPR + cicilan lain) dibagi pendapatan per bulan, dalam
     * persen. Patokan umum bank di Indonesia: sampai 30% sehat, 30–40% perlu
     * waspada, di atas 40% berisiko.
     */
    'dsr_healthy_max' => 30,
    'dsr_caution_max' => 40,

    /*
     * Sisa uang per bulan setelah cicilan, pengeluaran, dan pajak tahunan/12, dalam
     * persen pendapatan. Rasio cicilan yang aman tetap bisa menyisakan
     * nyaris nol bila pengeluarannya besar — tanpa sisa, tidak ada ruang
     * untuk dana darurat atau kenaikan harga. Di bawah batas ini dinilai
     * waspada; minus selalu berisiko.
     */
    'residual_min_percentage' => 10,
];
