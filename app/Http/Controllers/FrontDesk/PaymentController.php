<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function index()
    {
        $appointments = Appointment::with('customer', 'payment')
            ->where('payment_status', 'for_validation')
            ->whereNull('deleted_at')
            ->paginate(15);
        return view('frontdesk.payments.index', compact('appointments'));
    }

    public function validatePayment(Appointment $appointment)
    {
        $appointment->update(['payment_status' => 'paid']);
        if ($appointment->payment) {
            $appointment->payment->update(['status' => 'validated', 'validated_at' => now()]);
        }
        return redirect()->back()->with('success', 'Payment is validated and marked as paid.');
    }
}
