<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Validation\Rule;
use App\Models\Customer;
use App\Models\Employee;

class ProfileController extends Controller
{
    public function edit(Request $request): View
    {
        $user = $request->user();
        $profile = null;

        if ($user->role == 'customer') {
            $profile = $user->customer;
        } else {
            $profile = $user->employee;
        }

        return view('profile.edit', compact('user', 'profile'));
    }

    public function update(Request $request): RedirectResponse
    {
        $user = $request->user();

        // Base rules (common for all)
        $rules = [
            'name'       => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email,' . $user->id,
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'birthdate'  => 'required|date',
            'gender'     => 'required|in:male,female,other',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
            'password'   => 'nullable|string|min:8|confirmed',
        ];

        // Build mobile_num rule dynamically as an array
        $mobileRule = ['required', 'string', 'size:11', 'regex:/^[0-9]+$/'];

        if ($user->role == 'customer') {
            $customer = $user->customer;
            if ($customer) {
                $mobileRule[] = Rule::unique('customers', 'mobile_num')->ignore($customer->id);
            }
        } elseif (in_array($user->role, ['manager', 'frontdesk', 'tech', 'employee'])) {
            $employee = $user->employee;
            if ($employee) {
                $mobileRule[] = Rule::unique('employees', 'mobile_num')->ignore($employee->id);
            }
        }

        $rules['mobile_num'] = $mobileRule;

        $request->validate($rules);

        // Update user name and email
        $user->update([
            'name'  => $request->name,
            'email' => $request->email,
        ]);

        // Handle profile (customer or employee)
        if ($user->role == 'customer') {
            $profile = $user->customer;
            if (!$profile) {
                $profile = \App\Models\Customer::create([
                    'user_id'    => $user->id,
                    'first_name' => $request->first_name,
                    'last_name'  => $request->last_name,
                    'mobile_num' => $request->mobile_num,
                    'birthdate'  => $request->birthdate,
                    'gender'     => $request->gender,
                ]);
            } else {
                $profile->update([
                    'first_name' => $request->first_name,
                    'last_name'  => $request->last_name,
                    'mobile_num' => $request->mobile_num,
                    'birthdate'  => $request->birthdate,
                    'gender'     => $request->gender,
                ]);
            }
        } else {
            // For staff, the profile must already exist
            $profile = $user->employee;
            if (!$profile) {
                return back()->withErrors(['error' => 'Employee profile missing. Please contact administrator.']);
            }
            $profile->update([
                'first_name' => $request->first_name,
                'last_name'  => $request->last_name,
                'mobile_num' => $request->mobile_num,
                'birthdate'  => $request->birthdate,
                'gender'     => $request->gender,
            ]);
        }

        // Handle image upload
        if ($request->hasFile('image')) {
            if ($profile->image && Storage::disk('public')->exists($profile->image)) {
                Storage::disk('public')->delete($profile->image);
            }
            $folder = $user->role == 'customer' ? 'customers' : 'employees';
            $imagePath = $request->file('image')->store($folder, 'public');
            $profile->update(['image' => $imagePath]);
        }

        // Update password if provided
        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        return Redirect::route('profile.edit')->with('success', 'Profile updated successfully.');
    }

    public function destroy(Request $request): RedirectResponse
    {
        // Keep the existing destroy method (unchanged)
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);

        $user = $request->user();

        Auth::logout();

        $user->delete();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return Redirect::to('/');
    }
}
