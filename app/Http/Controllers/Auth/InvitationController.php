<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;      // <-- ADD THIS
use Illuminate\Validation\Rules\Password; // <-- ADD THIS

class InvitationController extends Controller
{
    public function showAcceptForm($token)
    {
        $user = User::where('invitation_token', $token)
            ->whereNull('invitation_accepted_at')
            ->where('invitation_expires_at', '>', now())
            ->firstOrFail();

        return view('auth.accept-invite', ['token' => $token, 'email' => $user->email]);
    }

    public function accept(Request $request, $token)
    {
        $user = User::where('invitation_token', $token)
            ->whereNull('invitation_accepted_at')
            ->where('invitation_expires_at', '>', now())
            ->firstOrFail();

        $request->validate([
            'password' => ['required', 'confirmed', Password::defaults()],
        ]);

        $user->acceptInvitation($request->password);

        // Use the Auth facade instead of the helper
        Auth::login($user);

        return redirect()->route('dashboard')->with('success', 'Your account has been activated. Welcome!');
    }
}
