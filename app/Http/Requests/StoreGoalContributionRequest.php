<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreGoalContributionRequest extends FormRequest
{
    /**
     * Sama seperti UpdateDailySavingsTargetRequest — hanya pemilik goal
     * yang boleh mencatat setoran ke goal tersebut.
     */
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
            'contributed_on' => ['required', 'date', 'before_or_equal:today'],
            'note' => ['nullable', 'string', 'max:500'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'amount.required' => 'Nominal setoran wajib diisi.',
            'amount.numeric' => 'Nominal setoran harus berupa angka.',
            'amount.min' => 'Nominal setoran minimal Rp 1.',
            'contributed_on.required' => 'Tanggal setoran wajib diisi.',
            'contributed_on.before_or_equal' => 'Tanggal setoran tidak boleh di masa depan.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ];
    }
}
