<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use App\Models\User;

class ProfileController extends Controller
{
    public function edit()
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $customer = $user->customer;
        return view('customer.profile.edit', compact('user', 'customer'));
    }

    public function update(Request $request)
    {
        /** @var \App\Models\User $user */
        $user = Auth::user();
        $customer = $user->customer;

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

        $customer->update([
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
        ]);

        return redirect()->route('customer.profile.edit')->with('success', 'Profile updated successfully.');
    }
}
