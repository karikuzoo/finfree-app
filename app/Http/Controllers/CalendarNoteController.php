<?php

namespace App\Http\Controllers;

use App\Models\CalendarNote;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Catatan pengguna pada tanggal tertentu di kalender aktivitas dashboard.
 *
 * Satu catatan per tanggal, jadi menyimpan berarti "buat atau perbarui" —
 * bukan menambah baris baru. Karena itu tidak ada method `update` terpisah.
 */
class CalendarNoteController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'note_date' => ['required', 'date'],
            'body' => ['required', 'string', 'max:500'],
        ], [
            'body.required' => 'Catatan tidak boleh kosong.',
            'body.max' => 'Catatan maksimal 500 karakter.',
        ]);

        CalendarNote::updateOrCreate(
            [
                // user_id ikut jadi kunci pencarian, bukan hanya nilai yang
                // disimpan. Tanpa itu, seseorang bisa menimpa catatan milik
                // pengguna lain di tanggal yang sama.
                'user_id' => $request->user()->id,
                'note_date' => $data['note_date'],
            ],
            ['body' => $data['body']],
        );

        return back();
    }

    public function destroy(Request $request, CalendarNote $calendarNote): RedirectResponse
    {
        // Pemeriksaan kepemilikan — titik kebocoran data antar pengguna yang
        // paling mudah terlewat (CONTRIBUTING §7).
        abort_unless($calendarNote->user_id === $request->user()->id, 403);

        $calendarNote->delete();

        return back();
    }
}
