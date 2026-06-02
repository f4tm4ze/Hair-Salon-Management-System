<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\User;
use App\Models\Customer;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Events\Registered;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Illuminate\Validation\Rule;

class RegisteredUserController extends Controller
{
    /**
     * Display the registration view.
     */
    public function create(): View
    {
        return view('auth.register');
    }

    /**
     * Handle an incoming registration request.
     *
     * @throws ValidationException
     */
    public function store(Request $request)
    {
        $request->validate([
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', 'unique:' . User::class],
            'mobile_num' => [
                'required',
                'string',
                'size:11',
                'regex:/^[0-9]+$/',
                Rule::unique('customers', 'mobile_num'),
            ],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'active',
        ]);

        // Create associated customer record
        Customer::create([
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
        ]);

        event(new Registered($user));

        Auth::login($user);

        return redirect(RouteServiceProvider::HOME);
    }
}
