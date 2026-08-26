<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ProfileUpdateRequest extends FormRequest
{
    /**
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            // Aturan nama sama dengan pendaftaran. Kalau di sini lebih longgar,
            // pengguna bisa mendaftar dengan nama yang sah lalu menggantinya
            // dengan apa pun lewat halaman profil.
            'name' => ['required', 'string', 'max:255', "regex:/^[\pL\s.'-]+$/u"],

            'email' => [
                'required',
                'string',
                'lowercase',
                'email',
                'max:255',
                Rule::unique(User::class)->ignore($this->user()->id),
            ],

            // Keempat field identitas bersifat opsional (PRD FR-3).
            'birth_date' => [
                'nullable',
                'date',
                // Batas bawah menyaring salah ketik tahun seperti "0195".
                'after:1900-01-01',
                // Anak di bawah 17 tahun tidak bisa membuka rekening efek
                // sendiri di Indonesia, jadi perencanaan investasi mandiri
                // belum relevan. Sekaligus menghindari mengumpulkan data
                // pribadi anak, yang diatur lebih ketat oleh UU PDP.
                'before_or_equal:'.now()->subYears(17)->toDateString(),
            ],
            'nationality' => ['nullable', 'string', 'max:100', "regex:/^[\pL\s.'-]+$/u"],
            // Sengaja longgar: nomor Indonesia ditulis bermacam-macam —
            // 08xx, +62 8xx, dengan spasi atau tanda hubung. Menolak salah satu
            // bentuk yang sah lebih merugikan daripada menerima yang tidak rapi.
            'phone' => ['nullable', 'string', 'max:20', 'regex:/^[0-9+\-\s()]{8,20}$/'],
            'occupation' => ['nullable', 'string', 'max:100'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.regex' => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
            'nationality.regex' => 'Kewarganegaraan hanya boleh berisi huruf.',
            'phone.regex' => 'Nomor telepon hanya boleh berisi angka, spasi, tanda hubung, kurung, dan tanda plus.',
            'birth_date.before_or_equal' => 'Usia minimal 17 tahun.',
            'birth_date.after' => 'Tanggal lahir tidak masuk akal.',
        ];
    }
}
