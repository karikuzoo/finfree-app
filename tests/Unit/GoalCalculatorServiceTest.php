<?php

namespace Tests\Unit;

use App\Services\GoalCalculatorService;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

/**
 * Mesin kalkulator adalah fungsi matematis murni tanpa efek samping —
 * cakupan pengujiannya harus paling tinggi di seluruh aplikasi.
 *
 * Sumber kebenaran kasus uji ada di docs/fixtures/calculator-cases.json,
 * berkas yang sama yang dipakai test JavaScript untuk preview real-time.
 * Bila kedua implementasi bisa berbeda diam-diam, cepat atau lambat mereka
 * akan berbeda.
 */
class GoalCalculatorServiceTest extends TestCase
{
    private GoalCalculatorService $calculator;

    protected function setUp(): void
    {
        parent::setUp();
        $this->calculator = new GoalCalculatorService();
    }

    public static function fixtureCases(): array
    {
        $path = dirname(__DIR__, 2).'/docs/fixtures/calculator-cases.json';

        if (! is_file($path)) {
            throw new \RuntimeException("Test vector tidak ditemukan di {$path}");
        }

        $fixture = json_decode(file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

        return array_reduce(
            $fixture['cases'],
            fn (array $carry, array $case) => $carry + [$case['name'] => [$case]],
            [],
        );
    }

    #[DataProvider('fixtureCases')]
    public function test_cocok_dengan_test_vector(array $case): void
    {
        $in = $case['input'];

        $result = $this->calculator->calculateMonthlyContribution(
            $in['target_amount'],
            $in['current_amount'],
            $in['months'],
            $in['annual_return_rate'],
            $in['annual_inflation_rate'],
        );

        foreach (['monthly_contribution_required', 'future_value_target', 'future_value_projection', 'total_contribution_projection', 'total_investment_growth_projection'] as $key) {
            $this->assertSame(
                $case['expected'][$key],
                $result[$key],
                "Kasus '{$case['name']}': {$key} tidak sesuai test vector."
            );
        }

        $this->assertEqualsWithDelta(
            $case['expected']['monthly_rate'],
            $result['monthly_rate'],
            1e-10,
            "Kasus '{$case['name']}': monthly_rate tidak sesuai."
        );
    }

    /**
     * Penjaga D-1. Bila suatu saat ada yang mengubah rumus menjadi real return
     * (memotong return dengan inflasi), target masa depan tidak akan naik dan
     * test ini gagal. Perbaiki rumusnya, bukan angka harapannya.
     */
    public function test_inflasi_menaikkan_target_bukan_memotong_return(): void
    {
        $tanpaInflasi = $this->calculator->calculateMonthlyContribution(500_000_000, 0, 120, 8, 0);
        $denganInflasi = $this->calculator->calculateMonthlyContribution(500_000_000, 0, 120, 8, 3.5);

        $this->assertSame(500_000_000, $tanpaInflasi['future_value_target']);
        $this->assertGreaterThan(
            $tanpaInflasi['future_value_target'],
            $denganInflasi['future_value_target'],
            'Inflasi harus menaikkan nominal target (D-1).'
        );

        // Rate bulanan tidak boleh ikut berubah karena inflasi — kalau berubah,
        // berarti return sedang dipotong inflasi juga: perhitungan ganda.
        $this->assertEqualsWithDelta(
            $tanpaInflasi['monthly_rate'],
            $denganInflasi['monthly_rate'],
            1e-12,
            'Return nominal tidak boleh dipotong inflasi — itu perhitungan ganda.'
        );
    }

    /**
     * Penjaga D-2. Pada ordinary annuity dengan tenor 1 bulan, setoran tunggal
     * terjadi di akhir periode sehingga tidak sempat berbunga sama sekali.
     * Kalau hasilnya lebih kecil dari target, rumusnya sudah berubah jadi
     * annuity due (setoran di awal bulan).
     */
    public function test_tenor_satu_bulan_setara_target_penuh(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(10_000_000, 0, 1, 12, 0);

        $this->assertSame(10_000_000, $result['monthly_contribution_required']);
    }

    public function test_return_nol_tidak_membagi_nol(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(60_000_000, 0, 12, 0, 0);

        $this->assertSame(5_000_000, $result['monthly_contribution_required']);
        $this->assertSame(0.0, $result['monthly_rate']);
    }

    public function test_dana_awal_melebihi_target_menghasilkan_nol_bukan_negatif(): void
    {
        $result = $this->calculator->calculateMonthlyContribution(100_000_000, 150_000_000, 60, 6, 0);

        $this->assertSame(0, $result['monthly_contribution_required']);
        $this->assertTrue($result['already_achieved']);
    }

    public function test_dana_awal_yang_bertumbuh_melebihi_target_juga_nol(): void
    {
        // 90jt @6%/thn selama 5 tahun tumbuh melewati 100jt tanpa setoran apa pun.
        $result = $this->calculator->calculateMonthlyContribution(100_000_000, 90_000_000, 60, 6, 0);

        $this->assertSame(0, $result['monthly_contribution_required']);
    }

    public function test_setoran_dibulatkan_ke_atas_sehingga_target_tercapai(): void
    {
        foreach (self::fixtureCases() as [$case]) {
            $result = $this->calculator->calculateMonthlyContribution(
                $case['input']['target_amount'],
                $case['input']['current_amount'],
                $case['input']['months'],
                $case['input']['annual_return_rate'],
                $case['input']['annual_inflation_rate'],
            );

            $this->assertGreaterThanOrEqual(
                $result['future_value_target'],
                $result['future_value_projection'],
                "Kasus '{$case['name']}': proyeksi tidak boleh berakhir di bawah target."
            );
        }
    }

    public function test_konversi_rate_memakai_effective_annual_bukan_pembagian_dua_belas(): void
    {
        $i = $this->calculator->monthlyRate(12);

        // Effective: (1.12)^(1/12)-1 ≈ 0.009489, bukan 0.01.
        $this->assertEqualsWithDelta(0.0094887929, $i, 1e-9);
        $this->assertEqualsWithDelta(0.12, (1 + $i) ** 12 - 1, 1e-12);
    }

    public function test_menolak_jangka_waktu_nol_atau_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(100_000_000, 0, 0, 8, 0);
    }

    public function test_menolak_target_nol(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(0, 0, 12, 8, 0);
    }

    public function test_menolak_dana_awal_negatif(): void
    {
        $this->expectException(InvalidArgumentException::class);
        $this->calculator->calculateMonthlyContribution(100_000_000, -1, 12, 8, 0);
    }
}
