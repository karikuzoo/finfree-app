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
 * sama sekali. Pengguna Arus mengklik ganda berkas .json dan mendapat
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
        $jalur = tempnam(sys_get_temp_dir(), 'arus-').'.xlsx';

        $writer = new Writer();
        $writer->openToFile($jalur);

        $this->sheetRingkasan($writer, $goals);
        $this->sheetTujuan($writer, $goals);
        $this->sheetTransaksi($writer, $user);

        $writer->close();

        return response()
            ->download($jalur, 'arus-'.now()->format('Y-m-d').'.xlsx', [
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

        $terkumpul = $goals->sum(fn (FinancialGoal $g) => (float) $g->allocated_amount);
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
            'Nama', 'Nominal target', 'Dana ditandai', 'Progres (%)', 'Rekening',
            'Prioritas', 'Tanggal target', 'Imbal hasil (%)', 'Inflasi (%)', 'Status', 'Dibuat',
        ], $this->tebal()));

        foreach ($goals as $goal) {
            $terkumpul = (float) $goal->allocated_amount;
            $target = (float) $goal->target_amount;

            $writer->addRow(Row::fromValues([
                $goal->name,
                $target,
                $terkumpul,
                $target > 0 ? round($terkumpul / $target * 100, 1) : 0,
                $goal->account?->name ?? 'Belum ditandai',
                $goal->priority->label(),
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

    /**
     * Dulu sheet ini berisi setoran per tujuan. Sejak pencatatan setoran
     * dipensiunkan, catatan uang yang sebenarnya ada di `transactions` —
     * dan itu yang diekspor. Isinya lebih lengkap daripada sebelumnya:
     * bukan hanya uang yang masuk ke tujuan, tetapi seluruh pemasukan,
     * pengeluaran, transfer, penyesuaian nilai, dan pembayaran utang.
     */
    private function sheetTransaksi(Writer $writer, User $user): void
    {
        $writer->addNewSheetAndMakeItCurrent()->setName('Transaksi');

        $writer->addRow(Row::fromValuesWithStyle(
            ['Tanggal', 'Nama', 'Jenis', 'Rekening', 'Ke rekening', 'Utang', 'Kategori', 'Nominal'],
            $this->tebal(),
        ));

        // Dialirkan per potongan, bukan dimuat seluruhnya: riwayat transaksi
        // tumbuh tanpa batas atas, dan ekspor menyentuh SEMUANYA sekaligus —
        // justru di sinilah memori paling mudah habis.
        $user->transactions()
            ->with(['account:id,name', 'toAccount:id,name', 'debt:id,name'])
            ->orderBy('occurred_on')
            ->orderBy('id')
            ->chunk(500, function ($transaksi) use ($writer) {
                foreach ($transaksi as $t) {
                    $writer->addRow(Row::fromValues([
                        $t->occurred_on->toDateString(),
                        $t->name,
                        $t->type->label(),
                        $t->account?->name ?? '',
                        $t->toAccount?->name ?? '',
                        $t->debt?->name ?? '',
                        $t->category ?? '',
                        (float) $t->amount,
                    ]));
                }
            });
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
            ->with('account:id,name')
            ->orderBy('created_at')
            ->get();
    }
}
