<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create()
    {
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request)
    {
        $request->validate([
            'email' => 'required|email',
            'password' => 'required',
        ]);

        // Check if user exists
        $user = User::where('email', $request->email)->first();

        if (!$user) {
            return back()->withErrors([
                'email' => 'No account found with this email.',
            ])->onlyInput('email');
        }

        // Check if password is correct
        if (!Hash::check($request->password, $user->password)) {
            return back()->withErrors([
                'password' => 'Password provided is incorrect.',
            ])->onlyInput('email');
        }

        // Check if user is active (optional)
        if ($user->status !== 'active') {
            return back()->withErrors([
                'email' => 'This account is inactive. Please contact support.',
            ])->onlyInput('email');
        }

        // Attempt login
        Auth::login($user, $request->boolean('remember'));

        $request->session()->regenerate();

        return redirect()->intended('/dashboard');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request)
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect()->route('login');
    }
}
