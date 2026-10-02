<?php

namespace Tests\Feature;

use App\Enums\AccountKind;
use App\Enums\GoalStatus;
use App\Enums\GoalType;
use App\Models\Account;
use App\Models\Debt;
use App\Models\Transaction;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Routing\Route as RouteDefinition;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Permintaan yang terlihat di Inspect bisa ditiru siapa saja — curl,
 * Postman, atau mengedit isian di DevTools. Keamanan tidak boleh bergantung
 * pada "tidak terlihat"; yang dijaga di sini adalah server:
 *
 * 1. Respons halaman mana pun tidak memuat rahasia (hash kata sandi, token,
 *    kunci API, APP_KEY) maupun data milik pengguna LAIN.
 * 2. Permintaan manual ke data milik orang lain ditolak — di SETIAP route
 *    yang menerima ID. Daftar route dibaca otomatis; route baru yang
 *    menerima jenis ID yang belum dikenal membuat test ini gagal.
 * 3. SQL injection: kiriman berisi SQL tidak memicu error dan tidak
 *    mengubah data; kueri mentah tidak pernah disisipi variabel.
 *
 * Dibuat 30 Sep 2026 atas permintaan pengguna.
 */
class RequestSecurityTest extends TestCase
{
    use RefreshDatabase;

    private const KUNCI_API_UJI = 'KUNCI-NEWSDATA-UJI-JANGAN-BOCOR';

    private User $a;

    private User $b;

    /** @var array<string, int> ID data milik A, per nama parameter route. */
    private array $milikA = [];

    protected function setUp(): void
    {
        parent::setUp();

        config(['news.api_key' => self::KUNCI_API_UJI]);

        $this->a = User::factory()->create(['email' => 'pemilik-a@contoh.test', 'remember_token' => 'TOKENINGATA1234567890']);
        $this->b = User::factory()->create(['email' => 'rahasia-b@contoh.test', 'name' => 'Nama Rahasia B']);

        $this->milikA = $this->isiData($this->a, 'A');
        $this->isiData($this->b, 'Rahasia B');
    }

    /** @return array<string, int> */
    private function isiData(User $u, string $tanda): array
    {
        $bank = Account::factory()->for($u)->jenis(AccountKind::Bank)
            ->create(['name' => "Rekening {$tanda}", 'opening_balance' => 50_000_000]);
        $utang = Debt::factory()->for($u)->create(['name' => "Utang {$tanda}", 'principal' => 5_000_000]);
        $transaksi = Transaction::factory()->for($u)->for($bank)->pemasukan(1_000_000)
            ->create(['name' => "Transaksi {$tanda}"]);
        $tujuan = $u->goals()->create([
            'type' => GoalType::Custom->value,
            'name' => "Tujuan {$tanda}",
            'target_amount' => 100_000_000,
            'initial_amount' => 0,
            'target_date' => now()->addYears(3)->toDateString(),
            'estimated_return_rate' => 5,
            'estimated_inflation_rate' => 0,
            'status' => GoalStatus::Active->value,
        ]);
        $pengingat = $u->reminders()->create(['title' => "Pengingat {$tanda}", 'remind_at' => now()->addDay()]);
        $catatan = $u->calendarNotes()->create(['note_date' => now()->toDateString(), 'body' => "Catatan {$tanda}"]);
        $riwayat = $u->calculationHistories()->create([
            'calculator' => 'investment',
            'input' => ['monthly_contribution' => '1000000', 'months' => '12', 'annual_return_rate' => '5', 'tanda' => $tanda],
            'input_hash' => hash('sha256', $tanda),
            'summary' => ['final_value' => 12_000_000],
        ]);

        return [
            'account' => $bank->id,
            'debt' => $utang->id,
            'transaction' => $transaksi->id,
            'financialGoal' => $tujuan->id,
            'reminder' => $pengingat->id,
            'calendarNote' => $catatan->id,
            'calculationHistory' => $riwayat->id,
        ];
    }

    /** Seluruh baris milik A di tabel-tabel keuangannya — untuk dibandingkan sebelum/sesudah. */
    private function potretA(): string
    {
        $tabel = ['accounts', 'transactions', 'debts', 'financial_goals', 'reminders', 'calendar_notes', 'goal_calculations', 'budgets', 'calculation_histories'];
        $potret = [];

        foreach ($tabel as $t) {
            $kolom = $t === 'goal_calculations' ? 'financial_goal_id' : 'user_id';
            $nilai = $t === 'goal_calculations' ? $this->a->goals()->pluck('id') : [$this->a->id];
            $potret[$t] = DB::table($t)->whereIn($kolom, $nilai)->orderBy('id')->get()->toArray();
        }
        $potret['users'] = DB::table('users')->where('id', $this->a->id)->first();

        return json_encode($potret);
    }

    // ── 1. Tidak ada rahasia di respons ─────────────────────────────────

    public function test_tidak_ada_data_sensitif_di_halaman_mana_pun(): void
    {
        $rahasia = [
            'hash kata sandi A' => $this->a->getAuthPassword(),
            'remember_token A' => 'TOKENINGATA1234567890',
            'kunci API NewsData' => self::KUNCI_API_UJI,
            'APP_KEY' => config('app.key'),
            'email B' => 'rahasia-b@contoh.test',
            'nama B' => 'Nama Rahasia B',
            'data keuangan B' => 'Rahasia B',
        ];

        $dicek = 0;
        foreach (Route::getRoutes() as $route) {
            /** @var RouteDefinition $route */
            if (! in_array('GET', $route->methods(), true) || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            // Route tamu/verifikasi: pengguna terverifikasi dialihkan, tidak ada isi.
            if (in_array($route->getName(), ['verification.notice', 'verification.verify', 'password.confirm'], true)) {
                continue;
            }

            $parameter = [];
            foreach ($route->parameterNames() as $p) {
                $this->assertArrayHasKey($p, $this->milikA, "Route {$route->uri()} memakai parameter {{$p}} yang belum dikenal test ini.");
                $parameter[$p] = $this->milikA[$p];
            }

            $respons = $this->actingAs($this->a)->get($this->alamat($route, $parameter));
            $respons->assertSuccessful();
            $isi = $this->isiRespons($respons);

            foreach ($rahasia as $label => $nilai) {
                $this->assertStringNotContainsString($nilai, $isi, "{$route->uri()} memuat {$label}.");
            }
            // Nama kunci yang tidak boleh ada di props mana pun. Diperiksa pada
            // props yang sudah diurai, bukan teks mentah: daftar route Ziggy di
            // HTML memuat "uri":"password" (alamat ganti kata sandi), yang bukan data.
            foreach ($this->kunciProps($isi) as $kunci) {
                $this->assertNotContains($kunci, ['password', 'remember_token', 'api_key', 'password_hash'], "{$route->uri()} memuat kunci props {$kunci}.");
            }
            $dicek++;
        }

        $this->assertGreaterThanOrEqual(15, $dicek, 'Terlalu sedikit halaman yang dicek — pemindaian route rusak?');
    }

    /**
     * Semua nama kunci di props halaman Inertia (atribut data-page), atau di
     * JSON unduhan cadangan. Kosong untuk respons lain.
     *
     * @return array<int, string>
     */
    private function kunciProps(string $isi): array
    {
        if (preg_match('/data-page="([^"]*)"/', $isi, $m)) {
            $data = json_decode(html_entity_decode($m[1]), true);
        } else {
            $data = json_decode($isi, true);
        }

        if (! is_array($data)) {
            return [];
        }

        $kunci = [];
        $telusur = function (array $a) use (&$telusur, &$kunci) {
            foreach ($a as $k => $v) {
                if (is_string($k)) {
                    $kunci[] = $k;
                }
                if (is_array($v)) {
                    $telusur($v);
                }
            }
        };
        $telusur($data);

        return array_values(array_unique($kunci));
    }

    /** Alamat route dari URI-nya — tidak semua route bernama (mis. POST confirm-password). */
    private function alamat(RouteDefinition $route, array $nilai): string
    {
        $uri = preg_replace_callback('/\{(\w+)\??\}/', fn ($m) => (string) ($nilai[$m[1]] ?? 1), $route->uri());

        return '/'.ltrim($uri, '/');
    }

    /** Isi respons yang benar-benar diterima browser, termasuk unduhan Excel (zip). */
    private function isiRespons($respons): string
    {
        $isi = $respons->baseResponse instanceof \Symfony\Component\HttpFoundation\StreamedResponse
            ? $respons->streamedContent()
            : ($respons->baseResponse instanceof \Symfony\Component\HttpFoundation\BinaryFileResponse
                ? file_get_contents($respons->baseResponse->getFile()->getPathname())
                : $respons->getContent());

        // .xlsx adalah zip — teksnya ada di dalam berkas XML-nya.
        if (str_starts_with($isi, "PK\x03\x04") && class_exists(\ZipArchive::class)) {
            $jalur = tempnam(sys_get_temp_dir(), 'uji-xlsx-');
            file_put_contents($jalur, $isi);
            $zip = new \ZipArchive;
            $zip->open($jalur);
            $isi = '';
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $isi .= $zip->getFromIndex($i);
            }
            $zip->close();
            unlink($jalur);
        }

        return $isi;
    }

    // ── 2. Permintaan manual ke data orang lain ─────────────────────────

    /**
     * Isian "serba ada" yang sah untuk hampir semua form. Tanpa ini, route
     * yang LUPA memeriksa pemilik bisa tampak aman hanya karena kirimannya
     * ditolak validasi lebih dulu.
     */
    private function kirimanSah(): array
    {
        $rekeningB = $this->b->accounts()->first()->id;

        return [
            'name' => 'Dibajak', 'title' => 'Dibajak', 'body' => 'Dibajak',
            'amount' => 1000, 'type' => 'income', 'account_id' => $rekeningB,
            'occurred_on' => now()->toDateString(), 'category' => null,
            'kind' => 'bank', 'institution' => null, 'opening_balance' => 1000,
            'value' => 1000, 'principal' => 1000, 'monthly_principal' => 100, 'due_on' => null,
            'target_amount' => 1000, 'initial_amount' => 0, 'target_date' => now()->addYear()->toDateString(),
            'estimated_return_rate' => 1, 'estimated_inflation_rate' => 0,
            'allocated_amount' => 0, 'priority' => 'low', 'daily_savings_target' => 1,
            'asset_allocation' => ['tabungan' => 1], 'option' => 'contribution',
            'remind_at' => now()->addDay()->format('Y-m-d H:i'), 'note_date' => now()->toDateString(),
        ];
    }

    public function test_permintaan_manual_ke_data_orang_lain_ditolak_di_setiap_route(): void
    {
        $dicek = 0;

        foreach (Route::getRoutes() as $route) {
            /** @var RouteDefinition $route */
            $parameter = $route->parameterNames();
            if ($parameter === [] || ! in_array('auth', $route->gatherMiddleware(), true)) {
                continue;
            }
            // Tautan verifikasi email bertanda tangan — dijaga `signed`, diuji EmailVerificationTest.
            if ($route->getName() === 'verification.verify') {
                continue;
            }

            $nilai = [];
            foreach ($parameter as $p) {
                $this->assertArrayHasKey($p, $this->milikA, "Route {$route->uri()} memakai parameter {{$p}} yang belum dikenal test ini — tambahkan data A untuknya.");
                $nilai[$p] = $this->milikA[$p];
            }

            foreach (array_diff($route->methods(), ['HEAD']) as $metode) {
                $sebelum = $this->potretA();

                $respons = $this->actingAs($this->b)->call($metode, $this->alamat($route, $nilai), $this->kirimanSah());

                $label = "{$metode} /{$route->uri()}";
                $this->assertContains($respons->status(), [403, 404], "{$label} oleh pengguna lain seharusnya 403/404, didapat {$respons->status()}.");
                $this->assertSame($sebelum, $this->potretA(), "{$label} oleh pengguna lain MENGUBAH data milik A.");
                $dicek++;
            }
        }

        $this->assertGreaterThanOrEqual(17, $dicek, 'Terlalu sedikit route yang dicek — pemindaian route rusak?');
    }

    public function test_tamu_tidak_bisa_mengirim_apa_pun_ke_route_yang_butuh_login(): void
    {
        foreach (Route::getRoutes() as $route) {
            /** @var RouteDefinition $route */
            if (! in_array('auth', $route->gatherMiddleware(), true) || $route->getName() === 'verification.verify') {
                continue;
            }

            $nilai = array_map(fn ($p) => $this->milikA[$p] ?? 1, array_flip($route->parameterNames()));

            foreach (array_diff($route->methods(), ['HEAD']) as $metode) {
                $sebelum = $this->potretA();
                $this->call($metode, $this->alamat($route, $nilai), $this->kirimanSah())
                    ->assertRedirect(route('login'));
                $this->assertSame($sebelum, $this->potretA(), "{$metode} /{$route->uri()} oleh tamu mengubah data.");
            }
        }
    }

    // ── 3. SQL injection ────────────────────────────────────────────────

    /** @return array<int, string> */
    private function muatanSql(): array
    {
        return [
            "' OR '1'='1",
            "1; DROP TABLE users; --",
            "1' UNION SELECT password FROM users --",
            "2026-01'); DELETE FROM transactions; --",
        ];
    }

    public function test_sql_di_parameter_halaman_tidak_memicu_error_maupun_kebocoran(): void
    {
        $halaman = [
            ['transactions.index', 'bulan'], ['dashboard', 'bulan'], ['history.index', 'page'],
            ['news.index', 'kategori'], ['news.index', 'page'], ['calculator.loan', 'principal'],
            ['calculator.investment', 'months'], ['calculator.goal', 'target_amount'],
        ];

        foreach ($halaman as [$nama, $kunci]) {
            foreach ($this->muatanSql() as $sql) {
                $sebelum = $this->potretA();
                $respons = $this->actingAs($this->a)->get(route($nama, [$kunci => $sql]));

                $this->assertLessThan(500, $respons->status(), "{$nama}?{$kunci}={$sql} memicu error server.");
                $this->assertStringNotContainsString('rahasia-b@contoh.test', $respons->getContent());
                $this->assertSame($sebelum, $this->potretA(), "{$nama}?{$kunci}={$sql} mengubah data.");
            }
        }

        $this->assertSame(2, User::count(), 'Tabel pengguna berubah.');
    }

    /**
     * SQL di isian form disimpan apa adanya sebagai TEKS — bukti bahwa
     * nilainya dikirim lewat parameter binding, tidak dirangkai ke kueri.
     */
    public function test_sql_di_isian_form_disimpan_sebagai_teks_biasa(): void
    {
        foreach ($this->muatanSql() as $k => $sql) {
            $nama = mb_substr("{$k} {$sql}", 0, 100);

            $this->actingAs($this->a)->post(route('accounts.store'), [
                'name' => $nama, 'kind' => 'cash', 'opening_balance' => 1000,
            ])->assertSessionHasNoErrors();

            $this->assertTrue($this->a->accounts()->where('name', $nama)->exists(), "Nama \"{$nama}\" tidak tersimpan apa adanya.");
        }

        $this->assertSame(2, User::count());
        $this->assertSame(2, Transaction::count(), 'Tabel transaksi tersentuh.');
    }

    public function test_sql_di_id_route_menghasilkan_404_bukan_error(): void
    {
        foreach (['1 OR 1=1', "1' OR '1'='1", '1;DROP TABLE accounts'] as $sql) {
            $status = $this->actingAs($this->a)->patch('/rekening/'.rawurlencode($sql), ['name' => 'x'])->status();
            $this->assertSame(404, $status, "ID \"{$sql}\" menghasilkan {$status}.");
        }

        $this->assertSame(2, Account::count());
    }

    /**
     * Penjaga statis: kueri mentah (selectRaw, whereRaw, DB::raw, ...) adalah
     * satu-satunya tempat SQL injection bisa masuk di Laravel. Per 30 Sep 2026
     * semuanya berisi teks tetap. Kueri mentah yang disisipi variabel ($) harus
     * memakai binding (`whereRaw('x = ?', [$nilai])`) — dan argumen binding itu
     * ditulis di baris tersendiri supaya penjaga ini tetap bisa membacanya.
     */
    public function test_kueri_mentah_tidak_pernah_disisipi_variabel(): void
    {
        $pola = '/(selectRaw|whereRaw|orWhereRaw|orderByRaw|havingRaw|groupByRaw|fromRaw|DB::raw|DB::select|DB::statement|DB::unprepared)\(([^;]*)/';
        $pelanggaran = [];

        $berkas = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(app_path()));
        foreach ($berkas as $f) {
            if ($f->getExtension() !== 'php') {
                continue;
            }
            foreach (file($f->getPathname()) as $n => $baris) {
                if (preg_match($pola, $baris, $m) && str_contains($m[2], '$')) {
                    $pelanggaran[] = str_replace(base_path().DIRECTORY_SEPARATOR, '', $f->getPathname()).':'.($n + 1).'  '.trim($baris);
                }
            }
        }

        $this->assertSame([], $pelanggaran, "Kueri mentah berisi variabel:\n".implode("\n", $pelanggaran));
    }
}
