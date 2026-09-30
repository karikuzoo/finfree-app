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
use App\Services\SavingsPlanService;
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

    /** FR-51: berat emas ikut dicadangkan dan pulih. */
    public function test_jumlah_satuan_aset_ikut_pulih(): void
    {
        $user = User::factory()->create();
        Account::factory()->for($user)->jenis(AccountKind::Gold)
            ->create(['name' => 'Emas', 'opening_balance' => 14_500_000, 'units' => 10.5]);

        $cadangan = app(BackupService::class)->export($user);
        $this->assertSame(10.5, $cadangan['accounts'][0]['units']);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasNoErrors();

        $this->assertSame(10.5, (float) $user->accounts()->sole()->units);
    }

    // ── Penyisihan ("Sudah saya sisihkan") ──────────────────────────────

    private function sudahBulanIni(User $user): float
    {
        return app(SavingsPlanService::class)->forUser($user->fresh())['rows'][0]['set_aside_this_month'];
    }

    private function pulihkan(User $user, array $cadangan): void
    {
        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasNoErrors();
    }

    /**
     * Bug yang melahirkan bagian ini: pemulihan membuat ulang tujuan dengan ID
     * baru, catatan penyisihannya terlepas, dan rencana menabung mengira
     * bulan ini belum disisihkan sama sekali — lalu menyarankan menyisihkan
     * lagi dari awal.
     */
    public function test_yang_sudah_disisihkan_bulan_ini_bertahan_setelah_pemulihan(): void
    {
        $user = User::factory()->create();
        ['goal' => $goal] = $this->akunTerisi($user);

        $this->actingAs($user)
            ->post(route('goals.set-aside', $goal), ['amount' => 1_000_000])
            ->assertSessionHasNoErrors();

        $this->assertSame(1_000_000.0, $this->sudahBulanIni($user));

        $this->pulihkan($user, app(BackupService::class)->export($user));

        $this->assertSame(1_000_000.0, $this->sudahBulanIni($user));
        $this->assertSame(6_000_000.0, (float) $user->goals()->first()->allocated_amount);
    }

    /** Waktunya ikut pulih — penyisihan bulan lalu tidak pindah ke bulan ini. */
    public function test_penyisihan_bulan_lalu_tetap_di_bulannya(): void
    {
        $user = User::factory()->create();
        ['goal' => $goal] = $this->akunTerisi($user);

        $bulanLalu = now(config('app.timezone'))->subMonthNoOverflow()->startOfMonth()->addDays(4);

        $user->activities()->make([
            'financial_goal_id' => $goal->id,
            'type' => 'goal_set_aside',
            'goal_name' => $goal->name,
            'amount' => 2_000_000,
        ])->forceFill(['created_at' => $bulanLalu])->save();

        $this->pulihkan($user, app(BackupService::class)->export($user));

        $this->assertSame(0.0, $this->sudahBulanIni($user));

        $pulih = $user->activities()->where('type', 'goal_set_aside')->sole();
        $this->assertSame($bulanLalu->format('Y-m-d H:i'), $pulih->created_at->format('Y-m-d H:i'));
        $this->assertSame($user->goals()->first()->id, $pulih->financial_goal_id);
    }

    /** Memulihkan berkas yang sama dua kali tidak menggandakan baris di Riwayat. */
    public function test_memulihkan_dua_kali_tidak_menggandakan_penyisihan(): void
    {
        $user = User::factory()->create();
        ['goal' => $goal] = $this->akunTerisi($user);

        $this->actingAs($user)->post(route('goals.set-aside', $goal), ['amount' => 1_000_000]);

        $cadangan = app(BackupService::class)->export($user);
        $this->pulihkan($user, $cadangan);
        $this->pulihkan($user, $cadangan);

        $this->assertSame(1, $user->activities()->where('type', 'goal_set_aside')->count());
        $this->assertSame(1_000_000.0, $this->sudahBulanIni($user));
    }

    /**
     * Berkas dari sebelum `set_asides` ada tetap bisa dipulihkan, dan catatan
     * penyisihan lama TIDAK dihapus tanpa pengganti — ia tetap terbaca di
     * Riwayat meski tautannya ke tujuan terlepas.
     */
    public function test_berkas_lama_tanpa_set_asides_tetap_diterima(): void
    {
        $user = User::factory()->create();
        ['goal' => $goal] = $this->akunTerisi($user);

        $this->actingAs($user)->post(route('goals.set-aside', $goal), ['amount' => 1_000_000]);

        $lama = app(BackupService::class)->export($user);
        foreach ($lama['goals'] as $i => $g) {
            unset($lama['goals'][$i]['set_asides']);
        }

        $this->pulihkan($user, $lama);

        $this->assertSame(1, $user->activities()->where('type', 'goal_set_aside')->count());
    }

    /**
     * Penyisihan untuk tujuan yang SUDAH dihapus pengguna sebelumnya bukan
     * bagian dari pemulihan — ia tidak ikut terhapus.
     */
    public function test_penyisihan_tujuan_yang_sudah_dihapus_tidak_ikut_hilang(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $user->activities()->create([
            'financial_goal_id' => null,
            'type' => 'goal_set_aside',
            'goal_name' => 'Liburan yang batal',
            'amount' => 750_000,
        ]);

        $this->pulihkan($user, app(BackupService::class)->export($user));

        $this->assertDatabaseHas('user_activities', [
            'user_id' => $user->id,
            'goal_name' => 'Liburan yang batal',
            'type' => 'goal_set_aside',
        ]);
    }

    public function test_nominal_penyisihan_tidak_masuk_akal_ditolak(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);

        $rusak = app(BackupService::class)->export($user);
        $rusak['goals'][0]['set_asides'] = [['amount' => -5, 'at' => now()->toIso8601String()]];

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($rusak)])
            ->assertSessionHasErrors('berkas');
    }

    // ── Temuan tinjauan 30 Sep 2026 ─────────────────────────────────────

    /**
     * Baris transaksi yang TIDAK mungkin dibuat lewat form, dalam dua
     * bentuk: sebagai baris berkas (ref) dan sebagai kiriman form (id).
     *
     * @return array<string, array{0: array, 1: callable}>
     */
    public static function transaksiMustahil(): array
    {
        return [
            'pengeluaran minus' => [['type' => 'expense', 'amount' => -5000], fn ($r) => []],
            'pemasukan nol' => [['type' => 'income', 'amount' => 0], fn ($r) => []],
            'transfer ke rekening yang sama' => [['type' => 'transfer', 'amount' => 1000, 'to_account_ref' => 'asal'], fn ($r) => ['to_account_id' => $r['bank']->id]],
            'transfer tanpa tujuan' => [['type' => 'transfer', 'amount' => 1000], fn ($r) => []],
            'pengeluaran terhubung utang' => [['type' => 'expense', 'amount' => 1000, 'debt_ref' => 'utang'], fn ($r) => ['debt_id' => $r['utang']->id]],
            'pemasukan dengan rekening tujuan' => [['type' => 'income', 'amount' => 1000, 'to_account_ref' => 'emas'], fn ($r) => ['to_account_id' => $r['emas']->id]],
            'pembayaran tanpa utang' => [['type' => 'payment', 'amount' => 1000], fn ($r) => []],
        ];
    }

    /**
     * Aturan per jenis transaksi ditulis dua kali — StoreTransactionRequest
     * untuk form, BackupService untuk berkas. Test ini menjaga keduanya
     * tetap sepadan: setiap kasus harus ditolak DI KEDUA TEMPAT. Dulu berkas
     * menerima semuanya, dan pengeluaran minus diam-diam menaikkan saldo.
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('transaksiMustahil')]
    public function test_transaksi_mustahil_ditolak_di_form_maupun_di_berkas(array $ubah, callable $formTambahan): void
    {
        $user = User::factory()->create();
        $r = $this->akunTerisi($user);
        $cadangan = app(BackupService::class)->export($user);

        // Ref di berkas: 'asal' = rekening asal baris itu, lainnya dicari
        // dari urutan ekspor (bank lalu emas, satu utang).
        $refBank = $cadangan['transactions'][0]['account_ref'];
        $peta = ['asal' => $refBank, 'emas' => $cadangan['accounts'][1]['ref'], 'utang' => $cadangan['debts'][0]['ref']];
        $baris = ['account_ref' => $refBank, 'name' => 'Uji', 'occurred_on' => now()->toDateString(), 'category' => null];
        foreach ($ubah as $k => $v) {
            $baris[$k] = in_array($k, ['to_account_ref', 'debt_ref'], true) ? $peta[$v] : $v;
        }
        $cadangan['transactions'][] = $baris;

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas($cadangan)])
            ->assertSessionHasErrors('berkas');
        $this->assertSame(3, $user->transactions()->count(), 'Berkas seharusnya ditolak utuh.');

        $form = array_merge([
            'account_id' => $r['bank']->id,
            'type' => $ubah['type'],
            'name' => 'Uji',
            'amount' => $ubah['amount'],
            'occurred_on' => now()->toDateString(),
        ], $formTambahan($r));

        $this->actingAs($user)->post(route('transactions.store'), $form)->assertSessionHasErrors();
        $this->assertSame(3, $user->transactions()->count(), 'Form seharusnya menolak juga.');
    }

    /**
     * Form mengizinkan judul pengingat sampai 200 karakter, tetapi berkas
     * dulu dibatasi 100 — cadangan resmi pengguna sendiri tidak bisa
     * dipulihkan, dan itu baru ketahuan saat pemulihan dibutuhkan.
     */
    public function test_pengingat_berjudul_panjang_tetap_bisa_dipulihkan(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $judul = str_repeat('Bayar cicilan rumah ', 10); // 200 karakter
        $user->reminders()->create(['title' => $judul, 'remind_at' => '2026-10-01 08:00:00']);

        $this->actingAs($user)
            ->post(route('data.restore'), ['berkas' => $this->berkas(app(BackupService::class)->export($user))])
            ->assertSessionHasNoErrors();

        $this->assertTrue($user->reminders()->where('title', $judul)->exists());
    }

    /**
     * Tiga cara berkas yang BENTUKNYA sah dulu berakhir 500 atau diam-diam
     * salah, kini ditolak dengan pesan: tanggal catatan ganda (unique di
     * basis data), ref rekening ganda (menimpa peta ref), dan nominal di atas
     * kapasitas kolom (numeric overflow).
     */
    public function test_berkas_dengan_duplikat_atau_nominal_raksasa_ditolak_bukan_500(): void
    {
        $user = User::factory()->create();
        $this->akunTerisi($user);
        $asli = app(BackupService::class)->export($user);

        $kasus = [
            'tanggal catatan ganda' => fn ($c) => [...$c, 'calendar_notes' => [...$c['calendar_notes'], $c['calendar_notes'][0]]],
            'ref rekening ganda' => function ($c) {
                $c['accounts'][1]['ref'] = $c['accounts'][0]['ref'];

                return $c;
            },
            'nominal raksasa' => function ($c) {
                $c['goals'][0]['initial_amount'] = 1e20;

                return $c;
            },
        ];

        foreach ($kasus as $nama => $ubah) {
            $this->actingAs($user)
                ->post(route('data.restore'), ['berkas' => $this->berkas($ubah($asli))])
                ->assertRedirect()
                ->assertSessionHasErrors('berkas');

            $this->assertSame(2, $user->accounts()->count(), "Data lama harus utuh ({$nama}).");
        }
    }
}
