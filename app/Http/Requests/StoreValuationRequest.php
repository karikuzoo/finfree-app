<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Penilaian ulang sebuah rekening (PRD FR-72).
 *
 * Yang diminta adalah NILAI TOTAL rekening sekarang, bukan selisihnya.
 * Orang tahu portofolionya bernilai 19 juta hari ini; tidak ada yang tahu
 * bahwa itu berarti "naik 1.000.000 sejak terakhir dicatat". Selisihnya
 * dihitung AccountValuationController, lalu disimpan sebagai transaksi
 * penyesuaian — sehingga perubahan nilainya tetap punya jejak dan bisa
 * dibatalkan seperti transaksi lain.
 */
class StoreValuationRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('account')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Boleh nol — aset bisa habis terjual atau jatuh tak bernilai.
            // Tidak boleh negatif: tidak ada aset yang bernilai kurang dari
            // kosong, dan angka minus di sini selalu berarti salah ketik.
            'value' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'value.required' => 'Nilai terkini wajib diisi.',
            'value.numeric' => 'Nilai terkini harus berupa angka.',
            'value.min' => 'Nilai terkini tidak boleh negatif.',
            'occurred_on.required' => 'Tanggal penilaian wajib diisi.',
            'occurred_on.before_or_equal' => 'Tanggal penilaian tidak boleh di masa depan.',
        ];
    }
}
