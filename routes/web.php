<?php

use App\Http\Controllers\AvatarFileController;
use App\Http\Controllers\CalendarNoteController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\GoalCalculatorController;
use App\Http\Controllers\GoalContributionController;
use App\Http\Controllers\GoalController;
use App\Http\Controllers\HistoryController;
use App\Http\Controllers\GoalDailySavingsTargetController;
use App\Http\Controllers\ProfileAvatarController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePreferenceController;
use App\Http\Controllers\ReminderController;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;

/*
|--------------------------------------------------------------------------
| Halaman publik
|--------------------------------------------------------------------------
|
| Ketiganya sengaja berada di luar middleware `auth`. Kalkulator utilitas
| adalah pintu masuk pengguna baru — orang mencari "simulasi cicilan KPR" di
| mesin pencari, dan mengunci itu di balik pendaftaran membuang keunggulannya
| (PRD FR-44). Menyimpan hasilnya baru menuntut akun.
|
| `throttle` dipasang karena halaman publik yang menghitung tanpa batas laju
| adalah beban gratis bagi siapa pun yang ingin menyalahgunakannya
| (CLAUDE.md §6.8).
|
*/

Route::get('/', function () {
    // Sesuai permintaan: kalau sudah login, "/" (dan tombol logo yang
    // mengarah ke sini) selalu dialihkan ke Dashboard — Welcome hanya
    // untuk tamu yang belum punya akun/sesi.
    if (Auth::check()) {
        return redirect()->route('dashboard');
    }

    return Inertia::render('Welcome');
})->name('home');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/kalkulator', fn () => Inertia::render('Calculator/Index'))
        ->name('calculator.index');

    Route::get('/kalkulator/tujuan', [GoalCalculatorController::class, 'show'])
        ->name('calculator.goal');
});

Route::get('/berita', fn () => Inertia::render('News/Index'))->name('news.index');

// Lihat komentar panjang di AvatarFileController — ini pengganti
// Storage::disk('public')->url(), bukan duplikat symlink /storage.
Route::get('/media/avatars/{filename}', [AvatarFileController::class, 'show'])
    ->name('avatars.show');

/*
|--------------------------------------------------------------------------
| Halaman yang menuntut login
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', [DashboardController::class, 'index'])
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    // Catatan pengguna pada tanggal tertentu di kalender aktivitas dashboard.
    Route::post('/kalender/catatan', [CalendarNoteController::class, 'store'])
        ->name('calendar-notes.store');
    Route::delete('/kalender/catatan/{calendarNote}', [CalendarNoteController::class, 'destroy'])
        ->name('calendar-notes.destroy');

    // Pengingat kalender. Beda dari catatan: satu tanggal boleh punya banyak
    // pengingat, masing-masing dengan jamnya sendiri.
    Route::post('/kalender/pengingat', [ReminderController::class, 'store'])
        ->name('reminders.store');
    Route::patch('/kalender/pengingat/{reminder}', [ReminderController::class, 'update'])
        ->name('reminders.update');
    // Menandai selesai punya jalurnya sendiri: ia bukan "perubahan
    // sebagian" biasa melainkan satu tombol tanpa masukan, dan PATCH
    // pada rute induk dipakai untuk menyunting judul & jam.
    Route::patch('/kalender/pengingat/{reminder}/selesai', [ReminderController::class, 'toggle'])
        ->name('reminders.toggle');
    Route::delete('/kalender/pengingat/{reminder}', [ReminderController::class, 'destroy'])
        ->name('reminders.destroy');

    // Goal, Dompet & Riwayat masih kerangka (page dummy) — lihat
    // komentar di masing-masing file Page-nya. Sengaja di dalam grup
    // `auth`, beda dari Kalkulator/Berita yang publik, karena
    // ketiganya menampilkan data milik satu akun, tidak ada gunanya
    // diakses tanpa login.
    //
    // Path "/tujuan" dipilih supaya konsisten dengan prefix yang dipakai
    // dua route di bawah (target-harian, setoran) untuk aksi per-goal —
    // /tujuan sebagai index, /tujuan/{id}/... sebagai aksi. Urutan
    // definisinya tidak masalah karena jumlah segmen path beda, Laravel
    // tidak menganggapnya bentrok.
    Route::get('/tujuan', [GoalController::class, 'index'])
        ->name('goals.index');

    // Alur "Buat Tujuan Pertama". Ditaruh SEBELUM rute bersegmen dinamis
    // seperti /tujuan/{financialGoal} bila nanti ada, supaya "buat" tidak
    // pernah tertangkap sebagai ID tujuan.
    Route::get('/tujuan/buat', [GoalController::class, 'create'])
        ->name('goals.create');
    Route::post('/tujuan', [GoalController::class, 'store'])
        ->name('goals.store');
    Route::get('/tujuan/{financialGoal}/ubah', [GoalController::class, 'edit'])
        ->name('goals.edit');
    Route::patch('/tujuan/{financialGoal}', [GoalController::class, 'update'])
        ->name('goals.update');
    Route::patch('/tujuan/{financialGoal}/utama', [GoalController::class, 'setPrimary'])
        ->name('goals.primary');
    Route::get('/dompet', function (\Illuminate\Http\Request $request, \App\Services\DashboardSummaryService $summary) {
        $ringkasan = $summary->forUser($request->user());
        return Inertia::render('Wallet/Index', [
            'totalAssets' => $ringkasan['total_assets'],
            'goals' => $ringkasan['goals'],
        ]);
    })->name('wallet.index');
    Route::get('/riwayat', [HistoryController::class, 'index'])
        ->name('history.index');

    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/preferensi', [ProfilePreferenceController::class, 'update'])
        ->name('profile.preferences.update');

    // POST, bukan PATCH: unggahan berkas dikirim sebagai multipart/form-data
    // dan browser hanya bisa mengirimnya lewat POST.
    Route::post('/profile/foto', [ProfileAvatarController::class, 'update'])
        ->name('profile.avatar.update');
    Route::delete('/profile/foto', [ProfileAvatarController::class, 'destroy'])
        ->name('profile.avatar.destroy');

    Route::patch('/tujuan/{financialGoal}/target-harian', [GoalDailySavingsTargetController::class, 'update'])
        ->name('goals.daily-savings-target.update');

    Route::post('/tujuan/{financialGoal}/setoran', [GoalContributionController::class, 'store'])
        ->name('goals.contributions.store');

    // Ubah & hapus setoran (FR-33). Disunting dari dialog tanggal di
    // kalender, jadi tanggalnya tidak ikut bisa diubah — lihat controller.
    Route::patch('/setoran/{goalContribution}', [GoalContributionController::class, 'update'])
        ->name('goals.contributions.update');
    Route::delete('/setoran/{goalContribution}', [GoalContributionController::class, 'destroy'])
        ->name('goals.contributions.destroy');

    Route::delete('/tujuan/{financialGoal}', [GoalController::class, 'destroy'])
        ->name('goals.destroy');
});

require __DIR__.'/auth.php';