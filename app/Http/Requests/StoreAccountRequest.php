<?php

namespace App\Http\Requests;

use App\Enums\AccountKind;
use App\Support\Currencies;
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
        $mataUang = ['required_if:kind,'.AccountKind::ForeignCurrency->value, 'nullable', Rule::in(Currencies::codes())];

        // Jenis dikunci begitu ada transaksi: lihat komentar kelas.
        //
        // Disusun dengan `if` biasa, BUKAN Rule::when(). Argumen kedua
        // Rule::when() adalah array yang sudah terlanjur dibangun, jadi
        // `$rekening->kind` tetap dievaluasi walau kondisinya false — dan saat
        // menambah rekening baru `$rekening` masih null.
        if ($rekening !== null
            && $rekening->transactions()->exists()) {
            $jenis[] = Rule::in([$rekening->kind->value]);

            // Mata uang valas ikut terkunci: riwayatnya dicatat untuk mata
            // uang itu, dan USD yang diganti SGD membuat jumlah valas di
            // keterangan salah tanpa ada yang menyadarinya.
            if ($rekening->kind === AccountKind::ForeignCurrency) {
                $mataUang[] = Rule::in([$rekening->currency]);
            }
        }

        return [
            'name' => ['required', 'string', 'max:100'],
            'kind' => $jenis,
            // Hanya untuk valas, dan di sana wajib — tanpa mata uang, jumlah
            // valasnya tidak punya arti. Untuk jenis lain dibuang di dataRekening().
            'currency' => $mataUang,
            'institution' => ['nullable', 'string', 'max:100'],
            'opening_balance' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
            // FR-51. Hanya untuk jenis bersatuan (emas, saham, reksa dana,
            // valas); untuk bank dan tunai diabaikan — lihat dataRekening().
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
        $jenis = AccountKind::from($data['kind']);

        $data['units'] = ! $jenis->punyaSatuan() || ($data['units'] ?? '') === ''
            ? null
            : $data['units'];
        $data['currency'] = $jenis === AccountKind::ForeignCurrency ? $data['currency'] : null;

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
            'currency.required_if' => 'Pilih mata uangnya.',
            'currency.in' => 'Mata uang tidak bisa diubah karena rekening ini sudah punya riwayat transaksi, atau belum tersedia.',
            'institution.max' => 'Nama lembaga maksimal 100 karakter.',
            'opening_balance.required' => 'Saldo awal wajib diisi. Isi 0 bila mulai dari kosong.',
            'opening_balance.numeric' => 'Saldo awal harus berupa angka.',
            'opening_balance.min' => 'Saldo awal tidak boleh negatif.',
            'units.numeric' => 'Jumlahnya harus berupa angka.',
            'units.min' => 'Jumlahnya tidak boleh negatif.',
        ];
    }
}
