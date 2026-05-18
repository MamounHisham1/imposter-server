<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;

class AuthController extends Controller
{
    public function showLogin()
    {
        return Inertia::render('Login');
    }

    public function showRegister()
    {
        return Inertia::render('Register');
    }

    public function register(Request $request)
    {
        $validated = $request->validate([
            'nickname' => 'required|string|max:20|unique:users,nickname',
            'email' => 'nullable|email|unique:users,email',
            'password' => 'required|string|min:6',
        ]);

        $user = User::create([
            'nickname' => $validated['nickname'],
            'email' => $validated['email'] ?? null,
            'password' => Hash::make($validated['password']),
        ]);

        Auth::login($user);

        return redirect()->route('home');
    }

    public function login(Request $request)
    {
        $validated = $request->validate([
            'nickname' => 'required|string',
            'password' => 'required|string',
        ]);

        if (! Auth::attempt(['nickname' => $validated['nickname'], 'password' => $validated['password']])) {
            throw ValidationException::withMessages([
                'nickname' => __('auth.failed'),
            ]);
        }

        $request->session()->regenerate();

        return redirect()->route('home');
    }

    public function logout(Request $request)
    {
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('home');
    }
}
