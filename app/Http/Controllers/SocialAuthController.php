<?php

namespace App\Http\Controllers;

use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Laravel\Socialite\Facades\Socialite;

class SocialAuthController extends Controller
{
    public function redirect()
    {
        return Socialite::driver('google')->redirect();
    }

    public function callback()
    {
        $googleUser = Socialite::driver('google')->user();

        $user = User::where('google_id', $googleUser->getId())
            ->orWhere('email', $googleUser->getEmail())
            ->first();

        if ($user) {
            $user->update([
                'google_id' => $googleUser->getId(),
                'email' => $googleUser->getEmail(),
            ]);
        } else {
            $user = User::create([
                'google_id' => $googleUser->getId(),
                'nickname' => $googleUser->getNickname() ?? explode('@', $googleUser->getEmail())[0],
                'email' => $googleUser->getEmail(),
            ]);
        }

        Auth::login($user);

        return redirect()->route('home');
    }
}
