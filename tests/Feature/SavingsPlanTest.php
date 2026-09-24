<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\FinancialGoal;
use App\Models\Transaction;
use App\Models\User;
use App\Services\GoalCalculatorService;
use App\Services\SavingsPlanService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Rencana menabung (PRD FR-74..FR-78).
 *
 * Tiga sifat yang dijaga di sini, semuanya mudah rusak tanpa terlihat:
 *
 * 1. Kebutuhan bulanan memakai rumus ANUITAS yang sudah ada, bukan pembagian
 *    biasa seperti prototipe. Angkanya tidak dipatok di test — yang diuji
 *    adalah kesamaannya dengan GoalCalculatorService, supaya test tetap benar
 *    bila rumusnya suatu saat disempurnakan.
 * 2. Pembagian mengikuti prioritas lalu tenggat, dan tidak pernah melebihi
 *    kemampuan menabung.
 * 3. Kekurangan dana DITAMPILKAN, bukan ditutupi.
 */
class SavingsPlanTest extends TestCase
{
    use RefreshDatabase;

    private function rencana(User $user): array
    {
        return app(SavingsPlanService::class)->forUser($user->fresh());
    }

    private function anggaran(User $user, float $masuk, float $keluar, float $cadangan = 0): void
    {
        $user->budget()->updateOrCreate([], [
            'planned_income' => $masuk,
            'planned_expenses' => $keluar,
            'monthly_reserve' => $cadangan,
        ]);
    }

    private function tujuan(User $user, array $ubah = []): FinancialGoal
    {
        return $user->goals()->create(array_merge([
            'type' => GoalType::Custom->value,
            'name' => 'Rumah pertama',
            'target_amount' => 100_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 0,
            'target_date' => now(config('app.timezone'))->addMonths(24)->toDateString(),
            'estimated_return_rate' => 6,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::Medium->value,
        ], $ubah));
    }

    private function rekening(User $user, float $awal = 50_000_000): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['opening_balance' => $awal]);
    }

    // ── Kemampuan menabung ──────────────────────────────────────────────

    public function test_kemampuan_dihitung_dari_pemasukan_dikurangi_semua_kewajiban(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 17_500_000, 6_500_000, 1_500_000);
        Debt::factory()->for($user)->create([
            'principal' => 12_000_000, 'monthly_principal' => 1_000_000,
        ]);

        $this->assertSame(8_500_000.0, $this->rencana($user)['budget']['capacity']);
    }

    /**
     * Utang bersisa 300 ribu dengan rencana cicilan 1 juta hanya menyerap 300
     * ribu. Tanpa pembatasan itu, kemampuan menabung tampak lebih kecil
     * daripada sebenarnya tepat pada bulan terakhir cicilan.
     */
    public function test_cicilan_dibatasi_sisa_pokoknya(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user);
        $this->anggaran($user, 10_000_000, 5_000_000);

        $utang = Debt::factory()->for($user)->create([
            'principal' => 1_000_000, 'monthly_principal' => 1_000_000,
        ]);
        Transaction::factory()->for($user)->for($rekening)
            ->pembayaran(700_000, $utang)->create();

        $rencana = $this->rencana($user);

        $this->assertSame(300_000.0, $rencana['budget']['debt_principal']);
        $this->assertSame(4_700_000.0, $rencana['budget']['capacity']);
    }

    /** Anggaran tekor melaporkan kemampuan nol, bukan angka negatif. */
    public function test_anggaran_tekor_menghasilkan_kemampuan_nol(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 5_000_000, 8_000_000);

        $this->assertSame(0.0, $this->rencana($user)['budget']['capacity']);
    }

    public function test_tanpa_anggaran_kemampuan_nol_dan_tidak_error(): void
    {
        $user = User::factory()->create();
        $this->tujuan($user);

        $rencana = $this->rencana($user);

        $this->assertSame(0.0, $rencana['budget']['capacity']);
        $this->assertSame(0.0, $rencana['rows'][0]['allocation']);
    }

    // ── Kebutuhan memakai anuitas ───────────────────────────────────────

    /**
     * Ini alasan utama fase ini ada: prototipe memakai pembagian biasa, dan
     * kita TIDAK. Angkanya dibaca dari kalkulator, bukan dipatok — supaya
     * test tetap benar bila rumusnya disempurnakan.
     */
    public function test_kebutuhan_bulanan_memakai_rumus_anuitas_bukan_pembagian_biasa(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 100_000_000, 0);
        $goal = $this->tujuan($user, ['estimated_return_rate' => 8]);

        $diharapkan = app(GoalCalculatorService::class)->calculateMonthlyContribution(
            targetAmount: 100_000_000,
            currentAmount: 0,
            months: 24,
            annualReturnRate: 8,
            annualInflationRate: 0,
        )['monthly_contribution_required'];

        $kebutuhan = $this->rencana($user)['rows'][0]['need'];

        $this->assertSame((float) $diharapkan, $kebutuhan);

        // Dan benar-benar lebih murah daripada pembagian biasa, karena dananya
        // bertumbuh. Kalau suatu saat sama persis, anuitasnya tidak terpakai.
        $this->assertLessThan(100_000_000 / 24, $kebutuhan);
        $this->assertNotNull($goal->id);
    }

    public function test_dana_yang_sudah_ditandai_mengurangi_kebutuhan(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 100_000_000, 0);
        $rekening = $this->rekening($user, 60_000_000);

        $tanpa = $this->tujuan($user);
        $kebutuhanAwal = $this->rencana($user)['rows'][0]['need'];

        $tanpa->update(['account_id' => $rekening->id, 'allocated_amount' => 50_000_000]);

        $this->assertLessThan($kebutuhanAwal, $this->rencana($user)['rows'][0]['need']);
    }

    public function test_target_yang_sudah_tercapai_tidak_menyerap_anggaran(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 10_000_000, 0);
        $rekening = $this->rekening($user, 100_000_000);

        $this->tujuan($user, [
            'account_id' => $rekening->id,
            'allocated_amount' => 100_000_000,
        ]);

        $baris = $this->rencana($user)['rows'][0];

        $this->assertTrue($baris['achieved']);
        $this->assertSame(0.0, $baris['need']);
        $this->assertSame(0.0, $baris['allocation']);
    }

    /**
     * Tanpa tenggat, setoran berapa pun secara matematis "cukup" — tidak ada
     * kebutuhan bulanan yang bisa dihitung. Ia tidak boleh menyerap dana dan
     * menggeser target yang benar-benar dikejar tanggal.
     */
    public function test_target_tanpa_tenggat_tidak_menyerap_dana(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 10_000_000, 0);

        $this->tujuan($user, ['name' => 'Dana darurat', 'target_date' => null]);
        $this->tujuan($user, ['name' => 'Rumah']);

        $rencana = $this->rencana($user);
        $darurat = collect($rencana['rows'])->firstWhere('name', 'Dana darurat');
        $rumah = collect($rencana['rows'])->firstWhere('name', 'Rumah');

        $this->assertSame(0.0, $darurat['allocation']);
        $this->assertGreaterThan(0, $rumah['allocation']);
    }

    /**
     * Tenggat yang sudah LEWAT menjatuhkan seluruh kekurangan ke bulan ini.
     * Menyebarnya ke bulan berikutnya berarti diam-diam memundurkan tenggat
     * yang pengguna tetapkan sendiri.
     */
    public function test_tenggat_yang_sudah_lewat_dihitung_satu_bulan(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 500_000_000, 0);
        $this->tujuan($user, [
            'target_amount' => 10_000_000,
            'target_date' => now(config('app.timezone'))->subMonths(2)->toDateString(),
        ]);

        $baris = $this->rencana($user)['rows'][0];

        $this->assertSame(1, $baris['months_left']);
        $this->assertGreaterThanOrEqual(9_000_000, $baris['need']);
    }

    // ── Pembagian ───────────────────────────────────────────────────────

    public function test_prioritas_tinggi_dilayani_lebih_dulu(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 3_000_000, 0);

        $this->tujuan($user, ['name' => 'Rendah', 'priority' => GoalPriority::Low->value]);
        $this->tujuan($user, ['name' => 'Tinggi', 'priority' => GoalPriority::High->value]);

        $baris = $this->rencana($user)['rows'];

        $this->assertSame('Tinggi', $baris[0]['name']);
        $this->assertSame('Rendah', $baris[1]['name']);
        $this->assertGreaterThan($baris[1]['allocation'], $baris[0]['allocation']);
    }

    public function test_prioritas_sama_diurutkan_tenggat_terdekat(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 1_000_000, 0);

        $this->tujuan($user, [
            'name' => 'Jauh',
            'target_date' => now(config('app.timezone'))->addMonths(36)->toDateString(),
        ]);
        $this->tujuan($user, [
            'name' => 'Dekat',
            'target_date' => now(config('app.timezone'))->addMonths(6)->toDateString(),
        ]);

        $this->assertSame('Dekat', $this->rencana($user)['rows'][0]['name']);
    }

    /** Uang yang sama tidak boleh dijanjikan ke dua target sekaligus. */
    public function test_total_alokasi_tidak_pernah_melebihi_kemampuan(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 2_000_000, 0);

        foreach (['A', 'B', 'C'] as $nama) {
            $this->tujuan($user, ['name' => $nama]);
        }

        $rencana = $this->rencana($user);

        $this->assertLessThanOrEqual(
            $rencana['budget']['capacity'],
            $rencana['total_allocation'],
        );
    }

    public function test_kekurangan_dana_ditampilkan_bukan_ditutupi(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 1_000_000, 0);
        $this->tujuan($user, ['target_amount' => 500_000_000]);

        $rencana = $this->rencana($user);
        $baris = $rencana['rows'][0];

        $this->assertGreaterThan(0, $baris['shortfall']);
        $this->assertSame(
            round($baris['need'] - $baris['allocation'], 2),
            $baris['shortfall'],
        );
        $this->assertGreaterThan(0, $rencana['total_shortfall']);
    }

    public function test_sisa_kemampuan_yang_belum_terpakai_dilaporkan(): void
    {
        $user = User::factory()->create();
        $this->anggaran($user, 100_000_000, 0);
        $this->tujuan($user, ['target_amount' => 1_000_000]);

        $rencana = $this->rencana($user);

        $this->assertSame(0.0, $rencana['total_shortfall']);
        $this->assertGreaterThan(0, $rencana['unallocated']);
    }

    // ── Alokasi dana ke rekening ────────────────────────────────────────

    public function test_alokasi_tersimpan_dan_tidak_mengurangi_saldo_rekening(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 50_000_000);
        $goal = $this->tujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => $rekening->id,
                'allocated_amount' => 10_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(10_000_000.0, (float) $goal->fresh()->allocated_amount);
        $this->assertSame(
            50_000_000.0,
            app(\App\Services\AccountBalanceService::class)->forUser($user)[$rekening->id],
        );
    }

    /**
     * Uang yang sama tidak boleh ditandai untuk dua target. Tanpa aturan ini
     * kedua target tampak berjalan sesuai rencana padahal hanya satu yang
     * bisa dipenuhi.
     */
    public function test_alokasi_melebihi_saldo_rekening_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);

        $pertama = $this->tujuan($user, ['name' => 'A']);
        $kedua = $this->tujuan($user, ['name' => 'B']);

        $this->actingAs($user)->patch(route('goals.allocation.update', $pertama), [
            'account_id' => $rekening->id,
            'allocated_amount' => 8_000_000,
            'priority' => GoalPriority::High->value,
        ])->assertSessionHasNoErrors();

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $kedua), [
                'account_id' => $rekening->id,
                'allocated_amount' => 5_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasErrors('allocated_amount');

        $this->assertSame(0.0, (float) $kedua->fresh()->allocated_amount);
    }

    /** Pengeluaran yang memakai uang sudah ditandai target juga ditolak. */
    public function test_pengeluaran_yang_memakan_dana_tertandai_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 10_000_000);
        $goal = $this->tujuan($user);

        $this->actingAs($user)->patch(route('goals.allocation.update', $goal), [
            'account_id' => $rekening->id,
            'allocated_amount' => 9_000_000,
            'priority' => GoalPriority::High->value,
        ]);

        $this->actingAs($user)
            ->post(route('transactions.store'), [
                'account_id' => $rekening->id,
                'type' => 'expense',
                'name' => 'Belanja besar',
                'amount' => 2_000_000,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasErrors('amount');
    }

    public function test_dana_target_hanya_boleh_di_rekening_likuid(): void
    {
        $user = User::factory()->create();
        $saham = Account::factory()->for($user)->jenis(AccountKind::Stock)
            ->create(['opening_balance' => 50_000_000]);
        $goal = $this->tujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => $saham->id,
                'allocated_amount' => 1_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasErrors('account_id');
    }

    public function test_alokasi_melebihi_target_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->rekening($user, 500_000_000);
        $goal = $this->tujuan($user, ['target_amount' => 10_000_000]);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => $rekening->id,
                'allocated_amount' => 11_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasErrors('allocated_amount');
    }

    public function test_menandai_dana_tanpa_rekening_ditolak(): void
    {
        $user = User::factory()->create();
        $goal = $this->tujuan($user);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => null,
                'allocated_amount' => 5_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasErrors('account_id');
    }

    // ── Halaman & otorisasi ─────────────────────────────────────────────

    public function test_halaman_menampilkan_rencana_milik_sendiri(): void
    {
        $orangLain = User::factory()->create();
        $this->tujuan($orangLain, ['name' => 'Punya orang lain']);

        $saya = User::factory()->create();
        $this->anggaran($saya, 5_000_000, 1_000_000);
        $this->tujuan($saya, ['name' => 'Punya saya']);

        $this->actingAs($saya)
            ->get(route('savings-plan.index'))
            ->assertInertia(fn ($page) => $page
                ->component('SavingsPlan/Index')
                ->has('plan.rows', 1)
                ->where('plan.rows.0.name', 'Punya saya')
                ->where('plan.budget.capacity', 4_000_000),
            );
    }

    public function test_tidak_bisa_mengubah_alokasi_tujuan_orang_lain(): void
    {
        $goal = $this->tujuan(User::factory()->create());

        $this->actingAs(User::factory()->create())
            ->patch(route('goals.allocation.update', $goal), [
                'account_id' => null,
                'allocated_amount' => 0,
                'priority' => GoalPriority::High->value,
            ])
            ->assertForbidden();
    }

    public function test_tamu_tidak_bisa_membuka_rencana(): void
    {
        $this->get(route('savings-plan.index'))->assertRedirect(route('login'));
    }
}
