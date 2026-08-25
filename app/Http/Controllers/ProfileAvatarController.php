<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAvatarRequest;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Foto profil (PRD FR-3).
 *
 * Memakai POST, bukan PATCH, karena unggahan berkas dikirim sebagai
 * multipart/form-data — dan browser hanya bisa mengirimnya lewat POST.
 */
class ProfileAvatarController extends Controller
{
    public function update(UpdateAvatarRequest $request, AvatarService $avatars): RedirectResponse
    {
        $user = $request->user();

        $path = $avatars->store($user, $request->file('avatar'));

        $user->forceFill(['avatar_path' => $path])->save();

        return back();
    }

    public function destroy(Request $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->remove($request->user());

        return back();
    }
}
