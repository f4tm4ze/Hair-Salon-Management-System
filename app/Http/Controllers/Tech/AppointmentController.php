<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AppointmentController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;

        $query = $employee->appointments()->with('customer', 'services')
            ->where('status', '!=', 'completed'); // Always exclude completed

        if ($request->filled('search')) {
            $search = $request->search;
            $query->whereHas('customer', function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%");
            });
        }

        if ($request->filled('status') && $request->status !== 'completed') {
            $query->where('status', $request->status);
        }

        $appointments = $query->orderBy('appointment_date', 'desc')->paginate(15);
        return view('tech.appointments.index', compact('appointments'));
    }

    public function show(Appointment $appointment)
    {
        // Ensure the appointment belongs to the authenticated tech
        if ($appointment->employee_id !== Auth::user()->employee->id) {
            abort(403);
        }
        $appointment->load('customer.user', 'services');
        return view('tech.appointments.show', compact('appointment'));
    }

    public function complete(Request $request, Appointment $appointment)
    {
        if ($appointment->employee_id !== Auth::user()->employee->id) {
            abort(403);
        }
        $appointment->update(['status' => 'completed']);
        return redirect()->back()->with('success', 'Appointment marked as completed.');
    }
}
