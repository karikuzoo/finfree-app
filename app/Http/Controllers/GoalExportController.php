<?php

namespace App\Http\Controllers;

use App\Models\FinancialGoal;
use App\Models\User;
use Illuminate\Http\Request;
use OpenSpout\Common\Entity\Row;
use OpenSpout\Common\Entity\Style\Style;
use OpenSpout\Writer\XLSX\Writer;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/**
 * Ekspor data tujuan finansial (PRD FR-38) sebagai satu berkas Excel.
 *
 * SATU berkas .xlsx, bukan JSON maupun beberapa CSV terpisah.
 *
 * JSON sempat disediakan karena ia satu-satunya format yang memuat semuanya —
 * tetapi lengkap dalam format yang tidak bisa dibuka penggunanya bukan lengkap
 * sama sekali. Pengguna FinGoal mengklik ganda berkas .json dan mendapat
 * Notepad berisi teks mentah. CSV bisa dibuka, tetapi tidak punya sheet,
 * sehingga hanya memuat setoran dan meninggalkan tujuannya sendiri.
 *
 * .xlsx menyelesaikan keduanya: terbuka langsung di Excel maupun Google
 * Sheets, dan tiap jenis data punya sheet-nya sendiri.
 *
 * LINGKUPNYA sengaja tanpa data profil — nama, email, telepon, tanggal lahir.
 * Berkas unduhan gampang tersimpan bertahun-tahun di folder Downloads,
 * terkirim ke orang lain, atau ikut tersalin ke cadangan awan. Isinya dibatasi
 * pada yang dibutuhkan pengguna untuk mengolah catatan keuangannya sendiri.
 */
class GoalExportController extends Controller
{
    public function xlsx(Request $request): BinaryFileResponse
    {
        $user = $request->user();
        $goals = $this->goals($user);

        // Ditulis ke berkas sementara lalu dikirim, bukan dialirkan langsung
        // ke keluaran. OpenSpout memasang header-nya sendiri lewat
        // openToBrowser(), dan itu bertabrakan dengan header yang sudah
        // disiapkan Laravel. Menulis ke disk lebih dulu menghindari tabrakan
        // itu, dan tetap hemat memori karena OpenSpout mengalir ke berkas.
        $jalur = tempnam(sys_get_temp_dir(), 'fingoal-').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($jalur);

        $this->sheetRingkasan($writer, $goals);
        $this->sheetTujuan($writer, $goals);
        $this->sheetSetoran($writer, $goals);

        $writer->close();

        return response()
            ->download($jalur, 'fingoal-'.now()->format('Y-m-d').'.xlsx', [
                // Berkas berisi data keuangan — jangan sampai tersimpan di
                // cache proxy atau riwayat browser bersama.
                'Cache-Control' => 'no-store, no-cache, must-revalidate',
            ])
            ->deleteFileAfterSend();
    }

    /**
     * Sheet pertama sengaja ringkasan: itu yang terbuka lebih dulu saat berkas
     * diklik, dan angka besarnya yang paling dicari.
     */
    private function sheetRingkasan(Writer $writer, $goals): void
    {
        $writer->getCurrentSheet()->setName('Ringkasan');

        $terkumpul = $goals->sum(
            fn (FinancialGoal $g) => (float) $g->initial_amount + (float) $g->contributions->sum('amount'),
        );
        $target = $goals->sum(fn (FinancialGoal $g) => (float) $g->target_amount);

        $writer->addRow(Row::fromValuesWithStyle(['Ringkasan Tujuan Finansial'], $this->tebal()));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues(['Diekspor pada', now()->translatedFormat('j F Y, H:i')]));
        $writer->addRow(Row::fromValues(['Jumlah tujuan', $goals->count()]));
        $writer->addRow(Row::fromValues(['Total target', $target]));
        $writer->addRow(Row::fromValues(['Total terkumpul', $terkumpul]));
        $writer->addRow(Row::fromValues([
            'Progres keseluruhan',
            $target > 0 ? round($terkumpul / $target * 100, 1).'%' : '—',
        ]));
        $writer->addRow(Row::fromValues([]));
        $writer->addRow(Row::fromValues([
            'Data profil tidak disertakan dalam berkas ini.',
        ]));
    }

    private function sheetTujuan(Writer $writer, $goals): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Tujuan');

        $writer->addRow(Row::fromValuesWithStyle([
            'Nama', 'Nominal target', 'Dana awal', 'Terkumpul', 'Progres (%)',
            'Tanggal target', 'Imbal hasil (%)', 'Inflasi (%)', 'Status', 'Dibuat',
        ], $this->tebal()));

        foreach ($goals as $goal) {
            $terkumpul = (float) $goal->initial_amount + (float) $goal->contributions->sum('amount');
            $target = (float) $goal->target_amount;

            $writer->addRow(Row::fromValues([
                $goal->name,
                $target,
                (float) $goal->initial_amount,
                $terkumpul,
                $target > 0 ? round($terkumpul / $target * 100, 1) : 0,
                // Tanggal ditulis sebagai teks ISO, bukan objek tanggal.
                // Excel menampilkan objek tanggal menurut locale mesin
                // pembacanya, sehingga 3 September bisa terbaca 9 Maret di
                // komputer berlokal Amerika. ISO tidak pernah ambigu.
                $goal->target_date?->toDateString() ?? 'Tanpa tenggat',
                (float) $goal->estimated_return_rate,
                (float) $goal->estimated_inflation_rate,
                $goal->status->value,
                $goal->created_at?->toDateString(),
            ]));
        }
    }

    private function sheetSetoran(Writer $writer, $goals): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Setoran');

        $writer->addRow(Row::fromValuesWithStyle(
            ['Tanggal', 'Tujuan', 'Nominal', 'Catatan'],
            $this->tebal(),
        ));

        foreach ($goals as $goal) {
            foreach ($goal->contributions as $setoran) {
                $writer->addRow(Row::fromValues([
                    $setoran->contributed_on->toDateString(),
                    $goal->name,
                    (float) $setoran->amount,
                    $setoran->note ?? '',
                ]));
            }
        }
    }

    private function tebal(): Style
    {
        return new Style(fontBold: true);
    }

    /** @return \Illuminate\Database\Eloquent\Collection<int, FinancialGoal> */
    private function goals(User $user)
    {
        return $user->goals()
            // Di-eager load supaya jumlah kueri tidak ikut bertambah seiring
            // banyaknya tujuan — ekspor menyentuh seluruh riwayat sekaligus,
            // justru di sinilah N+1 paling terasa.
            ->with(['contributions' => fn ($q) => $q->orderBy('contributed_on')])
            ->orderBy('created_at')
            ->get();
    }
}
