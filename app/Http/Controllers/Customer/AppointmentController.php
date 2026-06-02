<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use App\Models\Service;
use App\Models\Discount;
use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use App\Mail\AppointmentCancelledMail;
use Illuminate\Support\Facades\Mail;

class AppointmentController extends Controller
{
    public function create(Request $request)
    {
        $user = Auth::user();
        $customer = $user->customer;

        $services = Service::whereNull('deleted_at')->get();

        $discounts = Discount::all()->filter(function ($discount) use ($customer) {
            return $discount->isAvailableForCustomer($customer);
        });

        $loyaltyDiscountAvailable = $customer->loyalty_discount_available;

        return view('customer.appointments.create', compact('services', 'discounts', 'loyaltyDiscountAvailable'));
    }

    public function store(Request $request)
    {
        $rules = [
            'service_id' => 'required|exists:services,id',
            'appointment_date' => 'required|date|after:today', // strictly after today
            'appointment_time' => 'required|date_format:H:i',
            'payment_method' => 'required|in:cash,gcash',
            'discount_id' => 'nullable|exists:discounts,id',
            'gcash_reference' => 'nullable|string|max:255|required_if:payment_method,gcash|size:13|regex:/^\d+$/',
        ];

        $customMessages = [
            'gcash_reference.size' => 'The GCash reference number must be exactly 13 digits.',
            'gcash_reference.regex' => 'The GCash reference number may only contain digits.',
            'appointment_date.after_or_equal' => 'The appointment date must be today or a future date.',
        ];

        $request->validate($rules, $customMessages);

        $user = Auth::user();
        $customer = $user->customer;

        // Ensure customer exists (fallback)
        if (!$customer) {
            $customer = Customer::create([
                'user_id' => $user->id,
                'first_name' => $user->name ?? '',
                'last_name' => '',
            ]);
        }

        // Validate discount for this customer
        $discount = null;
        $selectedDiscountId = $request->discount_id;
        if ($selectedDiscountId) {
            $discount = Discount::find($selectedDiscountId);
            if (!$discount || !$discount->isAvailableForCustomer($customer)) {
                return back()->withErrors(['discount_id' => 'Selected discount is no longer available for this customer.'])->withInput();
            }
        }

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

        // Apply loyalty discount ONLY if the user checked the box and it's available
        $loyaltyDiscountUsed = false;
        if ($request->has('apply_loyalty') && $request->apply_loyalty && $customer->loyalty_discount_available) {
            $total *= 0.9; // 10% off
            $total = max(0, $total);
            $loyaltyDiscountUsed = true;
            $customer->update(['loyalty_discount_available' => false]);
        }

        $appointment = Appointment::create([
            'customer_id' => $customer->id,
            'employee_id' => null,
            'discount_id' => $selectedDiscountId,
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'status' => 'pending',
            'payment_method' => $request->payment_method,
            'payment_status' => $request->payment_method === 'cash' ? 'pending' : 'for_validation', // fixed enum
            'total_amount' => $total,
            'loyalty_discount_applied' => $loyaltyDiscountUsed,
        ]);

        $appointment->services()->attach($service->id, ['quantity' => 1]);

        if ($discount) {
            $discount->incrementUsageForCustomer($customer);
        }

        if ($request->payment_method === 'gcash' && $request->filled('gcash_reference')) {
            $appointment->payment()->create([
                'amount' => $total,
                'payment_method' => 'gcash',
                'reference_number' => $request->gcash_reference,
                'status' => 'pending',
            ]);
        }

        return redirect()->route('customer.appointments.show', $appointment)
            ->with('success', 'Appointment booked successfully! You will receive an email confirmation once a stylist is assigned.');
    }

    public function show(Appointment $appointment)
    {
        if ($appointment->customer_id !== Auth::user()->customer->id) {
            abort(403);
        }
        return view('customer.appointments.show', compact('appointment'));
    }

    public function index(Request $request)
    {
        $user = Auth::user();
        $customer = $user->customer;

        $query = $customer->appointments()->with('services');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->whereHas('services', function ($sq) use ($search) {
                    $sq->where('name', 'like', "%{$search}%");
                })->orWhere('appointment_date', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(10);
        return view('customer.appointments.index', compact('appointments'));
    }

    public function edit(Appointment $appointment)
    {
        if ($appointment->customer_id !== Auth::user()->customer->id) {
            abort(403);
        }

        $services = Service::whereNull('deleted_at')->get();
        $customer = $appointment->customer;
        $currentDiscountId = $appointment->discount_id;

        $discounts = Discount::all()->map(function ($discount) use ($customer, $currentDiscountId) {
            $customer_used = $discount->usageForCustomer($customer);
            $effective_used = ($discount->id == $currentDiscountId) ? $customer_used - 1 : $customer_used;
            $discount->customer_used = $customer_used;
            $discount->available = $discount->isAvailable() && (!$discount->usage_limit || $effective_used < $discount->usage_limit);
            return $discount;
        })->filter(fn($discount) => $discount->available);

        return view('customer.appointments.edit', compact('appointment', 'services', 'discounts'));
    }

    public function update(Request $request, Appointment $appointment)
    {
        if ($appointment->customer_id !== Auth::user()->customer->id) {
            abort(403);
        }

        $request->validate([
            'service_id' => 'required|exists:services,id',
            'appointment_date' => 'required|date|after:today',
            'appointment_time' => 'required',
            'payment_method' => 'required|in:cash,gcash',
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

        $service = Service::find($request->service_id);
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

        $appointment->update([
            'appointment_date' => $request->appointment_date,
            'appointment_time' => $request->appointment_time,
            'payment_method' => $request->payment_method,
            'discount_id' => $newDiscountId,
            'total_amount' => $total,
        ]);

        $appointment->services()->sync([$request->service_id]);

        // Adjust discount usage counts
        if ($oldDiscountId && $oldDiscountId != $newDiscountId) {
            Discount::find($oldDiscountId)->decrementUsageForCustomer($customer);
        }
        if ($newDiscountId && $oldDiscountId != $newDiscountId) {
            Discount::find($newDiscountId)->incrementUsageForCustomer($customer);
        }

        // Payment record
        if ($request->payment_method == 'gcash') {
            if ($appointment->payment) {
                $appointment->payment->update([
                    'amount' => $total,
                    'reference_number' => $request->gcash_reference,
                ]);
            } else {
                $appointment->payment()->create([
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

        return redirect()->route('customer.appointments.show', $appointment)
            ->with('success', 'Appointment updated.');
    }

    public function cancel(Request $request, Appointment $appointment)
    {
        if ($appointment->customer_id !== Auth::user()->customer->id) {
            abort(403);
        }

        $reason = $request->input('cancellation_reason');

        $customer = $appointment->customer;
        if ($appointment->discount_id) {
            $appointment->discount->decrementUsageForCustomer($customer);
        }
        $appointment->update([
            'status' => 'cancelled',
            'cancellation_reason' => $reason,
        ]);

        // Send cancellation email with reason
        Mail::to($appointment->customer->user->email)->send(new AppointmentCancelledMail($appointment, $reason));

        return redirect()->back()->with('success', 'Appointment cancelled.');
    }
}
