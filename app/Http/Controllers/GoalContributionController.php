<?php

namespace App\Http\Controllers;

use App\Http\Requests\StoreGoalContributionRequest;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Mencatat setoran (PRD FR-32..FR-35) langsung dari kartu utama Dashboard
 * — tidak perlu halaman "Tujuan" terpisah yang belum dibangun. Setelah
 * disimpan, DashboardSummaryService otomatis menghitung ulang
 * current_amount, progres, dan kalender aktivitas di request berikutnya
 * (tidak ada nilai yang di-cache di sisi FinancialGoal).
 */
class GoalContributionController extends Controller
{
    public function store(StoreGoalContributionRequest $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $contribution = $financialGoal->contributions()->create($request->validated());

        $request->user()->activities()->create([
            'type' => 'contribution_recorded',
            'goal_name' => $financialGoal->name,
            'amount' => $contribution->amount,
        ]);

        return back();
    }

    /**
     * Mengubah nominal atau catatan sebuah setoran (PRD FR-33).
     *
     * TANGGALNYA sengaja tidak bisa diubah di sini. Setoran disunting dari
     * dialog tanggal di kalender; memindahkannya ke tanggal lain akan membuat
     * barisnya lenyap dari dialog yang sedang dibuka — pengguna menekan simpan
     * lalu melihat entrinya hilang tanpa penjelasan. Salah tanggal diperbaiki
     * dengan menghapus lalu mencatat ulang di tanggal yang benar.
     */
    public function update(Request $request, GoalContribution $goalContribution): RedirectResponse
    {
        $this->pastikanMilikPengguna($request, $goalContribution);

        $data = $request->validate([
            'amount' => ['required', 'numeric', 'min:1', 'max:999999999999999.99'],
            'note' => ['nullable', 'string', 'max:500'],
        ], [
            'amount.required' => 'Nominal setoran wajib diisi.',
            'amount.min' => 'Nominal setoran harus lebih besar dari nol.',
            'note.max' => 'Catatan maksimal 500 karakter.',
        ]);

        $goalContribution->update($data);

        return back();
    }

    public function destroy(Request $request, GoalContribution $goalContribution): RedirectResponse
    {
        $this->pastikanMilikPengguna($request, $goalContribution);

        $goalContribution->delete();

        return back();
    }

    /**
     * Kepemilikan setoran diperiksa lewat tujuannya, karena goal_contributions
     * tidak menyimpan user_id sendiri. Ini titik kebocoran data antar pengguna
     * yang paling mudah terlewat (CONTRIBUTING §7).
     */
    private function pastikanMilikPengguna(Request $request, GoalContribution $contribution): void
    {
        abort_unless(
            $contribution->financialGoal?->user_id === $request->user()->id,
            403,
        );
    }
}
