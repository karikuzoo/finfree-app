<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateDailySavingsTargetRequest extends FormRequest
{
    /**
     * Hanya pemilik goal yang boleh mengubah nominal harian miliknya —
     * `financialGoal` di sini adalah hasil route model binding, bukan
     * input dari body request, jadi aman diandalkan untuk pengecekan ini.
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
            'daily_savings_target' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'daily_savings_target.required' => 'Nominal harian wajib diisi.',
            'daily_savings_target.numeric' => 'Nominal harian harus berupa angka.',
            'daily_savings_target.min' => 'Nominal harian tidak boleh negatif.',
        ];
    }
}
