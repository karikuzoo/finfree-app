<?php

namespace App\Http\Controllers;

use App\Enums\RiskProfile;
use App\Http\Requests\ProfileUpdateRequest;
use App\Services\AvatarService;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Inertia\Inertia;
use Inertia\Response;

class ProfileController extends Controller
{
    /**
     * Display the user's profile form.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Profile/Edit', [
            'mustVerifyEmail' => $request->user() instanceof MustVerifyEmail,
            'status' => session('status'),
            // Daftar pilihan dikirim dari backend, bukan ditulis ulang di
            // React — supaya menambah profil risiko baru cukup di App\Enums.
            'riskProfiles' => RiskProfile::options(),
        ]);
    }

    /**
     * Update the user's profile information.
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());

        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }

        $request->user()->save();

        return Redirect::route('profile.edit');
    }

    /**
     * Delete the user's account.
     */
    public function destroy(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        // Berkas foto harus ikut dihapus. Menghapus barisnya saja meninggalkan
        // gambar wajah pengguna di penyimpanan selamanya — bertentangan dengan
        // FR-37 yang menuntut penghapusan menyeluruh, dan berkas itu tidak lagi
        // dimiliki siapa pun sehingga tak akan pernah dibersihkan.
        $avatars->deleteFile($user->avatar_path);

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
