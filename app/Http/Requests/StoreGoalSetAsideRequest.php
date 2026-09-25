<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Menaikkan dana yang ditandai untuk sebuah target (PRD FR-85).
 *
 * Pengguna menyisihkan uang sesuai rencana, lalu menekan satu tombol untuk
 * mengatakan "sudah". Yang dikirim adalah NOMINAL YANG DISISIHKAN, bukan total
 * barunya — aplikasi yang menjumlahkan. Sebelum ini pengguna harus membuka
 * form alokasi dan mengetik ulang total yang benar, yaitu menghitung sendiri
 * 10.000.000 + 3.750.000; aritmetika yang memang tugas aplikasi.
 *
 * Rekeningnya tidak ikut dikirim: uang itu disisihkan ke tempat yang sudah
 * ditentukan target ini. Target yang belum punya rekening tidak bisa memakai
 * tombol ini sama sekali — lihat controller.
 */
class StoreGoalSetAsideRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('financialGoal')->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal yang disisihkan wajib diisi.',
            'amount.numeric' => 'Nominal harus berupa angka.',
            'amount.min' => 'Nominal harus lebih besar dari nol.',
        ];
    }
}
