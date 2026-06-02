<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Customer;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;
use App\Mail\AccountReactivatedMail;
use Illuminate\Support\Facades\Mail;

class CustomerController extends Controller
{
    public function index(Request $request)
    {
        $query = Customer::with('user')
            ->whereHas('user', function ($q) {
                $q->where('status', 'active');
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        // Order by latest created (descending)
        $query->orderBy('created_at', 'desc');

        $customers = $query->paginate(15);
        return view('frontdesk.customers.index', compact('customers'));
    }

    public function create()
    {
        return view('frontdesk.customers.create');
    }

    public function store(Request $request)
    {
        $rules = [
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'password'   => 'required|string|min:8|confirmed',
            'mobile_num' => 'nullable|string|size:11|regex:/^[0-9]+$/|unique:customers,mobile_num',
            'birthdate'  => 'nullable|date',
            'gender'     => 'nullable|in:male,female,other',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        $customMessages = [
            'email.required' => 'The email address is required.',
            'email.email'    => 'Please enter a valid email address.',
            'email.unique'   => 'This email address is already taken.',
            'mobile_num.size'  => 'The mobile number must be exactly 11 digits.',
            'mobile_num.regex' => 'The mobile number must contain only digits.',
            'mobile_num.unique' => 'This mobile number is already registered.',
            'password.min'     => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
        ];

        $customAttributes = [
            'email'      => 'email address',
            'mobile_num' => 'mobile number',
            'password'   => 'password',
        ];

        $request->validate($rules, $customMessages, $customAttributes);

        $user = User::create([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'password' => Hash::make($request->password),
            'role' => 'customer',
            'status' => 'active',
        ]);

        $customerData = [
            'user_id' => $user->id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
        ];

        if ($request->hasFile('image')) {
            $imagePath = $request->file('image')->store('customers', 'public');
            $customerData['image'] = $imagePath;
        }

        Customer::create($customerData);

        return redirect()->route('frontdesk.customers.index')->with('success', 'Customer created.');
    }

    public function edit(Customer $customer)
    {
        return view('frontdesk.customers.edit', compact('customer'));
    }

    public function update(Request $request, Customer $customer)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email,' . $customer->user_id,
            'mobile_num' => [
                'nullable',
                'string',
                'size:11',
                'regex:/^[0-9]+$/',
                Rule::unique('customers', 'mobile_num')->ignore($customer->id),
            ],
            'birthdate' => 'nullable|date',
            'gender' => 'nullable|in:male,female,other',
            'password' => 'nullable|string|min:8|confirmed',
            'image' => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        $user = $customer->user;
        $user->update([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        // Prepare customer data array
        $customerData = [
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
        ];

        // Handle image upload
        if ($request->hasFile('image')) {
            // Delete old image if exists
            if ($customer->image) {
                \Illuminate\Support\Facades\Storage::disk('public')->delete($customer->image);
            }
            $customerData['image'] = $request->file('image')->store('customers', 'public');
        }

        // Update customer with all data (including image if uploaded)
        $customer->update($customerData);

        return redirect()->route('frontdesk.customers.index')->with('success', 'Customer updated.');
    }

    public function show(Customer $customer)
    {
        return view('frontdesk.customers.show', compact('customer'));
    }

    public function history(Customer $customer)
    {
        $appointments = $customer->appointments()
            ->with('services', 'employee')
            ->orderBy('appointment_date', 'desc')
            ->paginate(15);
        return view('frontdesk.customers.history', compact('customer', 'appointments'));
    }

    public function destroy(Customer $customer)
    {
        // Archive the customer (set user status to inactive)
        $customer->user->update(['status' => 'inactive']);
        return redirect()->route('frontdesk.customers.index')->with('success', 'Customer archived.');
    }

    public function archived(Request $request)
    {
        $query = Customer::with('user')
            ->whereHas('user', function ($q) {
                $q->where('status', 'inactive');
            });

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        $customers = $query->paginate(15);
        return view('frontdesk.customers.archived', compact('customers'));
    }

    public function restore($id)
    {
        $customer = Customer::findOrFail($id);
        $customer->user->update(['status' => 'active']);

        // Send reactivation email
        Mail::to($customer->user->email)->send(new AccountReactivatedMail($customer));

        return redirect()->route('frontdesk.customers.archived')->with('success', 'Customer restored and emailed about their account reactivation.');
    }
}
