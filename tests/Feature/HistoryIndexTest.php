<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Models\Account;
use App\Models\Transaction;
use App\Models\User;
use App\Models\UserActivity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class HistoryIndexTest extends TestCase
{
    use RefreshDatabase;

    private function catatAktivitas(User $user, array $ubah = []): UserActivity
    {
        return $user->activities()->create(array_merge([
            'type' => 'contribution_recorded',
            'goal_name' => 'Dana Darurat',
            'amount' => 50000,
        ], $ubah));
    }

    public function test_tamu_tidak_bisa_membuka_riwayat(): void
    {
        $this->get(route('history.index'))->assertRedirect(route('login'));
    }

    public function test_kosong_saat_belum_ada_aktivitas(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('history.index'))
            ->assertOk()
            ->assertInertia(fn ($page) => $page
                ->where('activities.data', []));
    }

    public function test_hanya_menampilkan_aktivitas_milik_pengguna_sendiri(): void
    {
        $user = User::factory()->create();
        $lainnya = User::factory()->create();

        $this->catatAktivitas($user, ['goal_name' => 'Punya Saya']);
        $this->catatAktivitas($lainnya, ['goal_name' => 'Punya Orang Lain']);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->has('activities.data', 1)
                ->where('activities.data.0.label', 'Punya Saya'));
    }

    public function test_terurut_dari_yang_paling_baru(): void
    {
        $user = User::factory()->create();

        $lama = $this->catatAktivitas($user, ['goal_name' => 'Lebih Dulu']);
        $lama->created_at = now()->subDays(2);
        $lama->save();

        $baru = $this->catatAktivitas($user, ['goal_name' => 'Belakangan']);
        $baru->created_at = now();
        $baru->save();

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->where('activities.data.0.label', 'Belakangan')
                ->where('activities.data.1.label', 'Lebih Dulu'));
    }

    public function test_dipaginasi_20_per_halaman(): void
    {
        $user = User::factory()->create();

        for ($i = 0; $i < 25; $i++) {
            $this->catatAktivitas($user);
        }

        $halamanSatu = $this->actingAs($user)
            ->get(route('history.index'))
            ->viewData('page')['props']['activities'];

        $this->assertCount(20, $halamanSatu['data']);
        $this->assertSame(1, $halamanSatu['current_page']);
        $this->assertSame(2, $halamanSatu['last_page']);
        $this->assertNotNull($halamanSatu['next_page_url']);
        $this->assertNull($halamanSatu['prev_page_url']);

        $halamanDua = $this->actingAs($user)
            ->get(route('history.index', ['page' => 2]))
            ->viewData('page')['props']['activities'];

        $this->assertCount(5, $halamanDua['data']);
        $this->assertNotNull($halamanDua['prev_page_url']);
    }

    public function test_bentuk_data_tiap_aktivitas(): void
    {
        $user = User::factory()->create();
        $this->catatAktivitas($user, [
            'type' => 'contribution_recorded',
            'goal_name' => 'Dana Darurat',
            'amount' => 75000,
        ]);

        $this->actingAs($user)
            ->get(route('history.index'))
            ->assertInertia(fn ($page) => $page
                ->where('activities.data.0.type', 'contribution_recorded')
                ->where('activities.data.0.label', 'Dana Darurat')
                ->where('activities.data.0.amount', 75000)
                ->has('activities.data.0.occurred_at'));
    }

    // ── Transaksi ikut masuk riwayat ────────────────────────────────────

    private function rekening(User $user): Account
    {
        return Account::factory()->for($user)->jenis(AccountKind::Bank)
            ->create(["opening_balance" => 20_000_000]);
    }

    /**
     * Transaksi TIDAK disalin ke user_activities; riwayat menggabungkannya
     * saat dibaca. Test ini yang membuktikan penggabungan itu benar-benar
     * terjadi — tanpa satu baris pun ditulis ke tabel aktivitas.
     */
    public function test_transaksi_muncul_di_riwayat_tanpa_disalin(): void
    {
        $user = User::factory()->create();
        Transaction::factory()->for($user)->for($this->rekening($user))
            ->pengeluaran(850_000)->create(["name" => "Makan & kopi"]);

        $this->assertSame(0, UserActivity::count());

        $this->actingAs($user)
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page
                ->has("activities.data", 1)
                ->where("activities.data.0.type", "transaction:expense")
                ->where("activities.data.0.label", "Makan & kopi")
                ->where("activities.data.0.amount", 850_000));
    }

    public function test_riwayat_memuat_transaksi_dan_peristiwa_tujuan_sekaligus(): void
    {
        $user = User::factory()->create();
        $this->catatAktivitas($user, ["type" => "goal_created", "goal_name" => "DP Rumah", "amount" => null]);
        Transaction::factory()->for($user)->for($this->rekening($user))
            ->pemasukan(5_000_000)->create(["name" => "Gaji"]);

        $this->actingAs($user)
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page->has("activities.data", 2));
    }

    /**
     * Menyunting transaksi harus ikut memperbaiki riwayatnya. Inilah yang
     * tidak akan terjadi bila barisnya disalin saat dicatat.
     */
    public function test_menyunting_transaksi_ikut_memperbaiki_riwayat(): void
    {
        $user = User::factory()->create();
        $transaksi = Transaction::factory()->for($user)->for($this->rekening($user))
            ->pengeluaran(100_000)->create(["name" => "Salah ketik"]);

        $transaksi->update(["name" => "Sudah benar", "amount" => 250_000]);

        $this->actingAs($user)
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page
                ->where("activities.data.0.label", "Sudah benar")
                ->where("activities.data.0.amount", 250_000));
    }

    public function test_menghapus_transaksi_menghapusnya_dari_riwayat(): void
    {
        $user = User::factory()->create();
        $transaksi = Transaction::factory()->for($user)->for($this->rekening($user))
            ->pengeluaran(100_000)->create();

        $transaksi->delete();

        $this->actingAs($user)
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page->has("activities.data", 0));
    }

    /**
     * Transaksi bertanggal mundur tetap tercatat sebagai kegiatan HARI INI —
     * riwayat ini catatan perbuatan, bukan buku besar. Tanggal transaksinya
     * dikirim terpisah supaya bisa disebut bila berbeda.
     */
    public function test_tanggal_transaksi_dikirim_terpisah_dari_waktu_pencatatan(): void
    {
        $user = User::factory()->create();
        $kemarin = now(config("app.timezone"))->subDays(3)->toDateString();

        Transaction::factory()->for($user)->for($this->rekening($user))
            ->pengeluaran(100_000)->pada($kemarin)->create();

        $this->actingAs($user)
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page
                ->where("activities.data.0.occurred_on", $kemarin)
                ->has("activities.data.0.occurred_at"));
    }

    public function test_transaksi_orang_lain_tidak_muncul(): void
    {
        $orangLain = User::factory()->create();
        Transaction::factory()->for($orangLain)->for($this->rekening($orangLain))
            ->pengeluaran(100_000)->create();

        $this->actingAs(User::factory()->create())
            ->get(route("history.index"))
            ->assertInertia(fn ($page) => $page->has("activities.data", 0));
    }
}