<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalPriority;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use App\Services\AccountBalanceService;
use App\Services\BackupService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Tests\TestCase;

/**
 * Cadangan dan pemulihan (PRD FR-83, FR-84).
 *
 * Yang dijaga di sini bukan sekadar "berkasnya jadi", melainkan bahwa
 * cadangan dan pemulihan benar-benar SALING MEMBALIKKAN: memulihkan cadangan
 * sebuah akun harus mengembalikan keadaan yang persis sama. Cadangan yang
 * tidak bisa dipulihkan utuh lebih berbahaya daripada tidak ada cadangan sama
 * sekali — pengguna merasa aman padahal tidak.
 */
class BackupTest extends TestCase
{
    use RefreshDatabase;

    private function akunTerisi(User $user): array
    {
        $bank = Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(['name' => 'BCA Utama', 'opening_balance' => 20_000_000]);
        $emas = Account::factory()->for($user)->jenis(AccountKind::Gold)
            ->create(['name' => 'Emas batangan', 'opening_balance' => 10_000_000]);

        $utang = Debt::factory()->for($user)->create([
            'name' => 'Cicilan motor', 'principal' => 5_000_000,
        ]);

        Transaction::factory()->for($user)->for($bank)->pemasukan(3_000_000)->create();
        Transaction::factory()->for($user)->for($bank)->transfer(1_000_000, $emas)->create();
        Transaction::factory()->for($user)->for($bank)->pembayaran(500_000, $utang)->create();

        $goal = $user->goals()->create([
            'account_id' => $bank->id,
            'type' => GoalType::Custom->value,
            'name' => 'Dana Darurat',
            'target_amount' => 60_000_000,
            'initial_amount' => 0,
            'allocated_amount' => 5_000_000,
            'target_date' => null,
            'estimated_return_rate' => 4,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
            'priority' => GoalPriority::High->value,
        ]);

        $user->budget()->create([
            'planned_income' => 15_000_000,
            'planned_expenses' => 7_000_000,
            'monthly_reserve' => 1_000_000,
        ]);

        $user->calendarNotes()->create([
            'note_date' => '2026-09-10',
            'body' => 'Gajian, sisihkan untuk dana darurat',
        ]);

        $user->reminders()->create([
            'title' => 'Bayar listrik',
            'remind_at' => '2026-09-20 09:00:00',
        ]);

        return compact('bank', 'emas', 'utang', 'goal');
    }

    private function berkas(array $isi): UploadedFile
    {
        $jalur = tempnam(sys_get_temp_dir(), 'uji-cadangan-').'.json';
        file_put_contents($jalur, json_encode($isi));

        return new UploadedFile($jalur, 'cadangan.json', 'application/json', null, true);
    }

    // ── Isi cadangan ────────────────────────────────────────────────────

    public function test_cadangan_memuat_seluruh_data_keuangan(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $isi = app(BackupService::class)->export($user);

        $this->assertSame(BackupService::VERSION, $isi['arus_backup_version']);
        $this->assertCount(2, $isi['accounts']);
        $this->assertCount(1, $isi['debts']);
        $this->assertCount(3, $isi['transactions']);
        $this->assertCount(1, $isi['goals']);
        $this->assertCount(1, $isi['calendar_notes']);
        $this->assertCount(1, $isi['reminders']);
        $this->assertSame(15_000_000.0, $isi['budget']['planned_income']);
    }

    /**
     * Catatan kalender dan pengingat diketik sendiri oleh pengguna dan tidak
     * bisa dibuat ulang dari data lain. Cadangan yang meninggalkannya terasa
     * lengkap sampai saat pemulihan — ketika sudah terlambat.
     */
    public function test_catatan_dan_pengingat_pulih_lengkap_dengan_jamnya(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $cadangan = app(BackupService::class)->export($user);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasNoErrors();

        $catatan = $user->calendarNotes()->sole();
        $pengingat = $user->reminders()->sole();

        $this->assertSame('2026-09-10', $catatan->note_date->toDateString());
        $this->assertSame('Gajian, sisihkan untuk dana darurat', $catatan->body);

        $this->assertSame('Bayar listrik', $pengingat->title);
        // Jamnya ikut: pengingat "bayar listrik jam 9" kehilangan gunanya
        // bila yang bertahan cuma tanggalnya.
        $this->assertSame('2026-09-20 09:00', $pengingat->remind_at->format('Y-m-d H:i'));
    }

    public function test_status_selesai_pengingat_ikut_pulih(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $user->reminders()->create([
            'title' => 'Sudah dikerjakan',
            'remind_at' => '2026-09-01 08:00:00',
            'completed_at' => '2026-09-01 08:30:00',
        ]);

        $cadangan = app(BackupService::class)->export($user);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)]);

        $selesai = $user->reminders()->where('title', 'Sudah dikerjakan')->sole();

        $this->assertTrue($selesai->isCompleted());
    }

    /**
     * Berkas dari versi lama yang belum punya kunci ini harus ditolak, bukan
     * diam-diam dipulihkan dengan catatan kosong. Pengguna akan mengira
     * catatannya memang tidak pernah ada.
     */
    public function test_berkas_tanpa_kunci_catatan_ditolak(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $lama = app(BackupService::class)->export($user);
        unset($lama['calendar_notes'], $lama['reminders']);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($lama)])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(1, $user->calendarNotes()->count());
    }

    /**
     * Berkas cadangan gampang tersimpan bertahun-tahun di folder Downloads
     * atau ikut tersalin ke awan. Isinya dibatasi pada yang dibutuhkan untuk
     * memulihkan catatan keuangan — bukan identitas pemiliknya.
     */
    public function test_cadangan_tidak_memuat_data_profil(): void
    {
        $user = User::factory()->create([
            'name' => 'Budi Santoso',
            'email' => 'budi@contoh.test',
        ]);
        $this->akunTerisi($user);

        $json = json_encode(app(BackupService::class)->export($user));

        $this->assertStringNotContainsString('Budi Santoso', $json);
        $this->assertStringNotContainsString('budi@contoh.test', $json);
        $this->assertStringNotContainsString($user->password, $json);
    }

    /**
     * ID asli tidak boleh ikut. Ia akan bertabrakan begitu berkas dipulihkan
     * ke akun atau basis data lain; `ref` hanya berlaku di dalam berkas.
     */
    public function test_transaksi_menunjuk_rekening_lewat_ref_bukan_id(): void
    {
        $user = User::factory()->create();
        ['bank' => $bank] = $this->akunTerisi($user);

        $isi = app(BackupService::class)->export($user);

        $this->assertArrayNotHasKey('id', $isi['accounts'][0]);
        $this->assertArrayHasKey('ref', $isi['accounts'][0]);
        $this->assertSame(1, $isi['transactions'][0]['account_ref']);
        $this->assertNotSame($bank->id, $isi['transactions'][0]['account_ref']);
    }

    public function test_berkas_diunduh_dengan_nama_bertanggal(): void
    {
        $user = User::factory()->create();

        $respons = $this->actingAs($user)->get(route('data.download'));

        $respons->assertOk();
        $this->assertStringContainsString(
            'arus-cadangan-'.now(config('app.timezone'))->format('Y-m-d').'.json',
            $respons->headers->get('content-disposition'),
        );
    }

    // ── Pulang-pergi ────────────────────────────────────────────────────

    /**
     * INTI berkas ini: cadangan → pulihkan harus menghasilkan keadaan yang
     * sama persis. Diuji lewat angka turunannya (saldo, kekayaan bersih,
     * sisa utang), bukan hanya jumlah barisnya — baris yang lengkap tetapi
     * relasinya salah akan lolos dari hitungan baris.
     */
    public function test_memulihkan_cadangan_mengembalikan_keadaan_yang_sama(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $saldo = app(AccountBalanceService::class);
        $sebelum = [
            'aset' => $saldo->totalAssets($user),
            'bersih' => $saldo->netWorth($user),
            'utang' => array_sum($saldo->debtRemaining($user)),
        ];

        $cadangan = app(BackupService::class)->export($user);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasNoErrors();

        $user->refresh();

        $this->assertSame($sebelum['aset'], $saldo->totalAssets($user));
        $this->assertSame($sebelum['bersih'], $saldo->netWorth($user));
        $this->assertSame($sebelum['utang'], array_sum($saldo->debtRemaining($user)));
        $this->assertSame(5_000_000.0, (float) $user->goals()->first()->allocated_amount);
        $this->assertSame(15_000_000.0, (float) $user->budget->planned_income);
    }

    /**
     * MENGGANTI, bukan menggabungkan. Memulihkan cadangan yang sama dua kali
     * tidak boleh menggandakan apa pun — kalau iya, saldonya membengkak tanpa
     * ada yang tahu asal-usulnya.
     */
    public function test_memulihkan_dua_kali_tidak_menggandakan_data(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $cadangan = app(BackupService::class)->export($user);

        foreach (range(1, 2) as $kali) {
            $this->actingAs($user)
                ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
                ->assertSessionHasNoErrors();
        }

        $this->assertSame(2, $user->accounts()->count());
        $this->assertSame(3, $user->transactions()->count());
        $this->assertSame(1, $user->goals()->count());
        $this->assertSame(1, $user->debts()->count());
        $this->assertSame(1, $user->calendarNotes()->count());
        $this->assertSame(1, $user->reminders()->count());
    }

    public function test_memulihkan_mengganti_data_lama_yang_berbeda(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $cadangan = app(BackupService::class)->export($user);

        // Keadaan berubah setelah cadangan dibuat.
        $user->accounts()->create([
            'name' => 'Rekening baru', 'kind' => AccountKind::Cash->value,
            'opening_balance' => 1_000_000,
        ]);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)]);

        $this->assertSame(2, $user->accounts()->count());
        $this->assertDatabaseMissing('accounts', ['name' => 'Rekening baru']);
    }

    // ── Berkas yang ditolak ─────────────────────────────────────────────

    public function test_berkas_bukan_json_ditolak(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $jalur = tempnam(sys_get_temp_dir(), 'uji-rusak-').'.json';
        file_put_contents($jalur, 'ini bukan json');

        $this->actingAs($user)
            ->post(route('data.restore'), [
                'berkas' => new UploadedFile($jalur, 'rusak.json', 'application/json', null, true),
            ])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(2, $user->accounts()->count());
    }

    public function test_berkas_tanpa_penanda_versi_ditolak(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('data.restore'), [
                'berkas' => $this->berkas(['accounts' => [], 'debts' => [], 'transactions' => [], 'goals' => []]),
            ])
            ->assertSessionHasErrors('berkas');
    }

    public function test_versi_yang_tidak_dikenal_ditolak(): void
    {
        $user = User::factory()->create();
        $cadangan = app(BackupService::class)->export($user);
        $cadangan['arus_backup_version'] = 99;

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasErrors('berkas');
    }

    /**
     * Berkas disunting tangan bisa berisi nilai mustahil. Ia tetap harus
     * divalidasi selengkap masukan formulir — "berasal dari kami" bukan
     * alasan untuk memercayainya.
     */
    public function test_nilai_tidak_masuk_akal_di_dalam_berkas_ditolak(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $asli = app(BackupService::class)->export($user);

        $rusak = $asli;
        $rusak['accounts'][0]['kind'] = 'kripto';

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($rusak)])
            ->assertSessionHasErrors('berkas');

        // Dan data lamanya tetap utuh.
        $this->assertSame(2, $user->accounts()->count());
        $this->assertSame(3, $user->transactions()->count());
    }

    public function test_ref_yang_menunjuk_rekening_tak_ada_ditolak(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $rusak = app(BackupService::class)->export($user);
        $rusak['transactions'][0]['account_ref'] = 99;

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($rusak)])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(3, $user->transactions()->count());
    }

    /**
     * Berkas yang BENTUKNYA sah masih bisa melanggar aturan keuangan. Alokasi
     * target yang melebihi saldo rekeningnya harus ditolak utuh, bukan
     * tersimpan lalu menghasilkan angka mustahil di layar.
     */
    public function test_berkas_yang_melanggar_aturan_keuangan_ditolak_utuh(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $rusak = app(BackupService::class)->export($user);
        $rusak['goals'][0]['allocated_amount'] = 50_000_000;

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($rusak)])
            ->assertSessionHasErrors('berkas');

        $this->assertSame(5_000_000.0, (float) $user->goals()->first()->allocated_amount);
        $this->assertSame(2, $user->accounts()->count());
    }

    // ── Otorisasi ───────────────────────────────────────────────────────

    public function test_pemulihan_hanya_menyentuh_data_sendiri(): void
    {
        $orangLain = User::factory()->create();
        $this->akunTerisi($orangLain);

        $saya = User::factory()->create();
        $this->actingAs($saya)->post(route('data.restore'), [
            'berkas' => $this->berkas(app(BackupService::class)->export($saya)),
        ]);

        $this->assertSame(2, $orangLain->accounts()->count());
        $this->assertSame(3, $orangLain->transactions()->count());
    }

    public function test_tamu_tidak_bisa_mengunduh_maupun_memulihkan(): void
    {
        $this->get(route('data.download'))->assertRedirect(route('login'));
        $this->post(route('data.restore'))->assertRedirect(route('login'));
    }
}
