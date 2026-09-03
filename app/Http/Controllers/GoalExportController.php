<?php

namespace App\Http\Controllers;

use App\Models\FinancialGoal;
use App\Models\GoalCalculation;
use App\Models\User;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Ekspor data tujuan finansial (PRD FR-38).
 *
 * LINGKUPNYA sengaja terbatas pada tujuan, setoran, dan snapshot
 * perhitungannya. Data profil — nama, email, tanggal lahir, nomor telepon —
 * TIDAK ikut: berkas unduhan gampang tersimpan di folder Downloads bertahun-
 * tahun, terkirim ke orang lain, atau ikut tersalin ke cadangan awan. Isinya
 * dibatasi pada yang benar-benar dibutuhkan pengguna untuk mengolah datanya
 * sendiri, bukan seluruh isi basis data tentang dirinya.
 *
 * Hash kata sandi dan token sesi jelas tidak pernah ikut.
 *
 * Dua format karena dua kebutuhan berbeda:
 *  - JSON: lengkap dan berstruktur, untuk memindahkan atau mencadangkan.
 *  - CSV : setoran saja, untuk diolah di spreadsheet. Hanya setoran yang
 *          benar-benar berbentuk tabel; memaksa tujuan dan kalkulasi ke CSV
 *          menuntut beberapa berkas dalam zip — mesin yang lebih rumit
 *          daripada manfaatnya.
 */
class GoalExportController extends Controller
{
    public function json(Request $request): StreamedResponse
    {
        $user = $request->user();

        $isi = [
            'diekspor_pada' => now()->toIso8601String(),
            'aplikasi' => config('app.name'),
            'catatan' => 'Berisi data tujuan finansial dan setoran Anda. Data profil tidak disertakan.',
            'tujuan' => $this->goals($user)->map(fn (FinancialGoal $goal) => [
                'nama' => $goal->name,
                'nominal_target' => (float) $goal->target_amount,
                'dana_awal' => (float) $goal->initial_amount,
                'tanggal_target' => $goal->target_date?->toDateString(),
                'estimasi_imbal_hasil_persen' => (float) $goal->estimated_return_rate,
                'estimasi_inflasi_persen' => (float) $goal->estimated_inflation_rate,
                'status' => $goal->status->value,
                'dibuat_pada' => $goal->created_at?->toIso8601String(),

                'setoran' => $goal->contributions->map(fn ($s) => [
                    'tanggal' => $s->contributed_on->toDateString(),
                    'nominal' => (float) $s->amount,
                    'catatan' => $s->note,
                ])->values(),

                'perhitungan' => $goal->calculations->map(fn (GoalCalculation $k) => [
                    'dihitung_pada' => $k->created_at?->toIso8601String(),
                    'setoran_bulanan_dibutuhkan' => (float) $k->monthly_contribution_required,
                    'proyeksi_total_setoran' => (float) $k->total_contribution_projection,
                    'proyeksi_hasil_investasi' => (float) $k->total_investment_growth_projection,
                    // Versi rumus ikut supaya angka lama tetap bisa dijelaskan
                    // meski rumusnya berubah kemudian (PRD D-6).
                    'versi_rumus' => $k->formula_version,
                ])->values(),
            ])->values(),
        ];

        return $this->unduh(
            $this->namaBerkas('json'),
            'application/json',
            fn () => print json_encode(
                $isi,
                JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES,
            ),
        );
    }

    public function csv(Request $request): StreamedResponse
    {
        $goals = $this->goals($request->user());

        return $this->unduh(
            $this->namaBerkas('csv'),
            'text/csv; charset=UTF-8',
            function () use ($goals) {
                $keluaran = fopen('php://output', 'w');

                // BOM UTF-8. Tanpa ini Excel di Windows membaca berkasnya
                // sebagai ANSI, dan setiap huruf beraksen maupun tanda kutip
                // lengkung pada catatan berubah jadi karakter aneh.
                fwrite($keluaran, "\xEF\xBB\xBF");

                fputcsv($keluaran, ['Tanggal', 'Tujuan', 'Nominal', 'Catatan']);

                foreach ($goals as $goal) {
                    foreach ($goal->contributions as $setoran) {
                        fputcsv($keluaran, [
                            $setoran->contributed_on->toDateString(),
                            $goal->name,
                            // Angka polos tanpa pemisah ribuan — spreadsheet
                            // perlu membacanya sebagai bilangan, bukan teks.
                            (float) $setoran->amount,
                            $setoran->note,
                        ]);
                    }
                }

                fclose($keluaran);
            },
        );
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, FinancialGoal> */
    private function goals(User $user)
    {
        return $user->goals()
            // Di-eager load supaya jumlah kueri tidak ikut bertambah seiring
            // banyaknya tujuan.
            ->with([
                'contributions' => fn ($q) => $q->orderBy('contributed_on'),
                'calculations' => fn ($q) => $q->orderBy('created_at'),
            ])
            ->orderBy('created_at')
            ->get();
    }

    private function namaBerkas(string $ekstensi): string
    {
        return 'fingoal-tujuan-'.now()->format('Y-m-d').'.'.$ekstensi;
    }

    /**
     * Dialirkan, bukan disusun utuh di memori lebih dulu. Pengguna dengan
     * riwayat setoran bertahun-tahun tidak akan membentur batas memori PHP.
     */
    private function unduh(string $nama, string $tipe, callable $tulis): StreamedResponse
    {
        return response()->streamDownload($tulis, $nama, [
            'Content-Type' => $tipe,
            // Berkas berisi data keuangan — jangan sampai tersimpan di cache
            // proxy atau riwayat browser bersama.
            'Cache-Control' => 'no-store, no-cache, must-revalidate',
        ]);
    }
}
