<?php

namespace App\Http\Controllers;

use App\Models\FinancialGoal;
use Illuminate\Http\Request;

class GoalAssetAllocationController extends Controller
{
    public function update(Request $request, FinancialGoal $financialGoal)
    {
        abort_unless($financialGoal->user_id === $request->user()->id, 403);

        $validated = $request->validate([
            'asset_allocation' => ['required', 'array'],
            'asset_allocation.saham' => ['nullable', 'numeric', 'min:0'],
            'asset_allocation.obligasi' => ['nullable', 'numeric', 'min:0'],
            'asset_allocation.deposito' => ['nullable', 'numeric', 'min:0'],
            'asset_allocation.emas' => ['nullable', 'numeric', 'min:0'],
            'asset_allocation.tabungan' => ['nullable', 'numeric', 'min:0'],
            'asset_allocation.custom' => ['nullable', 'array'],
            'asset_allocation.custom.*.name' => ['required', 'string', 'max:50'],
            'asset_allocation.custom.*.amount' => ['required', 'numeric', 'min:0'],
        ]);

        $totalAmount = 0;
        foreach ($validated['asset_allocation'] as $key => $value) {
            if ($key === 'custom' && is_array($value)) {
                foreach ($value as $customItem) {
                    $totalAmount += $customItem['amount'];
                }
            } elseif (is_numeric($value)) {
                $totalAmount += $value;
            }
        }
        
        $blendedReturnRate = $financialGoal->estimated_return_rate; // default fallback

        if ($totalAmount > 0) {
            $rates = [
                'saham' => 10.0,
                'obligasi' => 6.0,
                'deposito' => 4.0,
                'emas' => 5.0,
                'tabungan' => 2.0,
            ];
            
            $weightedReturn = 0;
            foreach ($validated['asset_allocation'] as $instrument => $value) {
                if ($instrument === 'custom' && is_array($value)) {
                    foreach ($value as $customItem) {
                        if ($customItem['amount'] > 0) {
                            $weight = $customItem['amount'] / $totalAmount;
                            $weightedReturn += $weight * 5.0; // default 5% for custom
                        }
                    }
                } elseif (is_numeric($value) && $value > 0 && isset($rates[$instrument])) {
                    $weight = $value / $totalAmount;
                    $weightedReturn += $weight * $rates[$instrument];
                }
            }
            
            $blendedReturnRate = round($weightedReturn, 2);
        }

        $financialGoal->update([
            'asset_allocation' => $validated['asset_allocation'],
            'estimated_return_rate' => $blendedReturnRate,
        ]);

        return back()->with('success', 'Alokasi aset berhasil diperbarui dan imbal hasil telah disesuaikan.');
    }
}
