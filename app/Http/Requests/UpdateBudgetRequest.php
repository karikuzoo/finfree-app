<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Anggaran bulanan (PRD FR-74).
 *
 * Tidak ada aturan "pengeluaran harus lebih kecil dari pemasukan". Anggaran
 * yang tekor adalah keadaan nyata yang justru paling perlu terlihat —
 * menolaknya hanya memaksa pengguna mengarang angka supaya formulirnya mau
 * tersimpan, dan rencananya jadi bohong sejak awal. Kemampuan menabungnya
 * yang akan dilaporkan nol, bukan isiannya yang ditolak.
 */
class UpdateBudgetRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $uang = ['required', 'numeric', 'min:0', 'max:999999999999999.99'];

        return [
            'planned_income' => $uang,
            'planned_expenses' => $uang,
            'monthly_reserve' => $uang,
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'planned_income.required' => 'Pemasukan yang direncanakan wajib diisi.',
            'planned_income.min' => 'Pemasukan tidak boleh negatif.',
            'planned_expenses.required' => 'Kebutuhan & pengeluaran wajib diisi.',
            'planned_expenses.min' => 'Pengeluaran tidak boleh negatif.',
            'monthly_reserve.required' => 'Cadangan bulanan wajib diisi. Isi 0 bila tidak ada.',
            'monthly_reserve.min' => 'Cadangan bulanan tidak boleh negatif.',
        ];
    }
}
