<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdatePreferencesRequest;
use Illuminate\Http\RedirectResponse;

/**
 * Preferensi investasi pengguna (PRD FR-3, FR-19, FR-27).
 *
 * Dipisahkan dari ProfileController yang mengurus nama dan email. Alasannya
 * mengikuti pola Breeze: tiap kartu di halaman profil punya form dan route
 * sendiri, sehingga menyimpan satu bagian tidak menimpa bagian lain — dan
 * pesan "Tersimpan." muncul tepat di kartu yang baru disimpan.
 */
class ProfilePreferenceController extends Controller
{
    public function update(UpdatePreferencesRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        return back();
    }
}
