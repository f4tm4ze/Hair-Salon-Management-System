<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Payment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReportController extends Controller
{
    public function index(Request $request)
    {
        $startDate = $request->get('start_date', now()->startOfMonth()->toDateString());
        $endDate   = $request->get('end_date', now()->toDateString());
        $paymentMethod = $request->get('payment_method'); // null, 'cash', or 'gcash'

        // Base query: completed appointments with paid payment_status (to count actual revenue)
        $query = Appointment::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('appointment_date', [$startDate, $endDate]);

        if ($paymentMethod) {
            $query->where('payment_method', $paymentMethod);
        }

        // Total sales and appointments count
        $totalSales = $query->sum('total_amount');
        $totalAppointments = $query->count();
        $averageOrderValue = $totalAppointments > 0 ? $totalSales / $totalAppointments : 0;

        // Sales grouped by date
        $salesData = $query->select(
            DB::raw('DATE(appointment_date) as date'),
            DB::raw('COUNT(*) as count'),
            DB::raw('SUM(total_amount) as total')
        )
            ->groupBy('date')
            ->orderBy('date', 'asc')
            ->get();

        // Payment method breakdown (ignores current filter if we want overall, but we should apply the same date range)
        $paymentMethodBreakdown = Appointment::where('status', 'completed')
            ->where('payment_status', 'paid')
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->select('payment_method', DB::raw('COUNT(*) as count'), DB::raw('SUM(total_amount) as total'))
            ->groupBy('payment_method')
            ->get();

        return view('admin.reports.index', compact(
            'startDate',
            'endDate',
            'totalSales',
            'totalAppointments',
            'averageOrderValue',
            'salesData',
            'paymentMethodBreakdown'
        ));
    }
}
