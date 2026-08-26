<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): Response
    {
        return Inertia::render('Auth/Register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request): RedirectResponse
    {
        $request->validate([
            // \pL mencakup huruf beraksen, bukan hanya A–Z. Spasi, apostrof,
            // titik, dan tanda hubung ikut diizinkan karena nama sungguhan
            // memuatnya — "Siti Nur'aini", "H. Ahmad", "Anne-Marie". Membatasi
            // ke huruf saja terdengar rapi di atas kertas, tetapi berarti
            // menolak orang yang namanya memang begitu.
            'name' => ['required', 'string', 'max:255', "regex:/^[\pL\s.'-]+$/u"],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:'.User::class],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ], [
            // Hanya pesan yang perlu lebih spesifik daripada versi umum di
            // lang/id/validation.php. Sisanya sudah otomatis Bahasa Indonesia.
            'name.regex' => 'Nama hanya boleh berisi huruf, spasi, titik, apostrof, dan tanda hubung.',
        ]);

        $user = User::create([
            'name' => $request->name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(route('dashboard', absolute: false));
    }
}
