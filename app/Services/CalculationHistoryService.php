<?php

namespace App\Services;

use App\Models\CalculationHistory;
use App\Models\User;

/**
 * Riwayat kalkulasi cepat (PRD FR-45).
 *
 * Dicatat OTOMATIS oleh controller kalkulator setiap kali pengguna yang login
 * menghitung — pengguna tidak perlu ingat menekan tombol simpan. Tamu tidak
 * dicatat sama sekali.
 *
 * Membuka kembali = membuka kalkulatornya dengan isian tersimpan sebagai
 * query (`url()`), lalu pengguna bebas mengubah parameternya. Karena itu yang
 * disimpan hanya isian, dan isian itu harus persis bentuk yang diterima
 * kalkulatornya lewat alamat.
 */
class CalculationHistoryService
{
    /** Batas per pengguna; yang lebih lama dibuang saat ada catatan baru. */
    public const LIMIT = 50;

    /** Nama route tiap kalkulator — juga daftar kalkulator yang dikenal. */
    public const ROUTES = [
        'goal' => 'calculator.goal',
        'loan' => 'calculator.loan',
        'investment' => 'calculator.investment',
    ];

    /**
     * Isian yang tidak boleh disimpan, apa pun asalnya. Saat ini isian ini
     * hanya datang lewat badan POST dan karenanya tidak pernah ada di
     * `$input` yang lolos validasi query — daftar ini penjaga kedua, supaya
     * perubahan di controller kelak tidak diam-diam mulai menyimpan data
     * keuangan pribadi (sama dengan UtilityCalculatorController::DATA_KEUANGAN).
     */
    private const TIDAK_DISIMPAN = ['monthly_income', 'income_growth', 'other_installments', 'monthly_expenses', 'annual_taxes'];

    /**
     * @param  array<string, mixed>  $input  isian query yang sudah lolos validasi
     * @param  array<string, int|float|string|null>  $summary  angka utama untuk daftar
     */
    public function record(?User $user, string $calculator, array $input, array $summary): void
    {
        if (! $user || ! isset(self::ROUTES[$calculator])) {
            return;
        }

        $input = $this->canonical(array_diff_key($input, array_flip(self::TIDAK_DISIMPAN)));
        $hash = hash('sha256', $calculator.'|'.json_encode($input));

        // upsert, bukan firstOrNew + save: dua hitungan bersamaan dengan isian
        // yang sama akan menabrak indeks unik dan berakhir 500. Isian yang
        // sama dihitung lagi = naik ke urutan teratas (updated_at baru).
        // upsert melewati cast model, jadi JSON-nya disandikan di sini.
        $sekarang = now();
        CalculationHistory::upsert([[
            'user_id' => $user->id,
            'calculator' => $calculator,
            'input_hash' => $hash,
            'input' => json_encode($input),
            'summary' => json_encode($summary),
            'created_at' => $sekarang,
            'updated_at' => $sekarang,
        ]], ['user_id', 'calculator', 'input_hash'], ['summary', 'updated_at']);

        $this->prune($user);
    }

    public function url(CalculationHistory $catatan): string
    {
        return route(self::ROUTES[$catatan->calculator], $catatan->input);
    }

    /**
     * Urutan kunci dibakukan supaya `?a=1&b=2` dan `?b=2&a=1` dianggap isian
     * yang sama. Urutan daftar (jenjang bunga) tetap, karena urutan itu
     * bermakna.
     */
    private function canonical(array $input): array
    {
        if (! array_is_list($input)) {
            ksort($input);
        }

        return array_map(fn ($v) => is_array($v) ? $this->canonical($v) : $v, $input);
    }

    private function prune(User $user): void
    {
        $simpan = $user->calculationHistories()
            ->orderByDesc('updated_at')
            ->orderByDesc('id')
            ->limit(self::LIMIT)
            ->pluck('id');

        $user->calculationHistories()->whereNotIn('id', $simpan)->delete();
    }
}
