<?php

namespace App\Http\Requests;

use App\Enums\TransactionType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Dipakai untuk menambah MAUPUN mengubah transaksi.
 *
 * Dua kolom di tabel `transactions` hanya berlaku untuk jenis tertentu, dan
 * di sinilah aturannya ditegakkan (lihat komentar migrasinya): `to_account_id`
 * hanya untuk transfer, `debt_id` hanya untuk pembayaran. Keduanya dipaksa
 * KOSONG untuk jenis lain — bukan sekadar diabaikan. Nilai sisa yang
 * tertinggal dari pilihan sebelumnya akan membuat pengeluaran biasa
 * terhubung ke utang yang tidak pernah dibayar.
 *
 * Kepemilikan rekening dan utang diperiksa lewat `exists` yang disaring
 * `user_id` — tanpa itu, siapa pun bisa mencatat transaksi ke rekening orang
 * lain hanya dengan menebak ID-nya (CONTRIBUTING §7).
 *
 * Yang TIDAK diperiksa di sini: saldo tidak boleh minus dan pembayaran tidak
 * boleh melebihi sisa utang. Keduanya bergantung pada keadaan setelah
 * perubahan — lihat LedgerGuard.
 */
class StoreTransactionRequest extends FormRequest
{
    public function authorize(): bool
    {
        $transaksi = $this->route('transaction');

        return $transaksi === null || $transaksi->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $milikSaya = fn (string $tabel) => Rule::exists($tabel, 'id')
            ->where('user_id', $this->user()->id);

        $transfer = $this->input('type') === TransactionType::Transfer->value;
        $pembayaran = $this->input('type') === TransactionType::Payment->value;

        return [
            'account_id' => ['required', $milikSaya('accounts')],
            'type' => ['required', Rule::in(TransactionType::values())],
            'name' => ['required', 'string', 'max:100'],

            // Batas bawah sengaja tidak dipasang di sini: hanya `adjustment`
            // yang boleh negatif, dan itu diperiksa di after() supaya
            // pesannya bisa menyebut jenis transaksinya.
            'amount' => ['required', 'numeric', 'max:999999999999999.99'],

            'to_account_id' => [
                $transfer ? 'required' : 'prohibited',
                'nullable',
                $milikSaya('accounts'),
                'different:account_id',
            ],
            'debt_id' => [
                $pembayaran ? 'required' : 'prohibited',
                'nullable',
                $milikSaya('debts'),
            ],

            'category' => ['nullable', 'string', 'max:100'],
            'occurred_on' => ['required', 'date', 'before_or_equal:today'],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $jenis = TransactionType::tryFrom((string) $this->input('type'));
                $nominal = (float) $this->input('amount');

                if ($jenis === null) {
                    return;
                }

                if ($nominal == 0.0) {
                    $validator->errors()->add('amount', 'Nominal tidak boleh nol.');

                    return;
                }

                // Hanya penyesuaian nilai yang boleh turun. Untuk jenis lain,
                // arah uangnya sudah ditentukan oleh jenisnya sendiri —
                // "pengeluaran negatif" adalah pemasukan yang ditulis keliru.
                if ($nominal < 0 && ! $jenis->bolehNegatif()) {
                    $validator->errors()->add(
                        'amount',
                        'Nominal harus lebih dari nol. Nilai minus hanya untuk penyesuaian nilai aset.',
                    );
                }
            },
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'account_id.required' => 'Pilih rekening.',
            'account_id.exists' => 'Rekening tidak ditemukan.',
            'type.required' => 'Pilih jenis transaksi.',
            'type.in' => 'Jenis transaksi tidak dikenal.',
            'name.required' => 'Nama transaksi wajib diisi.',
            'name.max' => 'Nama transaksi maksimal 100 karakter.',
            'amount.required' => 'Nominal wajib diisi.',
            'amount.numeric' => 'Nominal harus berupa angka.',
            'to_account_id.required' => 'Pilih rekening tujuan transfer.',
            'to_account_id.prohibited' => 'Rekening tujuan hanya untuk transfer.',
            'to_account_id.exists' => 'Rekening tujuan tidak ditemukan.',
            'to_account_id.different' => 'Rekening tujuan harus berbeda dari rekening asal.',
            'debt_id.required' => 'Pilih utang yang dibayar.',
            'debt_id.prohibited' => 'Utang hanya diisi untuk pembayaran pokok.',
            'debt_id.exists' => 'Utang tidak ditemukan.',
            'category.max' => 'Kategori maksimal 100 karakter.',
            'occurred_on.required' => 'Tanggal wajib diisi.',
            'occurred_on.before_or_equal' => 'Tanggal transaksi tidak boleh di masa depan.',
        ];
    }
}
