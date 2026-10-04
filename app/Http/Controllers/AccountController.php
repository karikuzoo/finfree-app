<?php

namespace App\Http\Controllers;

use App\Enums\AccountKind;
use App\Enums\TransactionType;
use App\Http\Requests\StoreAccountRequest;
use App\Models\Account;
use App\Models\Transaction;
use App\Services\AccountBalanceService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

/**
 * Rekening & aset (PRD FR-63).
 *
 * Saldo tidak pernah dikirim per rekening lewat kueri terpisah —
 * AccountBalanceService menghitung seluruhnya sekali, lalu hasilnya
 * ditempelkan di sini. Halaman ini menampilkan semua rekening pengguna
 * sekaligus, jadi ia titik N+1 yang paling jelas kalau dikerjakan per baris.
 */
class AccountController extends Controller
{
    /** Baris mutasi per halaman detail rekening. */
    private const PER_HALAMAN = 20;

    /**
     * Pengaruh satu transaksi pada saldo rekening yang dilihat (lihat
     * mutasi()). Binding: id rekening, lalu dua jenis yang menambah saldo.
     */
    private const EFEK_SQL = 'CASE WHEN account_id = ? THEN (CASE WHEN type IN (?, ?) THEN amount ELSE -amount END) ELSE amount END';

    public function __construct(private AccountBalanceService $saldo) {}

    public function index(Request $request): Response
    {
        $user = $request->user();
        $tersedia = $this->saldo->availability($user);

        $rekening = $user->accounts()
            ->withExists('transactions')
            ->orderBy('created_at')
            ->get()
            ->map(fn (Account $r) => $this->kartu($r, $tersedia));

        return Inertia::render('Account/Index', [
            'accounts' => $rekening,
            'totalAssets' => $this->saldo->totalAssets($user),
            'composition' => $this->saldo->assetComposition($user),
            'kinds' => $this->jenis(),
        ]);
    }

    /**
     * Detail satu rekening: ringkasan yang sama dengan kartunya, ditambah
     * MUTASI — riwayat transaksi rekening ini beserta saldo sesudah tiap
     * baris, seperti mutasi rekening bank.
     *
     * Mutasi memuat transaksi YANG KELUAR dari rekening ini maupun transfer
     * yang MASUK ke dalamnya; melewatkan sisi kedua membuat saldo berjalannya
     * tidak pernah cocok dengan saldo di kartu (lihat AccountBalanceService).
     */
    public function show(Request $request, Account $account): Response
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $user = $request->user();
        $tersedia = $this->saldo->availability($user);
        $account->loadExists('transactions');

        return Inertia::render('Account/Show', [
            'account' => [
                ...$this->kartu($account, $tersedia),
                'last_valuation' => $this->saldo->lastValuationDates($user)[$account->id] ?? null,
            ],
            'mutations' => $this->mutasi($account),
            'kinds' => $this->jenis(),
        ]);
    }

    /**
     * Mutasi rekening, terbaru di atas, dengan saldo sesudah tiap baris.
     *
     * Saldo berjalan dihitung PostgreSQL lewat fungsi jendela (SUM … OVER)
     * atas SELURUH riwayat rekening, lalu baru dipotong per halaman —
     * menjumlahkannya di PHP berarti memuat seluruh riwayat setiap kali satu
     * halaman dibuka. Urutannya (tanggal, lalu id) sama untuk penjumlahan dan
     * tampilan, jadi saldo baris teratas halaman pertama selalu sama dengan
     * saldo di kartu.
     *
     * Arah tiap transaksi bagi rekening ini (`efek`) mengikuti
     * TransactionType::menambahSaldo(): pemasukan dan penyesuaian menambah,
     * sisanya mengurangi; transfer yang MASUK selalu menambah. Bila aturan di
     * enum itu berubah, CASE di bawah ikut berubah —
     * AccountShowTest::test_saldo_berjalan_cocok_dengan_saldo_kartu menjaganya.
     */
    private function mutasi(Account $account)
    {
        $menambah = collect(TransactionType::cases())
            ->filter(fn (TransactionType $t) => $t->menambahSaldo())
            ->map(fn (TransactionType $t) => $t->value)
            ->values()
            ->all();

        // SQL-nya teks tetap (EFEK_SQL), nilainya lewat binding — aturan
        // RequestSecurityTest untuk kueri mentah. Jumlah tanda tanya di sana
        // mengikuti jumlah jenis yang menambah saldo hari ini; bila enum
        // berubah, berhenti di sini alih-alih menghitung saldo yang salah.
        if (count($menambah) !== 2) {
            throw new \LogicException('EFEK_SQL perlu disesuaikan dengan TransactionType::menambahSaldo().');
        }

        $ikatan = [$account->id, ...$menambah];

        $dasar = Transaction::query()
            ->where('user_id', $account->user_id)
            ->where(fn ($q) => $q
                ->where('account_id', $account->id)
                ->orWhere(fn ($q) => $q
                    ->where('type', TransactionType::Transfer->value)
                    ->where('to_account_id', $account->id)))
            ->select('transactions.*')
            ->selectRaw(self::EFEK_SQL.' AS efek',
                $ikatan)
            ->selectRaw('SUM('.self::EFEK_SQL.') OVER (ORDER BY occurred_on, id) AS kumulatif',
                $ikatan);

        $awal = (float) $account->opening_balance;

        return Transaction::query()
            ->fromSub($dasar, 'transactions')
            ->with(['account:id,name', 'toAccount:id,name', 'debt:id,name'])
            ->orderByDesc('occurred_on')
            ->orderByDesc('id')
            ->paginate(self::PER_HALAMAN)
            ->withQueryString()
            ->through(function (Transaction $t) use ($account, $awal) {
                $masuk = $t->account_id !== $account->id;

                return [
                    'id' => $t->id,
                    'occurred_on' => $t->occurred_on->toDateString(),
                    'name' => $t->name,
                    'type' => $t->type->value,
                    'type_label' => $masuk ? 'Transfer masuk' : $t->type->label(),
                    'category' => $t->category,
                    // Rekening di seberang transfer — "dari" atau "ke".
                    'counterpart' => $t->type === TransactionType::Transfer
                        ? ($masuk ? $t->account?->name : $t->toAccount?->name)
                        : null,
                    'debt' => $t->debt?->name,
                    'amount' => round((float) $t->efek, 2),
                    'balance_after' => round($awal + (float) $t->kumulatif, 2),
                ];
            });
    }

    /**
     * Bentuk satu rekening untuk kartu di daftar dan kepala halaman detail —
     * satu tempat, supaya keduanya tidak menampilkan angka yang berbeda.
     */
    private function kartu(Account $r, array $tersedia): array
    {
        return [
            'id' => $r->id,
            'name' => $r->name,
            'kind' => $r->kind->value,
            'kind_label' => $r->kind->label(),
            'institution' => $r->institution,
            'opening_balance' => (float) $r->opening_balance,
            // FR-51: keterangan saja, bukan dasar hitung. NULL bila tidak diisi.
            'units' => $r->units === null ? null : (float) $r->units,
            'unit' => $r->kind->satuan(),
            'balance' => $tersedia[$r->id]['balance'] ?? 0.0,
            // Saldo penuh tetap angka utamanya — uangnya memang masih di
            // rekening ini. Dua angka di bawah menjelaskan kenapa
            // pengeluaran bisa ditolak padahal saldonya tampak cukup.
            'allocated' => $tersedia[$r->id]['allocated'] ?? 0.0,
            'allocated_goals' => $tersedia[$r->id]['allocated_goals'] ?? [],
            'free' => $tersedia[$r->id]['free'] ?? 0.0,
            // Nilainya bergerak sendiri mengikuti pasar, jadi kartunya
            // menawarkan "Perbarui nilai" alih-alih "Pindahkan dana".
            'needs_valuation' => $r->kind->perluPenilaian(),
            // Aturan yang sama dengan StoreAccountRequest: jenis dikunci
            // begitu ada transaksi. Dikirim supaya pilihannya terkunci di
            // form sejak awal, bukan baru ditolak setelah disimpan.
            'kind_locked' => (bool) $r->transactions_exists,
        ];
    }

    private function jenis()
    {
        return collect(AccountKind::cases())->map(fn (AccountKind $k) => [
            'value' => $k->value,
            'label' => $k->label(),
            'liquid' => $k->likuid(),
            'unit' => $k->satuan(),
        ]);
    }

    public function store(StoreAccountRequest $request): RedirectResponse
    {
        $request->user()->accounts()->create($request->dataRekening());

        return back();
    }

    public function update(StoreAccountRequest $request, Account $account): RedirectResponse
    {
        $account->update($request->dataRekening());

        return back();
    }

    /**
     * Rekening yang masih dipakai TIDAK boleh dihapus.
     *
     * Ditolak di sini dengan pesan yang bisa dibaca, bukan dibiarkan jatuh ke
     * pelanggaran foreign key yang muncul sebagai halaman 500. Menghapusnya
     * secara berantai juga bukan pilihan: riwayat transaksi adalah catatan
     * keuangan pengguna, dan melenyapkannya diam-diam karena ia menghapus
     * satu rekening adalah kehilangan data yang tidak bisa dibatalkan.
     */
    public function destroy(Request $request, Account $account): RedirectResponse
    {
        abort_unless($account->user_id === $request->user()->id, 403);

        $dipakai = $account->transactions()->exists()
            || $account->incomingTransfers()->exists();

        if ($dipakai) {
            throw ValidationException::withMessages([
                'account' => "Rekening {$account->name} masih punya riwayat transaksi. "
                    .'Hapus atau pindahkan transaksinya terlebih dahulu.',
            ]);
        }

        $account->delete();

        return back();
    }
}
