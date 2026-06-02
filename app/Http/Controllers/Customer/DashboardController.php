<?php

namespace App\Http\Controllers\Customer;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;
use Illuminate\Http\Request; // Add this line

class DashboardController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $customer = $user->customer;

        $completedVisits = $customer->appointments()->where('status', 'completed')->count();
        $visitsToNextDiscount = 3 - ($completedVisits % 3);
        $progressPercent = ($completedVisits % 3) * 33.33;
        $progressPercent = min(100, max(0, $progressPercent));

        $recentAppointments = $customer->appointments()
            ->with('services')
            ->orderBy('appointment_date', 'desc')
            ->limit(3)
            ->get();

        $totalAppointments = $customer->appointments()->count();
        $cancelled = $customer->appointments()->where('status', 'cancelled')->count();
        $totalPaid = $customer->appointments()->where('payment_status', 'paid')->sum('total_amount');

        return view('customer.dashboard', compact(
            'customer',
            'completedVisits',
            'visitsToNextDiscount',
            'progressPercent',
            'recentAppointments',
            'totalAppointments',
            'cancelled',
            'totalPaid'
        ));
    }

    public function deactivate(Request $request)
    {
        $user = Auth::user();

        // Deactivate the user account
        $user->update(['status' => 'inactive']);

        // Logout
        Auth::logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deactivated. Email lr.hairsalon.ms@gmail.com for reactivation.');
    }
}
