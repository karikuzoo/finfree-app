<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Validasi pembuatan tujuan finansial (FR-01..FR-09).
 *
 * Batas atasnya sengaja disamakan dengan kalkulator publik
 * (GoalCalculatorController) — imbal hasil maksimal 30% dan inflasi maksimal
 * 20% per tahun. Kalau kedua pintu masuk memakai batas berbeda, pengguna bisa
 * menghitung sesuatu di kalkulator lalu gagal menyimpannya sebagai tujuan,
 * tanpa penjelasan yang masuk akal.
 */
class StoreGoalRequest extends FormRequest
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],

            'target_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],

            'initial_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'lt:target_amount'],

            // Opsional. Tujuan tanpa tenggat dikumpulkan terus-menerus —
            // dana darurat contoh khasnya — dan kolomnya di database memang
            // nullable. Tanpa tanggal, jangka waktunya tidak diketahui
            // sehingga setoran bulanan tidak dihitung; itu konsekuensi yang
            // diterima, bukan kesalahan.
            'target_date' => [
                'nullable',
                'date',
                'after:today',
                'before:'.now()->addYears(60)->toDateString(),
            ],

            'estimated_return_rate' => ['required', 'numeric', 'min:0', 'max:30'],

            'estimated_inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:20'],
        ];
    }

    public function messages(): array
    {
        return [
            'name.required' => 'Nama tujuan wajib diisi.',
            'name.max' => 'Nama tujuan maksimal 100 karakter.',

            'target_amount.required' => 'Nominal target wajib diisi.',
            'target_amount.min' => 'Nominal target harus lebih besar dari nol.',

            'initial_amount.lt' => 'Dana awal harus lebih kecil dari nominal target. Bila dana Anda sudah mencapai target, tujuan ini tidak perlu dibuat.',

            'target_date.after' => 'Tanggal target harus setelah hari ini.',
            'target_date.before' => 'Tanggal target maksimal 60 tahun dari sekarang.',

            'estimated_return_rate.required' => 'Estimasi imbal hasil wajib diisi.',
            'estimated_return_rate.max' => 'Estimasi imbal hasil di atas 30% per tahun tidak realistis untuk perencanaan jangka panjang.',

            'estimated_inflation_rate.max' => 'Estimasi inflasi di atas 20% per tahun tidak realistis.',
        ];
    }

    public function attributes(): array
    {
        return [
            'name' => 'nama tujuan',
            'target_amount' => 'nominal target',
            'initial_amount' => 'dana awal',
            'target_date' => 'tanggal target',
            'estimated_return_rate' => 'estimasi imbal hasil',
            'estimated_inflation_rate' => 'estimasi inflasi',
        ];
    }
}
