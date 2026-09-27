<?php

namespace App\Services;

use App\Enums\TransactionType;
use App\Models\Debt;
use App\Models\User;
use Illuminate\Support\Collection;

/**
 * Saldo rekening, kekayaan bersih, dan arus kas (PRD FR-63..FR-69).
 *
 * Satu-satunya tempat angka-angka ini dihitung. Frontend menerimanya sudah
 * jadi dan tidak pernah menjumlahkan ulang (CLAUDE.md §6.9) — dua tempat
 * yang sama-sama menjumlahkan saldo adalah dua tempat yang bisa berbeda
 * hasilnya, dan pada aplikasi keuangan perbedaan itu menghancurkan
 * kepercayaan lebih cepat daripada bug apa pun.
 *
 * Seluruh metode di sini menghitung SATU KALI untuk SEMUA rekening, bukan
 * per rekening. Halaman yang menampilkan sepuluh rekening tidak boleh
 * memicu sepuluh kueri.
 */
class AccountBalanceService
{
    /**
     * Saldo tiap rekening milik pengguna, dipetakan `account_id => saldo`.
     *
     * Dihitung dari dua arah sekaligus: transaksi yang keluar dari sebuah
     * rekening, dan transfer yang masuk ke dalamnya. Melewatkan arah kedua
     * membuat saldo rekening tujuan selalu kurang sebesar seluruh transfer
     * yang pernah diterimanya — kekeliruan yang tidak terlihat sampai
     * pengguna memindahkan uang untuk pertama kalinya.
     *
     * @return array<int, float>
     */
    public function forUser(User $user): array
    {
        $rekening = $user->accounts()->get(['id', 'opening_balance']);

        /** @var array<int, float> $saldo */
        $saldo = $rekening
            ->mapWithKeys(fn ($r) => [$r->id => (float) $r->opening_balance])
            ->all();

        if ($saldo === []) {
            return [];
        }

        $transaksi = $user->transactions()->get([
            'account_id', 'to_account_id', 'type', 'amount',
        ]);

        foreach ($transaksi as $t) {
            $nominal = (float) $t->amount;

            if (array_key_exists($t->account_id, $saldo)) {
                $saldo[$t->account_id] += $t->type->menambahSaldo() ? $nominal : -$nominal;
            }

            if ($t->type === TransactionType::Transfer
                && $t->to_account_id !== null
                && array_key_exists($t->to_account_id, $saldo)) {
                $saldo[$t->to_account_id] += $nominal;
            }
        }

        return array_map(fn (float $n) => round($n, 2), $saldo);
    }

    /**
     * Dana tujuan yang ditandai di tiap rekening, dipetakan
     * `account_id => ['total' => …, 'goals' => [['id', 'name', 'amount'], …]]`.
     *
     * SATU-SATUNYA definisi "untuk tujuan". LedgerGuard memakainya untuk
     * menolak, halaman Rekening dan form Transaksi memakainya untuk
     * menampilkan "bebas dipakai". Kalau keduanya menghitung sendiri-sendiri,
     * tampilan bisa bilang bebas Rp 45 juta sementara pengeluaran Rp 40 juta
     * tetap ditolak — lebih buruk daripada tidak menampilkan apa-apa.
     *
     * Status tujuan TIDAK disaring: dana tujuan yang sudah tercapai atau
     * diarsipkan tetap menandai saldonya sampai alokasinya dikurangi sendiri.
     *
     * @return array<int, array{total: float, goals: array<int, array{id: int, name: string, amount: float}>}>
     */
    public function allocatedByAccount(User $user): array
    {
        return $user->goals()
            ->whereNotNull('account_id')
            ->where('allocated_amount', '>', 0)
            ->orderByDesc('allocated_amount')
            ->get(['id', 'account_id', 'name', 'allocated_amount'])
            ->groupBy('account_id')
            ->map(fn (Collection $tujuan) => [
                'total' => round((float) $tujuan->sum('allocated_amount'), 2),
                'goals' => $tujuan->map(fn ($g) => [
                    'id' => $g->id,
                    'name' => $g->name,
                    'amount' => (float) $g->allocated_amount,
                ])->values()->all(),
            ])
            ->all();
    }

    /**
     * Saldo, dana tujuan, dan sisa yang bebas dipakai per rekening, dipetakan
     * `account_id => […]`. Dipakai halaman Rekening dan setiap form yang
     * mengambil uang dari rekening (transaksi, pembayaran utang).
     *
     * `free` = saldo − dana tujuan: batas yang ditegakkan LedgerGuard untuk
     * pengeluaran dari rekening itu.
     *
     * @return array<int, array{balance: float, allocated: float, free: float, allocated_goals: array}>
     */
    public function availability(User $user): array
    {
        $saldo = $this->forUser($user);
        $ditandai = $this->allocatedByAccount($user);

        $hasil = [];

        foreach ($saldo as $id => $nilai) {
            $untukTujuan = $ditandai[$id]['total'] ?? 0.0;

            $hasil[$id] = [
                'balance' => $nilai,
                'allocated' => $untukTujuan,
                'free' => round($nilai - $untukTujuan, 2),
                'allocated_goals' => $ditandai[$id]['goals'] ?? [],
            ];
        }

        return $hasil;
    }

    /**
     * Daftar rekening untuk pilihan di form yang mengambil uang, beserta
     * ketersediaannya — supaya batasnya terlihat SEBELUM menyimpan, bukan
     * baru ketahuan saat ditolak.
     *
     * @return array<int, array{id: int, name: string, kind: string, balance: float, allocated: float, free: float, allocated_goals: array}>
     */
    public function accountOptions(User $user): array
    {
        $tersedia = $this->availability($user);

        return $user->accounts()
            ->orderBy('name')
            ->get(['id', 'name', 'kind'])
            ->map(fn ($r) => [
                'id' => $r->id,
                'name' => $r->name,
                'kind' => $r->kind->value,
                ...($tersedia[$r->id] ?? [
                    'balance' => 0.0, 'allocated' => 0.0, 'free' => 0.0, 'allocated_goals' => [],
                ]),
            ])
            ->all();
    }

    /** Jumlah seluruh saldo — nilai aset kotor, belum dikurangi utang. */
    public function totalAssets(User $user): float
    {
        return round(array_sum($this->forUser($user)), 2);
    }

    /**
     * Sisa pokok tiap utang, dipetakan `debt_id => sisa`.
     *
     * @return array<int, float>
     */
    public function debtRemaining(User $user): array
    {
        $utang = $user->debts()->get(['id', 'principal']);

        if ($utang->isEmpty()) {
            return [];
        }

        $dibayar = $user->transactions()
            ->where('type', TransactionType::Payment->value)
            ->whereNotNull('debt_id')
            ->selectRaw('debt_id, SUM(amount) AS total')
            ->groupBy('debt_id')
            ->pluck('total', 'debt_id');

        return $utang
            ->mapWithKeys(fn (Debt $d) => [
                $d->id => round((float) $d->principal - (float) ($dibayar[$d->id] ?? 0), 2),
            ])
            ->all();
    }

    /**
     * Kekayaan bersih = seluruh nilai aset − sisa pokok utang.
     *
     * Pembayaran pokok tidak mengubah angka ini: kas berkurang dan utang
     * berkurang sebesar yang sama. Itu memang benar, dan sekaligus yang
     * membuat angka ini lebih jujur daripada total saldo semata.
     */
    public function netWorth(User $user): float
    {
        return round(
            $this->totalAssets($user) - array_sum($this->debtRemaining($user)),
            2,
        );
    }

    /**
     * Arus kas satu bulan, format `$bulan` = "YYYY-MM".
     *
     * Transfer dan penyesuaian nilai TIDAK dihitung — keduanya tidak
     * memindahkan uang ke luar atau ke dalam kekayaan pengguna. Membeli
     * reksa dana bukan pengeluaran, dan harga emas yang naik bukan
     * pemasukan.
     *
     * @return array{income: float, expense: float, principal: float, net: float}
     */
    public function monthlyCashFlow(User $user, string $bulan): array
    {
        $per = $user->transactions()
            ->cashFlow()
            ->inMonth($bulan)
            ->selectRaw('type, SUM(amount) AS total')
            ->groupBy('type')
            ->pluck('total', 'type');

        $ambil = fn (TransactionType $t) => round((float) ($per[$t->value] ?? 0), 2);

        $masuk = $ambil(TransactionType::Income);
        $keluar = $ambil(TransactionType::Expense);
        $pokok = $ambil(TransactionType::Payment);

        return [
            'income' => $masuk,
            'expense' => $keluar,
            'principal' => $pokok,
            'net' => round($masuk - $keluar - $pokok, 2),
        ];
    }

    /**
     * Tanggal penilaian ulang terakhir tiap rekening, `account_id => tanggal`.
     *
     * NULL berarti nilainya belum pernah diperbarui sejak dicatat — dan itu
     * yang perlu terlihat. Nilai saham atau emas yang tidak pernah disentuh
     * berbulan-bulan tetap tampil sebagai angka pasti di layar, padahal ia
     * hanya tebakan yang sudah basi.
     *
     * @return array<int, string|null>
     */
    public function lastValuationDates(User $user): array
    {
        return $user->transactions()
            ->where('type', TransactionType::Adjustment->value)
            ->selectRaw('account_id, MAX(occurred_on) AS terakhir')
            ->groupBy('account_id')
            ->pluck('terakhir', 'account_id')
            ->map(fn ($tanggal) => $tanggal === null ? null : substr((string) $tanggal, 0, 10))
            ->all();
    }

    /**
     * Komposisi aset per jenis rekening, untuk donat di dashboard.
     *
     * @return Collection<int, array{kind: string, label: string, amount: float, percentage: float}>
     */
    public function assetComposition(User $user): Collection
    {
        $saldo = $this->forUser($user);
        $total = array_sum($saldo);

        return $user->accounts()
            ->get(['id', 'kind'])
            ->groupBy(fn ($r) => $r->kind->value)
            ->map(function ($grup, string $jenis) use ($saldo, $total) {
                $nominal = round(
                    $grup->sum(fn ($r) => $saldo[$r->id] ?? 0),
                    2,
                );

                return [
                    'kind' => $jenis,
                    'label' => $grup->first()->kind->label(),
                    'amount' => $nominal,
                    'percentage' => $total > 0 ? round($nominal / $total * 100, 1) : 0.0,
                ];
            })
            ->values();
    }
}
