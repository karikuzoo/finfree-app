<?php

namespace App\Http\Requests;

use App\Enums\AccountKind;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Dipakai untuk menambah MAUPUN mengubah rekening — aturannya identik.
 *
 * Kecuali satu: `kind` tidak boleh diubah setelah rekening punya riwayat.
 * Mengubah rekening bank menjadi saham membuat dana target yang sudah
 * ditandai di sana mendadak berada di instrumen yang tidak boleh
 * menampungnya. Lihat `rules()`.
 */
class StoreAccountRequest extends FormRequest
{
    public function authorize(): bool
    {
        $rekening = $this->route('account');

        return $rekening === null || $rekening->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $rekening = $this->route('account');
        $punyaRiwayat = $rekening !== null
            && $rekening->transactions()->exists();

        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => [
                'required',
                Rule::in(AccountKind::values()),
                // Jenis dikunci begitu ada transaksi: lihat komentar kelas.
                Rule::when(
                    $punyaRiwayat,
                    [Rule::in([$rekening->kind->value])],
                ),
            ],
            'institution' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama rekening wajib diisi.',
            'name.max' => 'Nama rekening maksimal 100 karakter.',
            'kind.required' => 'Pilih jenis rekening.',
            'kind.in' => 'Jenis rekening tidak bisa diubah karena sudah punya riwayat transaksi.',
            'institution.max' => 'Nama lembaga maksimal 100 karakter.',
            'opening_balance.required' => 'Saldo awal wajib diisi. Isi 0 bila mulai dari kosong.',
            'opening_balance.numeric' => 'Saldo awal harus berupa angka.',
            'opening_balance.min' => 'Saldo awal tidak boleh negatif.',
        ];
    }
}
