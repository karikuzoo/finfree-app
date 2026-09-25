<?php

namespace App\Services;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\CalendarNote;
use App\Models\Debt;
use App\Models\FinancialGoal;
use App\Models\Reminder;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Cadangan dan pemulihan seluruh data keuangan pengguna (PRD FR-83, FR-84).
 *
 * BEDA dari ekspor Excel (GoalExportController). Excel dibuat untuk DIBACA
 * manusia: rapi, berkolom, tetapi tidak bisa dimasukkan kembali. Berkas ini
 * dibuat untuk DIKEMBALIKAN: lengkap, apa adanya, dan pasangan sempurna dari
 * data yang sedang tersimpan. Keduanya sengaja ada, karena satu format tidak
 * bisa melayani kedua tujuan itu tanpa mengorbankan salah satunya.
 *
 * ID asli TIDAK ikut. Sebagai gantinya tiap rekening dan utang membawa `ref` —
 * nomor urut yang hanya berlaku di dalam berkas itu sendiri, dipakai transaksi
 * dan tujuan untuk menunjuk induknya. Memasukkan ID sungguhan akan bertabrakan
 * begitu berkas dipulihkan ke akun atau basis data yang berbeda.
 *
 * Yang TIDAK ikut dicadangkan, dan disebutkan supaya tidak dikira hilang
 * karena bug:
 *
 * - Data profil (nama, email, telepon). Berkas cadangan gampang tersimpan
 *   bertahun-tahun di folder Downloads atau ikut tersalin ke awan; isinya
 *   dibatasi pada yang memang dibutuhkan untuk memulihkan catatan keuangan.
 * - Snapshot perhitungan (`goal_calculations`). Ia jejak audit dari kalkulasi
 *   di masa lalu, bukan data yang dimasukkan pengguna — memulihkannya akan
 *   memalsukan riwayat yang tidak pernah terjadi di akun tujuan.
 *
 * Catatan kalender dan pengingat IKUT dicadangkan, meski keduanya menempel
 * pada tanggal dan bukan pada uang. Alasannya bukan soal kategori melainkan
 * soal akibat: keduanya diketik sendiri oleh pengguna dan tidak bisa dibuat
 * ulang dari data lain. Cadangan yang terasa lengkap padahal diam-diam
 * meninggalkan sesuatu adalah jenis kegagalan yang baru ketahuan saat
 * pemulihan — ketika sudah terlambat.
 */
class BackupService
{
    /** Dinaikkan bila bentuk berkasnya berubah sehingga versi lama tak terbaca. */
    public const VERSION = 1;

    /** @return array<string, mixed> */
    public function export(User $user): array
    {
        $rekening = $user->accounts()->orderBy('id')->get();
        $utang = $user->debts()->orderBy('id')->get();

        // Peta id asli → nomor urut di dalam berkas.
        $refRekening = $rekening->pluck('id')->flip()->map(fn ($i) => $i + 1);
        $refUtang = $utang->pluck('id')->flip()->map(fn ($i) => $i + 1);

        $anggaran = $user->budget;

        return [
            'arus_backup_version' => self::VERSION,
            'exported_at' => now(config('app.timezone'))->toIso8601String(),

            'accounts' => $rekening->map(fn (Account $r) => [
                'ref' => $refRekening[$r->id],
                'name' => $r->name,
                'kind' => $r->kind->value,
                'institution' => $r->institution,
                'opening_balance' => (float) $r->opening_balance,
            ])->values()->all(),

            'debts' => $utang->map(fn (Debt $d) => [
                'ref' => $refUtang[$d->id],
                'name' => $d->name,
                'principal' => (float) $d->principal,
                'monthly_principal' => (float) $d->monthly_principal,
                'due_on' => $d->due_on?->toDateString(),
            ])->values()->all(),

            'transactions' => $user->transactions()->orderBy('id')->get()
                ->map(fn (Transaction $t) => [
                    'account_ref' => $refRekening[$t->account_id] ?? null,
                    'to_account_ref' => $t->to_account_id === null
                        ? null
                        : ($refRekening[$t->to_account_id] ?? null),
                    'debt_ref' => $t->debt_id === null
                        ? null
                        : ($refUtang[$t->debt_id] ?? null),
                    'type' => $t->type->value,
                    'name' => $t->name,
                    'amount' => (float) $t->amount,
                    'category' => $t->category,
                    'occurred_on' => $t->occurred_on->toDateString(),
                ])->values()->all(),

            'goals' => $user->goals()->orderBy('id')->get()
                ->map(fn (FinancialGoal $g) => [
                    'account_ref' => $g->account_id === null
                        ? null
                        : ($refRekening[$g->account_id] ?? null),
                    'type' => $g->type->value,
                    'name' => $g->name,
                    'target_amount' => (float) $g->target_amount,
                    'initial_amount' => (float) $g->initial_amount,
                    'allocated_amount' => (float) $g->allocated_amount,
                    'target_date' => $g->target_date?->toDateString(),
                    'estimated_return_rate' => (float) $g->estimated_return_rate,
                    'estimated_inflation_rate' => (float) $g->estimated_inflation_rate,
                    'daily_savings_target' => (float) $g->daily_savings_target,
                    'asset_allocation' => $g->asset_allocation,
                    'status' => $g->status->value,
                    'priority' => $g->priority->value,
                ])->values()->all(),

            // Tidak punya `ref`: tidak ada apa pun yang menunjuk keduanya.
            'calendar_notes' => $user->calendarNotes()->orderBy('note_date')->get()
                ->map(fn (CalendarNote $c) => [
                    'note_date' => $c->note_date->toDateString(),
                    'body' => $c->body,
                ])->values()->all(),

            'reminders' => $user->reminders()->orderBy('remind_at')->get()
                ->map(fn (Reminder $r) => [
                    // Disimpan lengkap dengan jamnya — pengingat "bayar listrik
                    // jam 9" kehilangan gunanya bila hanya tanggalnya bertahan.
                    'remind_at' => $r->remind_at->toIso8601String(),
                    'completed_at' => $r->completed_at?->toIso8601String(),
                    'title' => $r->title,
                ])->values()->all(),

            'budget' => $anggaran === null ? null : [
                'planned_income' => (float) $anggaran->planned_income,
                'planned_expenses' => (float) $anggaran->planned_expenses,
                'monthly_reserve' => (float) $anggaran->monthly_reserve,
            ],
        ];
    }

    /**
     * MENGGANTI seluruh data keuangan pengguna dengan isi berkas.
     *
     * Bukan menggabungkan. Menggabungkan akan menggandakan tiap transaksi
     * setiap kali cadangan yang sama dipulihkan dua kali, dan saldonya
     * membengkak tanpa ada yang menyadari asal-usulnya.
     *
     * Seluruhnya dalam SATU transaksi database: berkas yang ditolak di tengah
     * jalan tidak boleh meninggalkan separuh data lama bercampur separuh data
     * baru — keadaan yang jauh lebih buruk daripada gagal sama sekali.
     */
    public function import(User $user, array $isi): void
    {
        $data = $this->validate($isi);

        DB::transaction(function () use ($user, $data) {
            // Urutannya mengikuti arah foreign key: yang menunjuk dihapus
            // lebih dulu, yang ditunjuk belakangan.
            $user->goals()->delete();
            $user->transactions()->delete();
            $user->accounts()->delete();
            $user->debts()->delete();
            $user->budget()->delete();
            $user->calendarNotes()->delete();
            $user->reminders()->delete();

            $petaRekening = [];
            foreach ($data['accounts'] as $baris) {
                $petaRekening[$baris['ref']] = $user->accounts()->create([
                    'name' => $baris['name'],
                    'kind' => $baris['kind'],
                    'institution' => $baris['institution'] ?? null,
                    'opening_balance' => $baris['opening_balance'],
                ])->id;
            }

            $petaUtang = [];
            foreach ($data['debts'] ?? [] as $baris) {
                $petaUtang[$baris['ref']] = $user->debts()->create([
                    'name' => $baris['name'],
                    'principal' => $baris['principal'],
                    'monthly_principal' => $baris['monthly_principal'],
                    'due_on' => $baris['due_on'] ?? null,
                ])->id;
            }

            foreach ($data['transactions'] ?? [] as $baris) {
                $user->transactions()->create([
                    'account_id' => $this->petakan($petaRekening, $baris['account_ref'], 'Rekening'),
                    'to_account_id' => isset($baris['to_account_ref'])
                        ? $this->petakan($petaRekening, $baris['to_account_ref'], 'Rekening tujuan')
                        : null,
                    'debt_id' => isset($baris['debt_ref'])
                        ? $this->petakan($petaUtang, $baris['debt_ref'], 'Utang')
                        : null,
                    'type' => $baris['type'],
                    'name' => $baris['name'],
                    'amount' => $baris['amount'],
                    'category' => $baris['category'] ?? null,
                    'occurred_on' => $baris['occurred_on'],
                ]);
            }

            foreach ($data['goals'] ?? [] as $baris) {
                $user->goals()->create([
                    'account_id' => isset($baris['account_ref'])
                        ? $this->petakan($petaRekening, $baris['account_ref'], 'Rekening tujuan')
                        : null,
                    'type' => $baris['type'],
                    'name' => $baris['name'],
                    'target_amount' => $baris['target_amount'],
                    'initial_amount' => $baris['initial_amount'] ?? 0,
                    'allocated_amount' => $baris['allocated_amount'] ?? 0,
                    'target_date' => $baris['target_date'] ?? null,
                    'estimated_return_rate' => $baris['estimated_return_rate'],
                    'estimated_inflation_rate' => $baris['estimated_inflation_rate'] ?? 0,
                    'daily_savings_target' => $baris['daily_savings_target'] ?? 0,
                    'asset_allocation' => $baris['asset_allocation'] ?? null,
                    'status' => $baris['status'],
                    'priority' => $baris['priority'] ?? GoalPriority::Medium->value,
                ]);
            }

            foreach ($data['calendar_notes'] ?? [] as $baris) {
                $user->calendarNotes()->create([
                    'note_date' => $baris['note_date'],
                    'body' => $baris['body'],
                ]);
            }

            foreach ($data['reminders'] ?? [] as $baris) {
                $user->reminders()->create([
                    'remind_at' => $baris['remind_at'],
                    'completed_at' => $baris['completed_at'] ?? null,
                    'title' => $baris['title'],
                ]);
            }

            if (($data['budget'] ?? null) !== null) {
                $user->budget()->create($data['budget']);
            }

            // Berkas yang sah bentuknya masih bisa melanggar aturan keuangan:
            // saldo minus, alokasi melebihi saldo, pembayaran melebihi utang.
            // Diperiksa di sini, di dalam transaksi yang sama, sehingga
            // berkas seperti itu ditolak utuh — bukan tersimpan lalu
            // menghasilkan angka mustahil di layar.
            app(LedgerGuard::class)->assertConsistent($user, 'berkas');
        });
    }

    /**
     * @param  array<int, int>  $peta
     */
    private function petakan(array $peta, int|string $ref, string $label): int
    {
        if (! isset($peta[$ref])) {
            throw ValidationException::withMessages([
                'berkas' => "{$label} dengan nomor {$ref} tidak ada di dalam berkas.",
            ]);
        }

        return $peta[$ref];
    }

    /**
     * Isi berkas divalidasi SELENGKAP masukan formulir.
     *
     * Berkas ini datang dari luar aplikasi — bisa disunting tangan, berasal
     * dari versi lain, atau rusak separuh. Memperlakukannya sebagai data
     * tepercaya hanya karena ia "berasal dari kami" adalah cara paling mudah
     * memasukkan nilai mustahil ke basis data.
     *
     * @return array<string, mixed>
     */
    private function validate(array $isi): array
    {
        $uang = ['required', 'numeric', 'min:0', 'max:999999999999999.99'];

        $validator = Validator::make($isi, [
            'arus_backup_version' => ['required', 'integer', 'in:'.self::VERSION],

            'accounts' => ['present', 'array', 'max:200'],
            'accounts.*.ref' => ['required', 'integer', 'min:1'],
            'accounts.*.name' => ['required', 'string', 'max:100'],
            'accounts.*.kind' => ['required', Rule::in(AccountKind::values())],
            'accounts.*.institution' => ['nullable', 'string', 'max:100'],
            'accounts.*.opening_balance' => $uang,

            'debts' => ['present', 'array', 'max:200'],
            'debts.*.ref' => ['required', 'integer', 'min:1'],
            'debts.*.name' => ['required', 'string', 'max:100'],
            'debts.*.principal' => ['required', 'numeric', 'min:0.01', 'max:999999999999999.99'],
            'debts.*.monthly_principal' => $uang,
            'debts.*.due_on' => ['nullable', 'date'],

            'transactions' => ['present', 'array', 'max:20000'],
            'transactions.*.account_ref' => ['required', 'integer', 'min:1'],
            'transactions.*.to_account_ref' => ['nullable', 'integer', 'min:1'],
            'transactions.*.debt_ref' => ['nullable', 'integer', 'min:1'],
            'transactions.*.type' => ['required', Rule::in(TransactionType::values())],
            'transactions.*.name' => ['required', 'string', 'max:100'],
            'transactions.*.amount' => ['required', 'numeric', 'max:999999999999999.99'],
            'transactions.*.category' => ['nullable', 'string', 'max:100'],
            'transactions.*.occurred_on' => ['required', 'date', 'before_or_equal:today'],

            'goals' => ['present', 'array', 'max:200'],
            'goals.*.account_ref' => ['nullable', 'integer', 'min:1'],
            'goals.*.type' => ['required', Rule::in(GoalType::values())],
            'goals.*.name' => ['required', 'string', 'max:100'],
            'goals.*.target_amount' => ['required', 'numeric', 'min:0.01', 'max:999999999999999.99'],
            'goals.*.initial_amount' => ['nullable', 'numeric', 'min:0'],
            'goals.*.allocated_amount' => ['nullable', 'numeric', 'min:0'],
            'goals.*.target_date' => ['nullable', 'date'],
            'goals.*.estimated_return_rate' => ['required', 'numeric', 'min:0', 'max:100'],
            'goals.*.estimated_inflation_rate' => ['nullable', 'numeric', 'min:0', 'max:100'],
            'goals.*.daily_savings_target' => ['nullable', 'numeric', 'min:0'],
            'goals.*.asset_allocation' => ['nullable', 'array'],
            'goals.*.status' => ['required', Rule::in(GoalStatus::values())],
            'goals.*.priority' => ['nullable', Rule::in(GoalPriority::values())],

            // `present` tanpa `required`: kunci wajib ADA supaya berkas dari
            // versi yang lebih lama tidak diam-diam lolos dengan catatan
            // hilang, tetapi isinya boleh kosong.
            'calendar_notes' => ['present', 'array', 'max:5000'],
            'calendar_notes.*.note_date' => ['required', 'date'],
            'calendar_notes.*.body' => ['required', 'string', 'max:500'],

            'reminders' => ['present', 'array', 'max:5000'],
            'reminders.*.remind_at' => ['required', 'date'],
            'reminders.*.completed_at' => ['nullable', 'date'],
            'reminders.*.title' => ['required', 'string', 'max:100'],

            'budget' => ['nullable', 'array'],
            'budget.planned_income' => ['required_with:budget', 'numeric', 'min:0'],
            'budget.planned_expenses' => ['required_with:budget', 'numeric', 'min:0'],
            'budget.monthly_reserve' => ['required_with:budget', 'numeric', 'min:0'],
        ], [
            'arus_backup_version.required' => 'Berkas ini bukan cadangan Arus.',
            'arus_backup_version.in' => 'Versi cadangan tidak dikenali oleh versi aplikasi ini.',
        ]);

        if ($validator->fails()) {
            throw ValidationException::withMessages([
                'berkas' => 'Isi berkas tidak sesuai: '.$validator->errors()->first(),
            ]);
        }

        return $validator->validated();
    }
}
