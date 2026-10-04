<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

/**
 * Tabungan mata uang asing (jenis `valas`, 4 Okt 2026).
 *
 * Dicatat dalam rupiah; jumlah valasnya keterangan di `units`, mata uangnya di
 * `currency`. Likuid (boleh menampung dana tujuan, keputusan pengguna) tetapi
 * tetap perlu dinilai ulang saat kurs bergerak.
 */
class ForeignCurrencyAccountTest extends TestCase
{
    use RefreshDatabase;

    private function isian(array $ubah = []): array
    {
        return array_merge([
            'name' => 'Tabungan USD',
            'kind' => AccountKind::ForeignCurrency->value,
            'currency' => 'USD',
            'institution' => 'BCA',
            'opening_balance' => 24_450_000,
            'units' => 1500,
        ], $ubah);
    }

    private function valas(User $user, array $ubah = []): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::ForeignCurrency)
            ->create(array_merge(['currency' => 'USD', 'units' => 1500, 'opening_balance' => 24_450_000], $ubah));
    }

    public function test_rekening_valas_tersimpan_dengan_mata_uang_dan_jumlahnya(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian())
            ->assertSessionHasNoErrors();

        $rekening = $user->accounts()->sole();
        $this->assertSame(AccountKind::ForeignCurrency, $rekening->kind);
        $this->assertSame('USD', $rekening->currency);
        $this->assertSame(1500.0, (float) $rekening->units);
        $this->assertSame('USD', $rekening->satuan());
    }

    public function test_valas_wajib_memilih_mata_uang_yang_dikenal(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['currency' => null]))
            ->assertSessionHasErrors(['currency' => 'Pilih mata uangnya.']);

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['currency' => 'XYZ']))
            ->assertSessionHasErrors('currency');

        $this->assertDatabaseCount('accounts', 0);
    }

    /** Mata uang yang terbawa dari form dibuang untuk jenis lain, bukan ditolak. */
    public function test_mata_uang_diabaikan_untuk_jenis_selain_valas(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('accounts.store'), $this->isian(['kind' => AccountKind::Bank->value, 'units' => null]))
            ->assertSessionHasNoErrors();

        $this->assertNull($user->accounts()->sole()->currency);
    }

    public function test_mata_uang_terkunci_begitu_ada_transaksi(): void
    {
        $user = User::factory()->create();
        $rekening = $this->valas($user);
        Transaction::factory()->for($user)->for($rekening)->pemasukan(1_000_000)->create();

        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian(['currency' => 'SGD']))
            ->assertSessionHasErrors('currency');

        $this->assertSame('USD', $rekening->fresh()->currency);

        // Mata uang yang sama tetap boleh dikirim ulang (form mengirim semua isian).
        $this->actingAs($user)
            ->patch(route('accounts.update', $rekening), $this->isian(['name' => 'USD liburan']))
            ->assertSessionHasNoErrors();
    }

    public function test_kartu_menampilkan_kode_mata_uang_sebagai_satuan(): void
    {
        $user = User::factory()->create();
        $this->valas($user);

        $this->actingAs($user)
            ->get(route('accounts.index'))
            ->assertInertia(fn (Assert $page) => $page
                ->where('accounts.0.kind_label', 'Valas')
                ->where('accounts.0.unit', 'USD')
                ->where('accounts.0.currency', 'USD')
                ->where('accounts.0.needs_valuation', true)
                ->has('currencies'));
    }

    /** Kurs bergerak: nilai rupiah dan jumlah valas diperbarui di satu tempat. */
    public function test_penilaian_ulang_memperbarui_nilai_dan_jumlah_valas(): void
    {
        $user = User::factory()->create();
        $rekening = $this->valas($user);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $rekening), [
                'value' => 26_000_000,
                'units' => 1600,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(1600.0, (float) $rekening->fresh()->units);
        $this->assertDatabaseHas('transactions', [
            'account_id' => $rekening->id,
            'type' => 'adjustment',
            'amount' => 1_550_000,
        ]);
    }

    /** Keputusan pengguna 4 Okt 2026: valas boleh menampung dana tujuan. */
    public function test_valas_boleh_menampung_dana_tujuan(): void
    {
        $user = User::factory()->create();
        $rekening = $this->valas($user);
        $tujuan = $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => 'Umrah',
            'target_amount' => 40_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 0,
            'target_date' => now()->addYears(2)->toDateString(),
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::Medium->value,
        ]);

        $this->actingAs($user)
            ->patch(route('goals.allocation.update', $tujuan), [
                'account_id' => $rekening->id,
                'allocated_amount' => 10_000_000,
                'priority' => GoalPriority::High->value,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame($rekening->id, $tujuan->fresh()->account_id);
    }

    /**
     * Akibat valas boleh menampung dana tujuan: bila kurs turun sampai nilai
     * rupiahnya di bawah dana tujuan yang ditandai, penilaian ulang ditolak
     * LedgerGuard — alokasinya harus dikurangi dulu. Dikunci di sini supaya
     * perilaku ini disengaja, bukan kebetulan.
     */
    public function test_kurs_turun_di_bawah_dana_tujuan_ditolak(): void
    {
        $user = User::factory()->create();
        $rekening = $this->valas($user);
        $user->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => 'Umrah',
            'target_amount' => 40_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 20_000_000,
            'account_id' => $rekening->id,
            'target_date' => now()->addYears(2)->toDateString(),
            'estimated_return_rate' => 0,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::Medium->value,
        ]);

        $this->actingAs($user)
            ->post(route('accounts.valuation.store', $rekening), [
                'value' => 18_000_000,
                'occurred_on' => now(config('app.timezone'))->toDateString(),
            ])
            ->assertSessionHasErrors('value');

        $this->assertDatabaseMissing('transactions', ['account_id' => $rekening->id]);
    }

    public function test_cadangan_membawa_mata_uang_dan_memulihkannya(): void
    {
        $user = User::factory()->create();
        $this->valas($user, ['currency' => 'SAR', 'units' => 3000]);
        $layanan = app(BackupService::class);

        $isi = $layanan->export($user);
        $this->assertSame('SAR', $isi['accounts'][0]['currency']);

        $layanan->import($user, $isi);

        $pulih = $user->accounts()->sole();
        $this->assertSame('SAR', $pulih->currency);
        $this->assertSame(3000.0, (float) $pulih->units);
    }

    public function test_cadangan_valas_tanpa_mata_uang_ditolak(): void
    {
        $user = User::factory()->create();
        $this->valas($user);
        $layanan = app(BackupService::class);

        $isi = $layanan->export($user);
        unset($isi['accounts'][0]['currency']);

        $this->expectException(\Illuminate\Validation\ValidationException::class);
        $layanan->import($user, $isi);
    }
}
