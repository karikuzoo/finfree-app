<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validasi pengubahan tujuan finansial.
 *
 * Aturannya sama dengan StoreGoalRequest, KECUALI satu hal: tanggal target
 * boleh tetap bernilai apa adanya meski sudah lewat.
 *
 * Tanpa pengecualian itu, tujuan yang tenggatnya terlanjur lewat menjadi
 * mustahil disunting sama sekali — pengguna yang cuma ingin memperbaiki salah
 * ketik pada namanya akan ditolak oleh aturan tanggal yang tidak ia sentuh.
 * Yang dilarang adalah MENYETEL tenggat baru ke masa lalu, bukan membiarkan
 * yang sudah ada.
 */
class UpdateGoalRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->route('financialGoal')?->user_id === $this->user()?->id;
    }

    public function rules(): array
    {
        $tanggalSaatIni = $this->route('financialGoal')?->target_date?->toDateString();

        return [
            'name' => ['required', 'string', 'max:100'],

            'target_amount' => ['required', 'numeric', 'min:1', 'max:999999999999'],

            'initial_amount' => ['nullable', 'numeric', 'min:0', 'max:999999999999', 'lt:target_amount'],

            'target_date' => [
                'nullable',
                'date',
                // Boleh sama persis dengan nilai yang tersimpan sekarang —
                // termasuk bila nilai itu sudah lewat. Selain itu, tanggal
                // harus di masa depan.
                Rule::when(
                    fn () => $this->input('target_date') !== $tanggalSaatIni,
                    ['after:today'],
                ),
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

            'initial_amount.lt' => 'Dana awal harus lebih kecil dari nominal target.',

            'target_date.after' => 'Tanggal target baru harus setelah hari ini.',
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
