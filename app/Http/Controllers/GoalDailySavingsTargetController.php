<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateDailySavingsTargetRequest;
use App\Models\FinancialGoal;
use Illuminate\Http\RedirectResponse;

/**
 * Satu aksi terpisah dari FinancialGoal CRUD utama (yang belum ada di
 * Rilis saat ini) — mengikuti pola ProfilePreferenceController: satu
 * kartu, satu form, satu route, supaya tidak menimpa kolom goal lain.
 */
class GoalDailySavingsTargetController extends Controller
{
    public function update(UpdateDailySavingsTargetRequest $request, FinancialGoal $financialGoal): RedirectResponse
    {
        $financialGoal->update($request->validated());

        return back();
    }
}
