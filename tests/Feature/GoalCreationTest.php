<?php

namespace Tests\Feature;

use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class GoalCreationTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    /** Payload yang sah, boleh ditimpa sebagian per kasus uji. */
    private function payload(array $ubah = []): array
    {
        return array_merge([
            'type' => GoalType::House->value,
            'name' => 'DP Rumah Pertama',
            'target_amount' => 500000000,
            'initial_amount' => 50000000,
            'target_date' => Carbon::now()->addYears(5)->toDateString(),
            'estimated_return_rate' => 7,
            'estimated_inflation_rate' => 3.5,
        ], $ubah);
    }

    // ── Akses ───────────────────────────────────────────────────────────

    public function test_tamu_tidak_bisa_membuka_form_buat_tujuan(): void
    {
        $this->get(route('goals.create'))->assertRedirect(route('login'));
        $this->post(route('goals.store'), $this->payload())->assertRedirect(route('login'));
    }

    public function test_pengguna_dapat_membuka_form_buat_tujuan(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('goals.create'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->component('Goal/Create')
                ->where('isFirstGoal', true)
                ->has('goalTypes', count(GoalType::cases())));
    }

    /**
     * Daftar jenis tujuan berasal dari enum, bukan ditulis ulang di frontend.
     * Menambah jenis baru harus cukup di satu tempat.
     */
    public function test_daftar_jenis_tujuan_mengikuti_enum(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('goals.create'))
            ->assertInertia(fn ($page) => $page
                ->where('goalTypes.0.value', GoalType::cases()[0]->value)
                ->where('goalTypes.0.label', GoalType::cases()[0]->label()));
    }

    // ── Pembuatan ───────────────────────────────────────────────────────

    public function test_tujuan_tersimpan_dan_terhubung_ke_pengguna(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('goals.store'), $this->payload())
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('goals.index'));

        $goal = FinancialGoal::sole();

        $this->assertSame($user->id, $goal->user_id);
        $this->assertSame('DP Rumah Pertama', $goal->name);
        $this->assertSame(GoalType::House, $goal->type);
        $this->assertSame(GoalStatus::Active, $goal->status);
        $this->assertSame('500000000.00', $goal->target_amount);
        $this->assertSame('50000000.00', $goal->initial_amount);
    }

    /**
     * Snapshot perhitungan ikut tersimpan supaya angka yang dijanjikan saat
     * tujuan dibuat tetap bisa dijelaskan nanti, meski rumusnya berubah
     * (formula_version — PRD D-6).
     */
    public function test_snapshot_perhitungan_ikut_tersimpan(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload());

        $kalkulasi = FinancialGoal::sole()->calculations()->sole();

        $this->assertGreaterThan(0, (float) $kalkulasi->monthly_contribution_required);
        $this->assertSame(1, $kalkulasi->formula_version);
        $this->assertArrayHasKey('future_value_target', $kalkulasi->calculation_snapshot);
    }

    /**
     * Dana darurat tidak bertenggat, jadi tidak ada jangka waktu — dan tanpa
     * jangka waktu, setoran bulanan tidak punya arti untuk dihitung.
     */
    public function test_dana_darurat_boleh_tanpa_tanggal_target(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload([
                'type' => GoalType::Emergency->value,
                'name' => 'Dana Darurat',
                'target_date' => null,
            ]))
            ->assertSessionHasNoErrors();

        $goal = FinancialGoal::sole();

        $this->assertNull($goal->target_date);
        $this->assertSame(0, $goal->calculations()->count());
    }

    public function test_jenis_bertenggat_wajib_punya_tanggal_target(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload(['target_date' => null]))
            ->assertSessionHasErrors('target_date');

        $this->assertSame(0, FinancialGoal::count());
    }

    // ── Validasi ────────────────────────────────────────────────────────

    public static function payloadTidakSah(): array
    {
        return [
            'nama kosong' => [['name' => ''], 'name'],
            'jenis tidak dikenal' => [['type' => 'liburan-ke-mars'], 'type'],
            'target nol' => [['target_amount' => 0], 'target_amount'],
            'target negatif' => [['target_amount' => -1000], 'target_amount'],
            'imbal hasil tidak masuk akal' => [['estimated_return_rate' => 45], 'estimated_return_rate'],
            'inflasi tidak masuk akal' => [['estimated_inflation_rate' => 35], 'estimated_inflation_rate'],
            'tanggal target di masa lalu' => [['target_date' => '2020-01-01'], 'target_date'],
        ];
    }

    /**
     * @dataProvider payloadTidakSah
     */
    public function test_menolak_masukan_yang_tidak_sah(array $ubah, string $kolom): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload($ubah))
            ->assertSessionHasErrors($kolom);

        $this->assertSame(0, FinancialGoal::count());
    }

    /**
     * Dana awal yang sudah melampaui target berarti tujuannya tercapai — dan
     * membuat tujuan yang sejak lahir sudah selesai hanya membingungkan.
     */
    public function test_menolak_dana_awal_yang_melampaui_target(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload([
                'target_amount' => 100000000,
                'initial_amount' => 100000000,
            ]))
            ->assertSessionHasErrors('initial_amount');
    }

    public function test_pesan_kesalahan_berbahasa_indonesia(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload(['name' => '']));

        $pesan = session('errors')->first('name');

        $this->assertSame('Nama tujuan wajib diisi.', $pesan);
    }

    // ── Integrasi dashboard ─────────────────────────────────────────────

    /**
     * Inti dari alur ini: setelah tujuan pertama dibuat, dashboard harus
     * berhenti menampilkan mode contoh dan beralih ke data asli.
     */
    public function test_dashboard_beralih_dari_mode_contoh_setelah_tujuan_dibuat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('summary.active_goals_count', 0));

        $this->actingAs($user)->post(route('goals.store'), $this->payload());

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page
                ->where('summary.active_goals_count', 1)
                ->where('summary.primary_goal.name', 'DP Rumah Pertama'));
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tujuan_tidak_bocor_ke_pengguna_lain(): void
    {
        $this->actingAs(User::factory()->create())
            ->post(route('goals.store'), $this->payload());

        $this->actingAs(User::factory()->create())
            ->get(route('dashboard'))
            ->assertInertia(fn ($page) => $page->where('summary.active_goals_count', 0));
    }

    public function test_tujuan_kedua_tidak_lagi_disebut_tujuan_pertama(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->post(route('goals.store'), $this->payload());

        $this->actingAs($user)
            ->get(route('goals.create'))
            ->assertInertia(fn ($page) => $page->where('isFirstGoal', false));
    }
}
