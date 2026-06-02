<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class ProfileController extends Controller
{
    public function edit()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $employee = $user->employee;
        return view('tech.profile.edit', compact('user', 'employee'));
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $employee = $user->employee;

        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $user->id,
            'mobile_num' => 'nullable|string|size:11|regex:/^[0-9]+$/',
            'birthdate' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'password' => 'nullable|string|min:8|confirmed',
        ]);

        $user->update([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $employee->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
        ]);

        return redirect()->route('tech.profile.edit')->with('success', 'Profile updated successfully.');
    }
}
