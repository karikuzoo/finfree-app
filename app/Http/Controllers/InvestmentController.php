<?php

namespace App\Http\Controllers;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Services\AccountBalanceService;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Investasi (PRD FR-71, FR-72).
 *
 * BUKAN tabel tersendiri. Investasi adalah rekening yang jenisnya bukan bank
 * atau tunai — saham, reksa dana, emas. Memisahkannya jadi entitas sendiri
 * berarti nilainya tidak ikut terhitung dalam total aset dan komposisi
 * portofolio, padahal justru di situ gunanya.
 *
 * Yang membedakan halaman ini dari Rekening & aset hanyalah penyaringan dan
 * penekanannya: di sini "Perbarui nilai" yang menonjol, karena aset inilah
 * yang nilainya bergerak tanpa ada transaksi apa pun dari pengguna.
 */
class InvestmentController extends Controller
{
    public function __construct(private AccountBalanceService $saldo) {}

    public function index(Request $request): Response
    {
        $user = $request->user();

        $saldo = $this->saldo->forUser($user);
        $penilaian = $this->saldo->lastValuationDates($user);

        $jenisInvestasi = array_values(array_diff(
            AccountKind::values(),
            AccountKind::nilaiLikuid(),
        ));

        $investasi = $user->accounts()
            ->whereIn('kind', $jenisInvestasi)
            ->orderBy('created_at')
            ->get()
            ->map(fn (Account $r) => [
                'id' => $r->id,
                'name' => $r->name,
                'kind' => $r->kind->value,
                'kind_label' => $r->kind->label(),
                'institution' => $r->institution,
                'opening_balance' => (float) $r->opening_balance,
                'value' => $saldo[$r->id] ?? 0.0,
                // NULL berarti belum pernah dinilai ulang sejak dicatat.
                // Frontend menampilkannya sebagai "Saldo awal", bukan tanggal.
                'last_valued_on' => $penilaian[$r->id] ?? null,
            ]);

        return Inertia::render('Investment/Index', [
            'investments' => $investasi,
            'totalValue' => round($investasi->sum('value'), 2),
            'kinds' => collect(AccountKind::cases())
                ->filter(fn (AccountKind $k) => ! $k->likuid())
                ->map(fn (AccountKind $k) => ['value' => $k->value, 'label' => $k->label()])
                ->values(),
        ]);
    }
}
