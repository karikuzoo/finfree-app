<?php

namespace Database\Seeders;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Enums\RiskProfile;
use App\Models\FinancialGoal;
use App\Models\GoalContribution;
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
 * baru dimulai dengan jangka sangat panjang. Setoran disebar ke belakang
 * selama 12 bulan supaya grafik pertumbuhan aset punya kurva sungguhan, bukan
 * satu titik.
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

        // Bersihkan tujuan lama milik akun demo saja. Akun lain tidak disentuh.
        $user->goals()->delete();

        $this->danaDarurat($user);
        $this->dpRumah($user);
        $this->danaPensiun($user);

        $this->command?->info('Akun demo siap: '.self::EMAIL.' / '.self::PASSWORD);
    }

    /** Hampir tercapai — memperlihatkan progress bar yang nyaris penuh. */
    private function danaDarurat(User $user): void
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

        $this->setoranBulanan($goal, jumlah: 12, nominal: 3_500_000, catatan: 'Setoran rutin');
    }

    /** Di tengah jalan — keadaan paling umum. */
    private function dpRumah(User $user): void
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

        $this->setoranBulanan($goal, jumlah: 10, nominal: 4_000_000, catatan: 'Sisihan gaji');
    }

    /** Baru dimulai, jangka sangat panjang — progresnya sengaja kecil. */
    private function danaPensiun(User $user): void
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

        $this->setoranBulanan($goal, jumlah: 6, nominal: 2_000_000, catatan: 'Reksa dana saham');
    }

    /**
     * Setoran disebar mundur satu per bulan dari bulan ini, supaya deret
     * pertumbuhan aset 12 bulan di dashboard terisi merata dan grafiknya
     * menanjak, bukan melompat di satu titik.
     */
    private function setoranBulanan(
        FinancialGoal $goal,
        int $jumlah,
        int $nominal,
        string $catatan,
    ): void {
        $baris = [];

        for ($i = $jumlah - 1; $i >= 0; $i--) {
            $baris[] = [
                'financial_goal_id' => $goal->id,
                'amount' => $nominal,
                'contributed_on' => Carbon::today()->subMonthsNoOverflow($i)->startOfMonth()->addDays(4),
                'note' => $catatan,
                'created_at' => now(),
                'updated_at' => now(),
            ];
        }

        GoalContribution::insert($baris);
    }
}
