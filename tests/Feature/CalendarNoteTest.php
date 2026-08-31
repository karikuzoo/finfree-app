<?php

namespace Tests\Feature;

use App\Models\CalendarNote;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class CalendarNoteTest extends TestCase
{
    use RefreshDatabase;

    public function test_catatan_dapat_disimpan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('calendar-notes.store'), [
                'note_date' => '2026-08-17',
                'body' => 'Gajian, sisihkan untuk dana darurat.',
            ])
            ->assertSessionHasNoErrors();

        $this->assertDatabaseHas('calendar_notes', [
            'user_id' => $user->id,
            'body' => 'Gajian, sisihkan untuk dana darurat.',
        ]);
    }

    public function test_menyimpan_dua_kali_di_tanggal_sama_memperbarui_bukan_menggandakan(): void
    {
        $user = User::factory()->create();

        foreach (['Catatan awal', 'Catatan yang sudah diperbaiki'] as $isi) {
            $this->actingAs($user)->post(route('calendar-notes.store'), [
                'note_date' => '2026-08-17',
                'body' => $isi,
            ]);
        }

        $this->assertSame(1, CalendarNote::count());
        $this->assertSame(
            'Catatan yang sudah diperbaiki',
            CalendarNote::first()->body,
        );
    }

    public function test_catatan_dapat_dihapus(): void
    {
        $user = User::factory()->create();
        $note = CalendarNote::create([
            'user_id' => $user->id,
            'note_date' => '2026-08-17',
            'body' => 'Coba',
        ]);

        $this->actingAs($user)
            ->delete(route('calendar-notes.destroy', $note))
            ->assertRedirect();

        $this->assertSame(0, CalendarNote::count());
    }

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tidak_bisa_menghapus_catatan_milik_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $orangLain = User::factory()->create();

        $note = CalendarNote::create([
            'user_id' => $pemilik->id,
            'note_date' => '2026-08-17',
            'body' => 'Rahasia',
        ]);

        $this->actingAs($orangLain)
            ->delete(route('calendar-notes.destroy', $note))
            ->assertForbidden();

        $this->assertSame(1, CalendarNote::count());
    }

    public function test_menyimpan_di_tanggal_sama_tidak_menimpa_catatan_orang_lain(): void
    {
        $a = User::factory()->create();
        $b = User::factory()->create();

        $this->actingAs($a)->post(route('calendar-notes.store'), [
            'note_date' => '2026-08-17',
            'body' => 'Punya A',
        ]);
        $this->actingAs($b)->post(route('calendar-notes.store'), [
            'note_date' => '2026-08-17',
            'body' => 'Punya B',
        ]);

        $this->assertSame(2, CalendarNote::count());
        $this->assertSame('Punya A', CalendarNote::where('user_id', $a->id)->first()->body);
    }

    public function test_menolak_catatan_kosong_dan_terlalu_panjang(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('calendar-notes.store'), ['note_date' => '2026-08-17', 'body' => ''])
            ->assertSessionHasErrors('body');

        $this->actingAs($user)
            ->post(route('calendar-notes.store'), [
                'note_date' => '2026-08-17',
                'body' => str_repeat('a', 501),
            ])
            ->assertSessionHasErrors('body');
    }

    public function test_tamu_tidak_bisa_menyimpan_catatan(): void
    {
        $this->post(route('calendar-notes.store'), [
            'note_date' => '2026-08-17',
            'body' => 'Coba',
        ])->assertRedirect(route('login'));
    }

    // ── Data kalender per bulan ─────────────────────────────────────────

    public function test_kalender_hanya_memuat_catatan_bulan_yang_diminta(): void
    {
        $user = User::factory()->create();

        foreach (['2026-07-05', '2026-08-10', '2026-09-01'] as $tanggal) {
            CalendarNote::create([
                'user_id' => $user->id,
                'note_date' => $tanggal,
                'body' => 'Catatan '.$tanggal,
            ]);
        }

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-08-01'));

        $this->assertSame('2026-08', $kalender['month']);
        $this->assertCount(1, $kalender['notes']);
        $this->assertSame('2026-08-10', $kalender['notes'][0]['date']);
    }

    public function test_kalender_tidak_memuat_catatan_pengguna_lain(): void
    {
        $user = User::factory()->create();
        CalendarNote::create([
            'user_id' => User::factory()->create()->id,
            'note_date' => '2026-08-10',
            'body' => 'Punya orang lain',
        ]);

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-08-01'));

        $this->assertEmpty($kalender['notes']);
    }

    public function test_dashboard_menerima_parameter_bulan(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('dashboard', ['bulan' => '2026-05']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('calendar.month', '2026-05'));
    }

    public function test_dashboard_menolak_format_bulan_yang_salah(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('dashboard', ['bulan' => 'agustus']))
            ->assertSessionHasErrors('bulan');
    }

    /**
     * Waktunya WAJIB dibekukan di tanggal 31.
     *
     * PHP mengisi bagian tanggal yang tidak disebutkan dari hari ini. Jadi bila
     * '2026-06' diurai dengan format 'Y-m' pada tanggal 31, hasilnya "31 Juni"
     * — tanggal yang tidak ada — yang meluber menjadi 1 Juli. Pengguna meminta
     * Juni, kalender menampilkan Juli.
     *
     * Tanpa setTestNow, test ini lolos pada 28 dari 31 hari dalam sebulan dan
     * hanya gagal di akhir bulan. Bug aslinya memang ditemukan pengguna, bukan
     * oleh pengujian, persis karena alasan itu.
     */
    public function test_bulan_tidak_meleset_ketika_diakses_pada_tanggal_31(): void
    {
        Carbon::setTestNow('2026-08-31 14:00:00');

        $user = User::factory()->create();

        // Bulan-bulan berisi 30 hari — justru inilah yang dulu meluber.
        foreach (['2026-06' => 'Juni', '2026-04' => 'April', '2026-09' => 'September'] as $param => $nama) {
            $this->actingAs($user)
                ->get(route('dashboard', ['bulan' => $param]))
                ->assertOk()
                ->assertInertia(fn ($page) => $page->where('calendar.month', $param));
        }

        // Februari adalah kasus paling ekstrem: 31 Februari meluber dua hari
        // penuh ke bulan Maret.
        $this->actingAs($user)
            ->get(route('dashboard', ['bulan' => '2026-02']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page->where('calendar.month', '2026-02'));

        Carbon::setTestNow();
    }

    /**
     * Setoran harus muncul di bulan yang diminta, bukan sekadar labelnya yang
     * benar. Kalau rentang tanggalnya meleset satu bulan, data yang tampil pun
     * milik bulan yang salah.
     */
    public function test_data_bulan_lain_benar_benar_terambil_pada_tanggal_31(): void
    {
        Carbon::setTestNow('2026-08-31 14:00:00');

        $user = User::factory()->create();
        CalendarNote::create([
            'user_id' => $user->id,
            'note_date' => '2026-06-15',
            'body' => 'Catatan bulan Juni',
        ]);

        $this->actingAs($user)
            ->get(route('dashboard', ['bulan' => '2026-06']))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('calendar.month', '2026-06')
                ->where('calendar.notes.0.body', 'Catatan bulan Juni'));

        Carbon::setTestNow();
    }


    /**
     * Catatan yang ditulis di form "Catat Setoran" harus ikut terlihat di
     * kalender. Sebelum ini ia tersimpan di goal_contributions.note lalu tidak
     * pernah muncul lagi di mana pun — kalender hanya menampilkan totalnya.
     */
    public function test_setoran_muncul_di_tanggalnya_lengkap_dengan_catatan(): void
    {
        $user = User::factory()->create();
        $goal = $user->goals()->create([
            'type' => 'house',
            'name' => 'DP Rumah',
            'target_amount' => 200000000,
            'initial_amount' => 0,
            'estimated_return_rate' => 7,
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(
            route('goals.contributions.store', $goal->id),
            [
                'amount' => 1500000,
                'contributed_on' => '2026-08-03',
                'note' => 'Bonus tahunan',
            ],
        )->assertSessionHasNoErrors();

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-08-01'));

        $hari = collect($kalender['contributions'])->firstWhere('date', '2026-08-03');

        $this->assertNotNull($hari, 'Setoran tidak muncul di tanggal yang dipilih.');
        $this->assertSame(1500000.0, $hari['amount']);
        $this->assertSame('Bonus tahunan', $hari['entries'][0]['note']);
        $this->assertSame('DP Rumah', $hari['entries'][0]['goal']);
    }

    public function test_beberapa_setoran_di_tanggal_sama_dirinci_satu_per_satu(): void
    {
        $user = User::factory()->create();
        $goal = $user->goals()->create([
            'type' => 'emergency',
            'name' => 'Dana Darurat',
            'target_amount' => 60000000,
            'initial_amount' => 0,
            'estimated_return_rate' => 4,
            'status' => 'active',
        ]);

        $this->actingAs($user)->post(route('goals.contributions.store', $goal->id), [
            'amount' => 500000,
            'contributed_on' => '2026-08-03',
            'note' => 'Pertama',
        ]);

        $this->actingAs($user)->post(route('goals.contributions.store', $goal->id), [
            'amount' => 750000,
            'contributed_on' => '2026-08-03',
        ]);

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-08-01'));
        $hari = collect($kalender['contributions'])->firstWhere('date', '2026-08-03');

        $this->assertSame(1250000.0, $hari['amount']);
        $this->assertCount(2, $hari['entries']);
        $this->assertSame('Pertama', $hari['entries'][0]['note']);
        $this->assertNull($hari['entries'][1]['note']);
    }
}
