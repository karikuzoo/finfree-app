<?php

namespace Tests\Feature;

use App\Models\Reminder;
use App\Models\User;
use App\Services\DashboardSummaryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Tests\TestCase;

class ReminderTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        Carbon::setTestNow();

        parent::tearDown();
    }

    // ── Membuat ─────────────────────────────────────────────────────────

    public function test_pengingat_dapat_dibuat(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('reminders.store'), [
                'title' => 'Setor rutin Dana Darurat',
                'remind_date' => '2026-09-05',
                'remind_time' => '09:00',
            ])
            ->assertSessionHasNoErrors();

        $pengingat = Reminder::sole();

        $this->assertSame($user->id, $pengingat->user_id);
        $this->assertSame('Setor rutin Dana Darurat', $pengingat->title);
        $this->assertSame('2026-09-05 09:00:00', $pengingat->remind_at->toDateTimeString());
        $this->assertNull($pengingat->completed_at);
    }

    /**
     * Beda dari catatan yang satu per tanggal, satu tanggal boleh punya banyak
     * pengingat dengan jam masing-masing.
     */
    public function test_satu_tanggal_boleh_punya_banyak_pengingat(): void
    {
        $user = User::factory()->create();

        foreach (['09:00', '20:00'] as $jam) {
            $this->actingAs($user)->post(route('reminders.store'), [
                'title' => "Pengingat {$jam}",
                'remind_date' => '2026-09-05',
                'remind_time' => $jam,
            ]);
        }

        $this->assertSame(2, Reminder::count());
    }

    public function test_menolak_masukan_yang_tidak_sah(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('reminders.store'), [
                'title' => '',
                'remind_date' => '2026-09-05',
                'remind_time' => '09:00',
            ])
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->post(route('reminders.store'), [
                'title' => 'Coba',
                'remind_date' => '2026-09-05',
                'remind_time' => 'pagi-pagi',
            ])
            ->assertSessionHasErrors('remind_time');

        $this->assertSame(0, Reminder::count());
    }

    public function test_tamu_tidak_bisa_membuat_pengingat(): void
    {
        $this->post(route('reminders.store'), [
            'title' => 'Coba',
            'remind_date' => '2026-09-05',
            'remind_time' => '09:00',
        ])->assertRedirect(route('login'));
    }

    // ── Mengubah ────────────────────────────────────────────────────────

    public function test_judul_dan_jam_dapat_diubah(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Cek harga emas',
            'remind_at' => '2026-09-05 20:00:00',
        ]);

        $this->actingAs($user)
            ->patch(route('reminders.update', $pengingat), [
                'title' => 'Cek harga emas Antam',
                'remind_time' => '07:30',
            ])
            ->assertSessionHasNoErrors();

        $pengingat->refresh();

        $this->assertSame('Cek harga emas Antam', $pengingat->title);
        $this->assertSame('2026-09-05 07:30:00', $pengingat->remind_at->toDateTimeString());
    }

    /**
     * TANGGALNYA tidak ikut berubah meski dikirim. Pengingat disunting dari
     * dialog tanggal; memindahkannya ke hari lain membuat barisnya lenyap dari
     * dialog yang sedang terbuka.
     */
    public function test_tanggal_pengingat_tidak_ikut_berubah(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Coba',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $this->actingAs($user)->patch(route('reminders.update', $pengingat), [
            'title' => 'Coba',
            'remind_time' => '10:00',
            'remind_date' => '2026-10-01',
        ]);

        $this->assertSame('2026-09-05', $pengingat->fresh()->remind_at->toDateString());
    }

    public function test_menolak_perubahan_yang_tidak_sah(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Coba',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $this->actingAs($user)
            ->patch(route('reminders.update', $pengingat), ['title' => '', 'remind_time' => '09:00'])
            ->assertSessionHasErrors('title');

        $this->actingAs($user)
            ->patch(route('reminders.update', $pengingat), ['title' => 'Coba', 'remind_time' => 'pagi'])
            ->assertSessionHasErrors('remind_time');

        $this->assertSame('Coba', $pengingat->fresh()->title);
    }

    public function test_tidak_bisa_mengubah_pengingat_orang_lain(): void
    {
        $pengingat = User::factory()->create()->reminders()->create([
            'title' => 'Rahasia',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $this->actingAs(User::factory()->create())
            ->patch(route('reminders.update', $pengingat), [
                'title' => 'Dibajak',
                'remind_time' => '01:00',
            ])
            ->assertForbidden();

        $this->assertSame('Rahasia', $pengingat->fresh()->title);
    }

    /**
     * Menandai selesai punya jalurnya sendiri sejak PATCH pada rute induk
     * dipakai untuk menyunting. Nama rutenya tidak berubah, jadi frontend
     * tidak perlu disesuaikan — test ini memastikan keduanya memang terpisah.
     */
    public function test_menandai_selesai_dan_menyunting_adalah_dua_jalur_berbeda(): void
    {
        $this->assertNotSame(
            route('reminders.toggle', 1),
            route('reminders.update', 1),
        );
    }

    // ── Menandai selesai ────────────────────────────────────────────────

    public function test_pengingat_dapat_ditandai_selesai_dan_dibatalkan(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Cek harga emas',
            'remind_at' => '2026-09-05 20:00:00',
        ]);

        $this->actingAs($user)->patch(route('reminders.toggle', $pengingat));
        $this->assertNotNull($pengingat->fresh()->completed_at);

        $this->actingAs($user)->patch(route('reminders.toggle', $pengingat));
        $this->assertNull($pengingat->fresh()->completed_at);
    }

    /**
     * Ditandai selesai, BUKAN dihapus — supaya kalender bulan lalu tetap
     * memperlihatkan apa yang sudah dikerjakan.
     */
    public function test_menandai_selesai_tidak_menghapus_barisnya(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Cek harga emas',
            'remind_at' => '2026-09-05 20:00:00',
        ]);

        $this->actingAs($user)->patch(route('reminders.toggle', $pengingat));

        $this->assertSame(1, Reminder::count());
    }

    public function test_pengingat_dapat_dihapus(): void
    {
        $user = User::factory()->create();
        $pengingat = $user->reminders()->create([
            'title' => 'Coba',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $this->actingAs($user)->delete(route('reminders.destroy', $pengingat));

        $this->assertSame(0, Reminder::count());
    }

    // ── Kepemilikan ─────────────────────────────────────────────────────

    /**
     * Kebocoran data antar pengguna adalah kesalahan yang paling sulit
     * dimaafkan pada aplikasi keuangan (CONTRIBUTING §7).
     */
    public function test_tidak_bisa_menyentuh_pengingat_milik_orang_lain(): void
    {
        $pemilik = User::factory()->create();
        $orangLain = User::factory()->create();

        $pengingat = $pemilik->reminders()->create([
            'title' => 'Rahasia',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $this->actingAs($orangLain)
            ->patch(route('reminders.toggle', $pengingat))
            ->assertForbidden();

        $this->actingAs($orangLain)
            ->delete(route('reminders.destroy', $pengingat))
            ->assertForbidden();

        $this->assertSame(1, Reminder::count());
        $this->assertNull($pengingat->fresh()->completed_at);
    }

    // ── Tampil di kalender & dashboard ──────────────────────────────────

    public function test_kalender_hanya_memuat_pengingat_bulan_yang_diminta(): void
    {
        $user = User::factory()->create();

        foreach (['2026-08-30 09:00:00', '2026-09-05 09:00:00', '2026-10-01 09:00:00'] as $waktu) {
            $user->reminders()->create(['title' => "Pengingat {$waktu}", 'remind_at' => $waktu]);
        }

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-09-01'));

        $this->assertCount(1, $kalender['reminders']);
        $this->assertSame('2026-09-05', $kalender['reminders'][0]['date']);
        $this->assertSame('09:00', $kalender['reminders'][0]['time']);
    }

    public function test_kalender_tidak_memuat_pengingat_pengguna_lain(): void
    {
        $user = User::factory()->create();
        User::factory()->create()->reminders()->create([
            'title' => 'Punya orang lain',
            'remind_at' => '2026-09-05 09:00:00',
        ]);

        $kalender = app(DashboardSummaryService::class)
            ->calendarForMonth($user, Carbon::parse('2026-09-01'));

        $this->assertEmpty($kalender['reminders']);
    }

    public function test_panel_hari_ini_hanya_memuat_pengingat_hari_ini(): void
    {
        Carbon::setTestNow('2026-09-05 12:00:00');

        $user = User::factory()->create();
        $user->reminders()->create(['title' => 'Kemarin', 'remind_at' => '2026-09-04 09:00:00']);
        $user->reminders()->create(['title' => 'Pagi ini', 'remind_at' => '2026-09-05 09:00:00']);
        $user->reminders()->create(['title' => 'Nanti malam', 'remind_at' => '2026-09-05 20:00:00']);
        $user->reminders()->create(['title' => 'Besok', 'remind_at' => '2026-09-06 09:00:00']);

        $hariIni = app(DashboardSummaryService::class)->remindersForToday($user);

        $this->assertCount(2, $hariIni);
        $this->assertSame('Pagi ini', $hariIni[0]['title']);
        $this->assertSame('Nanti malam', $hariIni[1]['title']);
    }

    /**
     * Jam yang sudah lewat tanpa ditandai selesai perlu ditonjolkan, jadi
     * penanda `past` harus benar — bukan sekadar ada.
     */
    public function test_menandai_pengingat_yang_jamnya_sudah_lewat(): void
    {
        Carbon::setTestNow('2026-09-05 12:00:00');

        $user = User::factory()->create();
        $user->reminders()->create(['title' => 'Pagi ini', 'remind_at' => '2026-09-05 09:00:00']);
        $user->reminders()->create(['title' => 'Nanti malam', 'remind_at' => '2026-09-05 20:00:00']);

        $hariIni = app(DashboardSummaryService::class)->remindersForToday($user);

        $this->assertTrue($hariIni[0]['past'], 'Pengingat pukul 09:00 seharusnya sudah lewat pada pukul 12:00.');
        $this->assertFalse($hariIni[1]['past'], 'Pengingat pukul 20:00 belum lewat pada pukul 12:00.');
    }

    public function test_dashboard_mengirim_pengingat_hari_ini(): void
    {
        Carbon::setTestNow('2026-09-05 08:00:00');

        $user = User::factory()->create();
        $user->reminders()->create(['title' => 'Setor rutin', 'remind_at' => '2026-09-05 09:00:00']);

        $this->actingAs($user)
            ->get(route('dashboard'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->has('todayReminders', 1)
                ->where('todayReminders.0.title', 'Setor rutin'));
    }

    /**
     * Menghapus akun harus menghapus seluruh jejaknya (FR-37, UU 27/2022 PDP).
     */
    public function test_pengingat_ikut_terhapus_saat_akun_dihapus(): void
    {
        $user = User::factory()->create();
        $user->reminders()->create(['title' => 'Coba', 'remind_at' => '2026-09-05 09:00:00']);

        $user->delete();

        $this->assertSame(0, Reminder::count());
    }
}
