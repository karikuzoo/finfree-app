<?php

namespace App\Services;

use App\Enums\RiskProfile;
use App\Models\FinancialGoal;
use Illuminate\Support\Carbon;

/**
 * Engine rekomendasi alokasi instrumen (PRD — "rekomendasi alokasi
 * instrumen investasi (saham, reksa dana, obligasi/SBN, deposito, emas)").
 *
 * PENTING: tabel di bawah ini adalah aturan ILUSTRATIF yang disederhanakan
 * untuk melengkapi tampilan Dashboard — BUKAN hasil kajian
 * produk/kepatuhan yang sudah direview. Sebelum dipakai pengguna
 * sungguhan, tabel ini wajib divalidasi oleh yang berwenang menyusun
 * kebijakan alokasi FinGoal (lihat catatan "simulasi edukatif, bukan
 * nasihat investasi" di PRD §keputusan desain).
 *
 * Aturan: jangka waktu ke target_date dikelompokkan jadi 3 horizon
 * (pendek < 24 bulan, menengah 24–59 bulan, panjang >= 60 bulan — dana
 * darurat tanpa target_date selalu dianggap horizon pendek karena harus
 * likuid), disilangkan dengan profil risiko (override goal, atau profil
 * akun kalau tidak di-override).
 */
class InvestmentAllocationService
{
    /**
     * @return array<int, array{instrument: string, percentage: int}>
     */
    public function forGoal(FinancialGoal $goal, RiskProfile $accountRiskProfile): array
    {
        $riskProfile = $goal->risk_profile_override ?? $accountRiskProfile;
        $horizon = $this->horizonBucket($goal);

        $split = $this->table()[$horizon][$riskProfile->value];

        return [
            ['instrument' => 'Saham', 'percentage' => $split['saham']],
            ['instrument' => 'Obligasi/SBN', 'percentage' => $split['obligasi']],
            ['instrument' => 'Deposito', 'percentage' => $split['deposito']],
            ['instrument' => 'Emas', 'percentage' => $split['emas']],
        ];
    }

    private function horizonBucket(FinancialGoal $goal): string
    {
        if (! $goal->target_date) {
            return 'pendek';
        }

        $months = Carbon::now()->diffInMonths($goal->target_date, false);

        return match (true) {
            $months >= 60 => 'panjang',
            $months >= 24 => 'menengah',
            default => 'pendek',
        };
    }

    /**
     * @return array<string, array<string, array<string, int>>>
     */
    private function table(): array
    {
        return [
            'pendek' => [
                'conservative' => ['deposito' => 70, 'obligasi' => 20, 'emas' => 10, 'saham' => 0],
                'moderate' => ['deposito' => 55, 'obligasi' => 25, 'emas' => 15, 'saham' => 5],
                'aggressive' => ['deposito' => 40, 'obligasi' => 25, 'emas' => 20, 'saham' => 15],
            ],
            'menengah' => [
                'conservative' => ['deposito' => 40, 'obligasi' => 35, 'emas' => 15, 'saham' => 10],
                'moderate' => ['deposito' => 25, 'obligasi' => 30, 'emas' => 15, 'saham' => 30],
                'aggressive' => ['deposito' => 15, 'obligasi' => 20, 'emas' => 15, 'saham' => 50],
            ],
            'panjang' => [
                'conservative' => ['deposito' => 25, 'obligasi' => 35, 'emas' => 15, 'saham' => 25],
                'moderate' => ['deposito' => 10, 'obligasi' => 25, 'emas' => 15, 'saham' => 50],
                'aggressive' => ['deposito' => 5, 'obligasi' => 15, 'emas' => 10, 'saham' => 70],
            ],
        ];
    }
}
