<?php

namespace Database\Seeders;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\RiskProfile;
use App\Enums\TransactionType;
use App\Models\Account;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Hash;

/**
 * Akun demo berisi data yang cukup untuk melihat aplikasi bekerja.
 *
 * Isi database tidak pernah ikut git (hanya skema, lewat migrasi), sehingga
 * setiap orang yang baru clone mendapat tabel kosong. Tanpa seeder ini, yang
 * dilihat pertama kali adalah empty state di mana-mana — dan fitur seperti
 * dashboard mustahil dinilai tanpa data.
 *
 * Datanya sengaja tidak acak. Tiga tujuan di bawah dipilih agar mewakili
 * keadaan yang berbeda-beda: satu hampir tercapai, satu di tengah jalan, satu
 * baru dimulai dengan jangka sangat panjang. Transaksi disebar ke belakang
 * selama 12 bulan supaya grafik pertumbuhan kekayaan punya kurva sungguhan,
 * bukan satu titik.
 *
 * Aman dijalankan berulang: akun dicari berdasarkan email, dan tujuan lamanya
 * dihapus lebih dulu agar tidak menumpuk.
 */
class DemoSeeder extends Seeder
{
    public const EMAIL = 'demo@fingoal.test';

    // Sengaja memenuhi aturan kata sandi aplikasi (huruf besar, huruf kecil,
    // angka, simbol, minimal 6) supaya sekaligus jadi contoh yang benar.
    public const PASSWORD = 'Demo123!';

    public function run(): void
    {
        $user = User::updateOrCreate(
            ['email' => self::EMAIL],
            [
                'name' => 'Budi Santoso',
                'password' => Hash::make(self::PASSWORD),
                // Sudah terverifikasi supaya bisa langsung masuk dashboard
                // tanpa harus mencari tautan verifikasi di berkas log.
                'email_verified_at' => now(),
                'risk_profile' => RiskProfile::Moderate,
                'prefers_syariah' => false,
                'currency_preference' => 'IDR',
                'birth_date' => '1995-08-17',
                'nationality' => 'Indonesia',
                'phone' => '0812 3456 7890',
                'occupation' => 'Karyawan swasta',
            ],
        );

        // Bersihkan data lama milik akun demo saja. Akun lain tidak disentuh.
        // Transaksi lebih dulu: rekening yang masih punya riwayat ditolak
        // dihapus oleh foreign key-nya.
        $user->goals()->delete();
        $user->transactions()->delete();
        $user->accounts()->delete();

        $rekening = $this->rekening($user);

        $this->danaDarurat($user, $rekening);
        $this->dpRumah($user, $rekening);
        $this->danaPensiun($user, $rekening);

        $this->command?->info('Akun demo siap: '.self::EMAIL.' / '.self::PASSWORD);
    }

    /** Hampir tercapai — memperlihatkan progress bar yang nyaris penuh. */
    private function danaDarurat(User $user, Account $rekening): void
    {
        $goal = $user->goals()->create([
            'type' => GoalType::Emergency,
            'name' => 'Dana Darurat',
            'target_amount' => 60_000_000,
            'initial_amount' => 10_000_000,
            // Dana darurat tidak punya tenggat (CLAUDE.md §5) — targetnya
            // "secepat mungkin", bukan tanggal tertentu.
            'target_date' => null,
            'estimated_return_rate' => 4.5,
            'estimated_inflation_rate' => 3,
            'risk_profile_override' => RiskProfile::Conservative,
            'status' => GoalStatus::Active,
        ]);

        $goal->update([
            'account_id' => $rekening->id,
            'allocated_amount' => 52_000_000,
            'priority' => GoalPriority::High->value,
        ]);
    }

    /** Di tengah jalan — keadaan paling umum. */
    private function dpRumah(User $user, Account $rekening): void
    {
        $goal = $user->goals()->create([
            'type' => GoalType::House,
            'name' => 'DP Rumah',
            'target_amount' => 200_000_000,
            'initial_amount' => 25_000_000,
            'target_date' => Carbon::today()->addYears(4)->toDateString(),
            'estimated_return_rate' => 7,
            'estimated_inflation_rate' => 4,
            'status' => GoalStatus::Active,
        ]);

        $goal->update([
            'account_id' => $rekening->id,
            'allocated_amount' => 45_000_000,
            'priority' => GoalPriority::Medium->value,
        ]);
    }

    /** Baru dimulai, jangka sangat panjang — progresnya sengaja kecil. */
    private function danaPensiun(User $user, Account $rekening): void
    {
        $goal = $user->goals()->create([
            'type' => GoalType::Retirement,
            'name' => 'Dana Pensiun',
            'target_amount' => 3_000_000_000,
            'initial_amount' => 0,
            'target_date' => Carbon::today()->addYears(30)->toDateString(),
            'estimated_return_rate' => 10,
            'estimated_inflation_rate' => 4,
            'risk_profile_override' => RiskProfile::Aggressive,
            'status' => GoalStatus::Active,
        ]);

        $goal->update([
            'account_id' => $rekening->id,
            'allocated_amount' => 10_000_000,
            'priority' => GoalPriority::Low->value,
        ]);
    }

    /**
     * Satu rekening bank berikut riwayat setahun terakhir.
     *
     * Transaksinya disebar mundur satu per bulan supaya grafik pertumbuhan
     * kekayaan di dashboard terisi merata dan menanjak, bukan melompat di
     * bukan satu titik. Saldonya sengaja dibuat cukup untuk menampung seluruh dana
     * yang ditandai ketiga tujuan — kalau tidak, LedgerGuard akan menolaknya
     * dan seeder gagal di tengah jalan.
     */
    private function rekening(User $user): Account
    {
        $rekening = $user->accounts()->create([
            'name' => 'BCA Utama',
            'kind' => AccountKind::Bank->value,
            'institution' => 'Bank BCA',
            'opening_balance' => 45_000_000,
        ]);

        $baris = [];
        $sekarang = Carbon::now();

        for ($i = 11; $i >= 0; $i--) {
            $tanggal = Carbon::today()->startOfMonth()->subMonths($i)->addDays(4);

            if ($tanggal->isFuture()) {
                continue;
            }

            $baris[] = [
                'user_id' => $user->id,
                'account_id' => $rekening->id,
                'type' => TransactionType::Income->value,
                'name' => 'Gaji bulanan',
                'amount' => 15_000_000,
                'category' => 'Gaji',
                'occurred_on' => $tanggal->toDateString(),
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];

            $baris[] = [
                'user_id' => $user->id,
                'account_id' => $rekening->id,
                'type' => TransactionType::Expense->value,
                'name' => 'Biaya hidup bulanan',
                'amount' => 9_000_000,
                'category' => 'Kebutuhan',
                'occurred_on' => $tanggal->copy()->addDay()->toDateString(),
                'created_at' => $sekarang,
                'updated_at' => $sekarang,
            ];
        }

        Transaction::insert($baris);

        return $rekening;
    }
}
