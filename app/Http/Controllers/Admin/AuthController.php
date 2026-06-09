<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\ValidationException;

class AuthController extends Controller
{
    public function login(Request $request)
    {
        $credentials = $request->validate(['email' => ['required', 'email'], 'password' => ['required', 'string']]);

        if (! Auth::attempt($credentials, true) || ! Auth::user()->is_admin) {
            Auth::logout();
            throw ValidationException::withMessages(['email' => __('auth.failed')]);
        }

        $request->session()->regenerate();

        return ['user' => Auth::user()->only(['id', 'name', 'email'])];
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return ['ok' => true];
    }

    public function me()
    {
        return ['user' => Auth::user()->only(['id', 'name', 'email'])];
    }
}
