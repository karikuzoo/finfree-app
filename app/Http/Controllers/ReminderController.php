<?php

namespace App\Http\Controllers;

use App\Models\Reminder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Pengingat pada tanggal & jam tertentu di kalender aktivitas.
 *
 * Berbeda dari CalendarNote yang hanya satu per tanggal, satu tanggal boleh
 * punya banyak pengingat — masing-masing dengan jam sendiri.
 *
 * Pengingat ini murni DI DALAM APLIKASI: ia tampil saat pengguna membuka
 * FinGoal, dan tidak mengirim notifikasi apa pun ke perangkat. Mengirim
 * notifikasi meski aplikasi tertutup menuntut Web Push atau email terjadwal —
 * keduanya keputusan tersendiri, bukan sesuatu yang boleh menyelinap masuk.
 */
class ReminderController extends Controller
{
    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'title' => ['required', 'string', 'max:200'],

            // Tanggal dan jam diterima terpisah karena itu bentuk yang wajar
            // di form, lalu digabung menjadi satu nilai saat disimpan.
            'remind_date' => ['required', 'date'],
            'remind_time' => ['required', 'date_format:H:i'],
        ], [
            'title.required' => 'Judul pengingat wajib diisi.',
            'title.max' => 'Judul pengingat maksimal 200 karakter.',
            'remind_date.required' => 'Tanggal pengingat wajib diisi.',
            'remind_time.required' => 'Jam pengingat wajib diisi.',
            'remind_time.date_format' => 'Jam pengingat harus dalam format 24 jam, misalnya 09:00.',
        ]);

        $request->user()->reminders()->create([
            'title' => $data['title'],
            'remind_at' => $data['remind_date'].' '.$data['remind_time'].':00',
        ]);

        return back();
    }

    /**
     * Menandai selesai, atau membatalkan tanda itu.
     *
     * Sengaja tanpa parameter "jadikan selesai atau belum" — tombolnya satu,
     * dan mengirim nilai yang diinginkan dari frontend membuka celah dua klik
     * beruntun saling menimpa.
     */
    public function toggle(Request $request, Reminder $reminder): RedirectResponse
    {
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->update([
            'completed_at' => $reminder->isCompleted() ? null : now(),
        ]);

        return back();
    }

    public function destroy(Request $request, Reminder $reminder): RedirectResponse
    {
        // Pemeriksaan kepemilikan — titik kebocoran data antar pengguna yang
        // paling mudah terlewat (CONTRIBUTING §7).
        abort_unless($reminder->user_id === $request->user()->id, 403);

        $reminder->delete();

        return back();
    }
}
