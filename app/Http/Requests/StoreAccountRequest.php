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

        $jenis = ['required', Rule::in(AccountKind::values())];

        // Jenis dikunci begitu ada transaksi: lihat komentar kelas.
        //
        // Disusun dengan `if` biasa, BUKAN Rule::when(). Argumen kedua
        // Rule::when() adalah array yang sudah terlanjur dibangun, jadi
        // `$rekening->kind` tetap dievaluasi walau kondisinya false — dan saat
        // menambah rekening baru `$rekening` masih null.
        if ($rekening !== null
            && $rekening->transactions()->exists()) {
            $jenis[] = Rule::in([$rekening->kind->value]);
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => $jenis,
            'institution' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
            // FR-51. Hanya untuk jenis bersatuan (emas, saham, reksa dana);
            // untuk bank dan tunai diabaikan — lihat dataRekening().
            'units' => ['nullable', 'numeric', 'min:0', 'max:9999999999999999'],
        ];
    }

    /**
     * Data tervalidasi, dengan `units` dipaksa NULL untuk jenis tanpa satuan.
     *
     * Bukan ditolak: rekening emas yang diganti menjadi bank (selama belum
     * bertransaksi) masih membawa berat gramnya dari form, dan yang benar
     * adalah membuangnya, bukan menggagalkan simpanannya.
     *
     * @return array<string, mixed>
     */
    public function dataRekening(): array
    {
        $data = $this->validated();
        $data['units'] = AccountKind::from($data['kind'])->satuan() === null || ($data['units'] ?? '') === ''
            ? null
            : $data['units'];

        return $data;
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
            'units.numeric' => 'Jumlahnya harus berupa angka.',
            'units.min' => 'Jumlahnya tidak boleh negatif.',
        ];
    }
}
