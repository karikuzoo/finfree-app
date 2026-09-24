<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

/**
 * Dipakai untuk mencatat MAUPUN mengubah utang (PRD FR-70).
 *
 * `principal` adalah sisa pokok pada saat pengguna mulai memakai Arus, bukan
 * nilai pinjaman aslinya. Mencatatnya TIDAK menambah saldo rekening mana pun
 * — uangnya sudah lama diterima dan dibelanjakan. Pinjaman yang benar-benar
 * baru cair dicatat terpisah sebagai penyesuaian saldo, kalau tidak uangnya
 * akan terhitung sebagai penghasilan.
 *
 * Yang TIDAK diperiksa di sini: menurunkan `principal` di bawah jumlah yang
 * sudah terbayar akan membuat sisa utang negatif. Itu bergantung pada
 * keadaan setelah perubahan — lihat LedgerGuard.
 */
class StoreDebtRequest extends FormRequest
{
    public function authorize(): bool
    {
        $utang = $this->route('debt');

        return $utang === null || $utang->user_id === $this->user()->id;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:100'],
            'principal' => ['required', 'numeric', 'min:0.01', 'max:999999999999999.99'],

            // Boleh nol: tidak semua utang punya jadwal cicilan tetap —
            // pinjaman keluarga yang dibayar sebisanya tetap perlu tercatat.
            'monthly_principal' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],

            // Tanggal LAMPAU sengaja diizinkan. Utang yang sudah lewat jatuh
            // tempo justru yang paling perlu terlihat; menolaknya memaksa
            // pengguna memalsukan tanggal agar datanya bisa masuk.
            'due_on' => ['nullable', 'date'],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'name.required' => 'Nama utang wajib diisi.',
            'name.max' => 'Nama utang maksimal 100 karakter.',
            'principal.required' => 'Sisa pokok utang wajib diisi.',
            'principal.numeric' => 'Sisa pokok harus berupa angka.',
            'principal.min' => 'Sisa pokok harus lebih besar dari nol.',
            'monthly_principal.required' => 'Rencana pokok per bulan wajib diisi. Isi 0 bila tidak tetap.',
            'monthly_principal.numeric' => 'Rencana pokok per bulan harus berupa angka.',
            'monthly_principal.min' => 'Rencana pokok per bulan tidak boleh negatif.',
            'due_on.date' => 'Tanggal jatuh tempo tidak valid.',
        ];
    }
}
