<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateAvatarRequest extends FormRequest
{
    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'avatar' => [
                'required',
                // `image` memeriksa isi berkasnya, bukan sekadar ekstensinya.
                'image',
                'mimes:jpg,jpeg,png,webp',
                // Batas dimensi ada demi memori, bukan demi ukuran berkas.
                // GD memakai 4 byte per piksel saat gambar dibuka, jadi
                // 3000×3000 sudah menghabiskan ±36 MB — dan itu belum termasuk
                // salinan hasil potong dan hasil perkecil. Dengan memory_limit
                // PHP bawaan 128 MB, angka yang lebih longgar berisiko
                // mematikan proses. Foto 3000 piksel pun sudah jauh melampaui
                // kebutuhan avatar 256 piksel.
                'dimensions:max_width=3000,max_height=3000',
                'max:4096',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'avatar.required' => 'Pilih berkas gambar terlebih dahulu.',
            'avatar.image' => 'Berkas yang dipilih bukan gambar.',
            'avatar.mimes' => 'Format yang didukung hanya JPG, PNG, dan WEBP.',
            'avatar.dimensions' => 'Ukuran gambar terlalu besar. Maksimal 3000 × 3000 piksel.',
            'avatar.max' => 'Ukuran berkas maksimal 4 MB.',
        ];
    }
}
