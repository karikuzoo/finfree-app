<?php

namespace App\Http\Requests;

use App\Enums\RiskProfile;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdatePreferencesRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            // Daftar nilai diambil dari enum, bukan ditulis ulang di sini —
            // menambah profil risiko baru cukup dilakukan di satu tempat.
            'risk_profile' => ['required', Rule::enum(RiskProfile::class)],
            'prefers_syariah' => ['required', 'boolean'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'risk_profile.required' => 'Profil risiko wajib dipilih.',
            'risk_profile.*' => 'Profil risiko yang dipilih tidak dikenali.',
            'prefers_syariah.required' => 'Preferensi instrumen syariah wajib diisi.',
        ];
    }
}
