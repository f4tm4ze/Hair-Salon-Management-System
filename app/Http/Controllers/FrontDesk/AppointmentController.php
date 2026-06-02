<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Discount;
use App\Models\Employee;
use App\Models\Payment;
use App\Models\Service;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\DB;
use App\Mail\AppointmentConfirmedMail;
use App\Mail\AppointmentCancelledMail;
use App\Mail\LoyaltyDiscountEarnedMail;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $query = Appointment::with('customer', 'employee', 'services')
            ->whereNull('deleted_at'); // exclude soft-deleted

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(15);
        return view('frontdesk.appointments.index', compact('appointments'));
    }

    public function create()
    {
        $customers = Customer::whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        $services = Service::all();
        $employees = Employee::whereHas('user', fn($q) => $q->where('role', 'tech'))->with('jobRole')->get();
        $discounts = Discount::all()->filter(fn($d) => $d->isAvailable()); // global

        return view('frontdesk.appointments.create', compact('customers', 'services', 'employees', 'discounts'));
    }

    public function store(Request $request)
    {
        $rules = [
            'service_id' => 'required|exists:services,id',
            'employee_id' => 'nullable|exists:employees,id',
            'appointment_date' => 'required|date|after_or_equal:today', // allow today
            'appointment_time' => 'required|date_format:H:i',
            'payment_method' => 'required|in:cash,gcash',
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'discount_id' => 'nullable|exists:discounts,id',
            'gcash_reference' => 'nullable|string|max:255|required_if:payment_method,gcash|size:13|regex:/^\d+$/',
        ];

        if ($request->has('newCustomer') && $request->newCustomer) {
            $rules['first_name'] = 'required|string|max:255';
            $rules['last_name']  = 'required|string|max:255';
            $rules['new_email']  = 'required|email|unique:users,email';
            $rules['new_mobile'] = 'required|string|size:11|regex:/^[0-9]+$/|unique:customers,mobile_num';
            $rules['new_password'] = 'required|string|min:8|confirmed';
        } else {
            $rules['customer_id'] = 'required|exists:customers,id';
        }

        $customMessages = [
            'new_email.email' => 'The email must be a valid email address.',
            'new_email.unique' => 'This email address is already taken.',
            'new_mobile.size' => 'The mobile number must be exactly 11 digits.',
            'new_mobile.regex' => 'The mobile number must be exactly 11 digits.',
            'new_mobile.unique' => 'This mobile number is already registered.',
            'new_password.min' => 'The password must be at least 8 characters.',
            'new_password.confirmed' => 'The password confirmation does not match.',
            'gcash_reference.size' => 'The GCash reference number must be exactly 13 digits.',
            'gcash_reference.regex' => 'The GCash reference number may only contain digits.',
        ];

        $customAttributes = [
            'new_email' => 'email',
            'new_mobile' => 'mobile number',
            'new_password' => 'password',
        ];

        $request->validate($rules, $customMessages, $customAttributes);

        // 2. Prepare variables for transaction
        $customer = null;
        $selectedDiscountId = $request->discount_id;
        $total = 0;
        $loyaltyDiscountUsed = false;

        try {
            DB::transaction(function () use ($request, &$customer, &$selectedDiscountId, &$total, &$loyaltyDiscountUsed) {
                // ---- Create or fetch customer ----
                if ($request->has('newCustomer') && $request->newCustomer) {
                    $user = User::create([
                        'name'     => $request->first_name . ' ' . $request->last_name,
                        'email'    => $request->new_email,
                        'password' => Hash::make($request->new_password),
                        'role'     => 'customer',
                        'status'   => 'active',
                    ]);

                    $customer = Customer::create([
                        'user_id'    => $user->id,
                        'first_name' => $request->first_name,
                        'last_name'  => $request->last_name,
                        'mobile_num' => $request->new_mobile,
                    ]);
                } else {
                    $customer = Customer::findOrFail($request->customer_id);
                }

                // ---- Validate discount availability ----
                $discount = null;
                if ($selectedDiscountId) {
                    $discount = Discount::find($selectedDiscountId);
                    if (!$discount || !$discount->isAvailableForCustomer($customer)) {
                        throw new \Exception('Selected discount is no longer available for this customer.');
                    }
                }

                // ---- Calculate total ----
                $service = Service::findOrFail($request->service_id);
                $total = $service->price;

                if ($discount) {
                    if ($discount->type == 'percentage') {
                        $total -= $total * ($discount->value / 100);
                    } else {
                        $total -= $discount->value;
                    }
                    $total = max(0, $total);
                }

                // ---- Loyalty discount ----
                if ($customer->loyalty_discount_available) {
                    $total *= 0.9;      // 10% off
                    $total = max(0, $total);
                    $loyaltyDiscountUsed = true;
                    $customer->update(['loyalty_discount_available' => false]);
                }

                // ---- Create appointment ----
                $appointment = Appointment::create([
                    'customer_id' => $customer->id,
                    'employee_id' => $request->employee_id,
                    'discount_id' => $selectedDiscountId,
                    'appointment_date' => $request->appointment_date,
                    'appointment_time' => $request->appointment_time,
                    'status' => $request->employee_id ? 'confirmed' : $request->status,
                    'payment_method' => $request->payment_method,
                    'payment_status' => $request->payment_method == 'cash' ? 'pending' : 'for_validation',
                    'total_amount' => $total,
                    'loyalty_discount_applied' => $loyaltyDiscountUsed,
                ]);

                // ---- Attach service ----
                $appointment->services()->attach($request->service_id);

                // ---- Increase discount usage ----
                if ($discount) {
                    $discount->incrementUsageForCustomer($customer);
                }

                // ---- Create payment record for GCash ----
                if ($request->payment_method == 'gcash' && $request->filled('gcash_reference')) {
                    Payment::create([
                        'appointment_id' => $appointment->id,
                        'amount' => $total,
                        'payment_method' => 'gcash',
                        'reference_number' => $request->gcash_reference,
                        'status' => 'pending',
                    ]);
                }
            });
        } catch (\Exception $e) {
            return redirect()->back()
                ->withInput()
                ->with('error', $e->getMessage());
        }

        return redirect()->route('frontdesk.appointments.index')
            ->with('success', 'Appointment created.');
    }

    public function show(Appointment $appointment)
    {
        // Ensure it's not soft-deleted
        if ($appointment->deleted_at) {
            abort(404);
        }
        $appointment->load('customer.user', 'employee', 'services', 'payment', 'discount');
        return view('frontdesk.appointments.show', compact('appointment'));
    }

    public function assignForm(Appointment $appointment)
    {
        if ($appointment->deleted_at) abort(404);
        $employees = Employee::whereHas('user', function ($q) {
            $q->where('role', 'tech');
        })->with('user', 'jobRole')->get();
        return view('frontdesk.appointments.assign', compact('appointment', 'employees'));
    }

    public function edit(Appointment $appointment)
    {
        $customers = Customer::whereHas('user', fn($q) => $q->where('status', 'active'))->get();
        $services = Service::all();
        $employees = Employee::whereHas('user', fn($q) => $q->where('role', 'tech'))->with('jobRole')->get();
        $currentDiscountId = $appointment->discount_id;

        $discounts = Discount::all()->map(function ($discount) use ($appointment, $currentDiscountId) {
            $customer_used = $discount->usageForCustomer($appointment->customer);
            $effective_used = ($discount->id == $currentDiscountId) ? $customer_used - 1 : $customer_used;
            $discount->customer_used = $customer_used;
            $discount->available = $discount->isAvailable() && (!$discount->usage_limit || $effective_used < $discount->usage_limit);
            return $discount;
        })->filter(fn($discount) => $discount->available);

        return view('frontdesk.appointments.edit', compact('appointment', 'customers', 'services', 'employees', 'discounts'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        $request->validate([
            'customer_id' => 'required|exists:customers,id',
            'service_id' => 'required|exists:services,id',
            'employee_id' => 'nullable|exists:employees,id',
            'appointment_date' => 'required|date|after_or_equal:today',
            'appointment_time' => 'required|date_format:H:i',
            'payment_method' => 'required|in:cash,gcash',
            'status' => 'required|in:pending,confirmed,completed,cancelled',
            'discount_id' => 'nullable|exists:discounts,id',
            'gcash_reference' => 'nullable|string|max:255|required_if:payment_method,gcash|size:13|regex:/^\d+$/',
        ]);

        $customer = $appointment->customer;
        $oldDiscountId = $appointment->discount_id;
        $newDiscountId = $request->discount_id;

        // Validate new discount, accounting for current appointment if same discount
        if ($newDiscountId) {
            $newDiscount = Discount::find($newDiscountId);
            if (!$newDiscount) {
                return back()->withErrors(['discount_id' => 'Discount not found.'])->withInput();
            }

            $currentUsage = $newDiscount->usageForCustomer($customer);
            // If the discount is unchanged, subtract 1 from the usage count when checking limit
            $effectiveUsage = ($newDiscountId == $oldDiscountId) ? $currentUsage - 1 : $currentUsage;
            $isAvailable = $newDiscount->isAvailable() && (!$newDiscount->usage_limit || $effectiveUsage < $newDiscount->usage_limit);

            if (!$isAvailable) {
                return back()->withErrors(['discount_id' => 'Selected discount is no longer available for this customer.'])->withInput();
            }
        }

        // Recalculate total
        $service = Service::findOrFail($request->service_id);
        $total = $service->price;

        if ($newDiscountId) {
            $discount = Discount::find($newDiscountId);
            if ($discount->type == 'percentage') {
                $total -= $total * ($discount->value / 100);
            } else {
                $total -= $discount->value;
            }
            $total = max(0, $total);
        }

        // Determine the new status
        $newStatus = $request->status;
        // If employee is removed (null) and the appointment was confirmed, change status to pending
        if ($request->employee_id === null && $appointment->status === 'confirmed') {
            $newStatus = 'pending';
        }

        $appointment->update([
            'customer_id' => $request->customer_id,
            'employee_id' => $request->employee_id,
            'discount_id' => $newDiscountId,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status' => $newStatus,  // use the possibly overridden status
            'payment_method' => $request->payment_method,
            'total_amount' => $total,
        ]);

        $appointment->services()->sync([$request->service_id]);

        // Adjust discount usage counts (only if discount changed)
        if ($oldDiscountId && $oldDiscountId != $newDiscountId) {
            Discount::find($oldDiscountId)->decrementUsageForCustomer($customer);
        }
        if ($newDiscountId && $oldDiscountId != $newDiscountId) {
            Discount::find($newDiscountId)->incrementUsageForCustomer($customer);
        }

        // Payment handling 
        if ($request->payment_method == 'gcash') {
            if ($appointment->payment) {
                $appointment->payment->update([
                    'amount' => $total,
                    'reference_number' => $request->gcash_reference,
                ]);
            } else {
                Payment::create([
                    'appointment_id' => $appointment->id,
                    'amount' => $total,
                    'payment_method' => 'gcash',
                    'reference_number' => $request->gcash_reference,
                    'status' => 'pending',
                ]);
            }
        } else {
            if ($appointment->payment) {
                $appointment->payment->delete();
            }
        }

        return redirect()->route('frontdesk.appointments.show', $appointment)->with('success', 'Appointment updated.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        $reason = $request->input('cancellation_reason');

        // Save reason and update status
        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Restore discount usage if any
        if ($appointment->discount_id) {
            $appointment->discount->decrementUsageForCustomer($appointment->customer);
        }

        // Send cancellation email with reason
        Mail::to($appointment->customer->user->email)->send(new AppointmentCancelledMail($appointment, $reason));

        return redirect()->back()->with('success', 'Appointment cancelled and customer is emailed about appointment cancellation.');
    }

    public function assign(Request $request, Appointment $appointment)
    {
        $request->validate(['employee_id' => 'required|exists:employees,id']);
        $appointment->update([
            'employee_id' => $request->employee_id,
            'status' => 'confirmed',
        ]);

        // Send confirmation email
        Mail::to($appointment->customer->user->email)->send(new AppointmentConfirmedMail($appointment));

        return redirect()->route('frontdesk.appointments.show', $appointment)
            ->with('success', 'Stylist assigned and customer is emailed about appointment confirmation.');
    }

    public function markPaid(Appointment $appointment)
    {
        $appointment->update(['payment_status' => 'paid']);
        if ($appointment->payment) {
            $appointment->payment->update(['status' => 'validated', 'validated_at' => now()]);
        }
        return redirect()->back()->with('success', 'Appointment marked as paid.');
    }

    public function complete(Appointment $appointment)
    {
        // Load services with their products (eager load)
        $appointment->load('services.products');

        try {
            DB::transaction(function () use ($appointment) {
                // 1. Deduct inventory – throws exception if insufficient stock
                $appointment->deductInventory();

                // 2. Update appointment status
                $appointment->update(['status' => 'completed']);

                // 3. Loyalty discount logic (your existing code)
                $customer = $appointment->customer;
                $completedVisits = $customer->appointments()->where('status', 'completed')->count();

                if ($completedVisits % 3 == 0) {
                    $customer->update(['loyalty_discount_available' => true]);
                    // Send notification email
                    Mail::to($customer->user->email)->send(new LoyaltyDiscountEarnedMail($customer));
                }
            });

            return redirect()->back()->with('success', 'Appointment marked as completed and inventory updated.');
        } catch (\Exception $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }
    }
}
