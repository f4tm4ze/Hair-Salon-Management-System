<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use Illuminate\Support\Facades\Auth;

class HistoryController extends Controller
{
    public function index()
    {
        $user = Auth::user();
        $employee = $user->employee;

        $appointments = $employee->appointments()
            ->with('customer', 'services')
            ->where('status', 'completed')
            ->orderBy('appointment_date', 'desc')
            ->paginate(15);

        return view('tech.history.index', compact('appointments'));
    }
}
