<?php

use App\Http\Controllers\GoalCalculatorController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\ProfilePreferenceController;
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

Route::get('/', fn () => Inertia::render('Welcome'))->name('home');

Route::middleware('throttle:60,1')->group(function () {
    Route::get('/kalkulator', fn () => Inertia::render('Calculator/Index'))
        ->name('calculator.index');

    Route::get('/kalkulator/tujuan', [GoalCalculatorController::class, 'show'])
        ->name('calculator.goal');
});

Route::get('/berita', fn () => Inertia::render('News/Index'))->name('news.index');

/*
|--------------------------------------------------------------------------
| Halaman yang menuntut login
|--------------------------------------------------------------------------
*/

Route::get('/dashboard', fn () => Inertia::render('Dashboard'))
    ->middleware(['auth', 'verified'])
    ->name('dashboard');

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
    Route::patch('/profile/preferensi', [ProfilePreferenceController::class, 'update'])
        ->name('profile.preferences.update');
});

require __DIR__.'/auth.php';
