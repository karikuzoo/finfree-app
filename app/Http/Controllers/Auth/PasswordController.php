<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordController extends Controller
{
    /**
     * Update the user's password.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', Password::defaults(), 'confirmed'],
        ]);

        $request->user()->update([
            'password' => Hash::make($validated['password']),
        ]);

        // Orang yang mengganti kata sandi sering melakukannya karena curiga
        // ada yang ikut masuk. Mengganti sandi tanpa mengeluarkan sesi lain
        // membiarkan penyusup tetap di dalam. Sesi ini sendiri tetap masuk:
        // logoutOtherDevices memperbarui cookie "ingat saya" perangkat ini, dan
        // AuthenticateSession menyimpan sidik sandi barunya di sesi ini.
        Auth::logoutOtherDevices($validated['password']);

        return back();
    }
}
