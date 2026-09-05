<?php

namespace App\Http\Controllers;

use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

class AuthController extends Controller
{
    public function mostrarLogin(): View|RedirectResponse
    {
        if (Auth::check()) {
            return redirect(Auth::user()->rutaInicio());
        }

        return view('auth.login');
    }

    public function login(Request $request): RedirectResponse
    {
        $datos = $request->validate([
            'email'    => ['required', 'email'],
            'password' => ['required', 'string'],
        ], [], [
            'email'    => 'correo electrónico',
            'password' => 'contraseña',
        ]);

        if (! Auth::attempt($datos, $request->boolean('recordarme'))) {
            throw ValidationException::withMessages([
                'email' => 'Las credenciales no coinciden con ninguna cuenta.',
            ]);
        }

        $request->session()->regenerate();

        $user = Auth::user();

        if (! $user->activo) {
            Auth::logout();

            throw ValidationException::withMessages([
                'email' => 'Esta cuenta está desactivada. Contacta con tu centro.',
            ]);
        }

        $user->forceFill(['ultimo_acceso_en' => now()])->save();

        // La web no pregunta el rol: lo deduce de la cuenta.
        return redirect()->intended($user->rutaInicio());
    }

    public function logout(Request $request): RedirectResponse
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
