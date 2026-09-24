<?php

namespace App\Http\Requests;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

/**
 * Menandai dana sebuah rekening untuk satu target (PRD FR-73).
 *
 * Dua aturan yang ditegakkan di sini:
 *
 * 1. Rekeningnya harus LIKUID — bank atau tunai. Menandai sebagian portofolio
 *    saham sebagai "dana DP rumah" menjanjikan kepastian yang tidak dimiliki
 *    instrumen bernilai fluktuatif: nilainya bisa turun setelah ditandai, dan
 *    targetnya meleset tanpa ada yang menyadarinya.
 * 2. Alokasi tidak boleh melebihi nominal targetnya sendiri. Menandai lebih
 *    banyak daripada yang dibutuhkan hanya mengunci uang tanpa alasan, dan
 *    membuat progres tampil di atas 100%.
 *
 * Yang TIDAK diperiksa di sini: total alokasi seluruh target pada rekening
 * yang sama tidak boleh melebihi saldonya. Itu bergantung pada keadaan
 * setelah perubahan — lihat LedgerGuard.
 */
class UpdateGoalAllocationRequest extends FormRequest
{
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
            'account_id' => [
                'nullable',
                Rule::exists('accounts', 'id')
                    ->where('user_id', $this->user()->id)
                    ->whereIn('kind', AccountKind::nilaiLikuid()),
            ],
            'allocated_amount' => ['required', 'numeric', 'min:0', 'max:999999999999999.99'],
            'priority' => ['required', Rule::in(GoalPriority::values())],
        ];
    }

    public function after(): array
    {
        return [
            function (Validator $validator) {
                $goal = $this->route('financialGoal');
                $alokasi = (float) $this->input('allocated_amount');

                if ($alokasi > (float) $goal->target_amount) {
                    $validator->errors()->add(
                        'allocated_amount',
                        'Alokasi melebihi nominal target. Kurangi, atau naikkan targetnya.',
                    );
                }

                // Menandai dana tanpa menyebut rekeningnya berarti angka itu
                // tidak berasal dari mana pun — uang yang tidak ada di tempat
                // mana pun tetapi terhitung sebagai progres.
                if ($alokasi > 0 && $this->input('account_id') === null) {
                    $validator->errors()->add(
                        'account_id',
                        'Pilih rekening tempat dana ini berada.',
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
            'account_id.exists' => 'Dana target hanya bisa ditandai di rekening bank atau tunai.',
            'allocated_amount.required' => 'Nominal alokasi wajib diisi. Isi 0 bila belum ada.',
            'allocated_amount.min' => 'Alokasi tidak boleh negatif.',
            'priority.required' => 'Pilih prioritas target.',
            'priority.in' => 'Prioritas tidak dikenal.',
        ];
    }
}
