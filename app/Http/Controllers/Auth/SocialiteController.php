<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use Laravel\Socialite\Facades\Socialite;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Str;

class SocialiteController extends Controller
{
    public function redirect($provider)
    {
        return Socialite::driver($provider)->redirect();
    }

    public function callback($provider)
    {
        try {
            $socialUser = Socialite::driver($provider)->user();
        } catch (\Exception $e) {
            return redirect('/login')->withErrors(['msg' => 'Login failed, please try again.']);
        }

        $user = User::where('email', $socialUser->getEmail())->first();

        if (!$user) {
            $user = User::create([
                'name' => $socialUser->getName() ?? $socialUser->getNickname(),
                'email' => $socialUser->getEmail(),
                'password' => bcrypt(Str::random(16)),
                'role' => 'customer',
                'status' => 'active',
            ]);

            Customer::create([
                'user_id' => $user->id,
                'first_name' => $user->name,
                'last_name' => '',
            ]);
        }

        Auth::login($user);
        return redirect()->intended('/dashboard');
    }
}
