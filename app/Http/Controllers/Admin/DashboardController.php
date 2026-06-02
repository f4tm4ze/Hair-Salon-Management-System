<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use App\Models\Employee;
use App\Models\Product;
use App\Models\Service;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        // Date range (default: last 30 days)
        $range = $request->get('range', '30days');
        $startDate = $this->getStartDate($range);
        $endDate = Carbon::now();

        // Basic stats
        $totalAppointments = Appointment::count();
        $totalCustomers = Customer::count();
        $lowStockProducts = Product::where('quantity', '<=', 10)->count();

        $todayRevenueChange = $this->getTodayRevenueChange();
        $totalAppointmentsChange = $this->getTotalAppointmentsChange();
        $totalCustomersChange = $this->getTotalCustomersChange();

        // Revenue stats
        $todayRevenue = Appointment::whereDate('appointment_date', Carbon::today())
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Revenue over time (for chart)
        $revenueData = $this->getRevenueData($startDate, $endDate, $range);

        // Percentage change in revenue (vs previous period)
        $revenueChange = $this->getRevenueChange($range);

        // Top services
        $topServices = $this->getTopServices($startDate, $endDate);

        // Staff performance
        $staffPerformance = $this->getStaffPerformance($startDate, $endDate);

        // Customer retention (new vs returning within period)
        $customerRetention = $this->getCustomerRetention($startDate, $endDate);

        // Recent appointments
        $recentAppointments = Appointment::with(['customer', 'employee'])
            ->latest('appointment_date')
            ->limit(10)
            ->get();

        return view('admin.dashboard', compact(
            'totalAppointments',
            'totalCustomers',
            'lowStockProducts',
            'todayRevenue',
            'revenueData',
            'revenueChange',
            'topServices',
            'staffPerformance',
            'customerRetention',
            'recentAppointments',
            'range',
            'todayRevenueChange',
            'totalAppointmentsChange',
            'totalCustomersChange'  // add these
        ));
    }

    private function getStartDate($range)
    {
        return match ($range) {
            '7days'   => Carbon::now()->subDays(7),
            '30days'  => Carbon::now()->subDays(30),
            '90days'  => Carbon::now()->subDays(90),
            'month'   => Carbon::now()->subMonth(),
            'year'    => Carbon::now()->subYear(),
            default   => Carbon::now()->subDays(30),
        };
    }

    private function getRevenueData($startDate, $endDate, $range)
    {
        // Group by day for short ranges, by month for longer ranges
        $groupBy = in_array($range, ['7days', '30days']) ? 'day' : 'month';

        $query = Appointment::whereBetween('appointment_date', [$startDate, $endDate])
            ->where('payment_status', 'paid')
            ->select(
                DB::raw("DATE_FORMAT(appointment_date, '%Y-%m-%d') as date"),
                DB::raw('SUM(total_amount) as revenue')
            )
            ->groupBy('date')
            ->orderBy('date');

        $results = $query->get();

        $labels = [];
        $values = [];

        if ($groupBy == 'day') {
            $period = Carbon::parse($startDate)->daysUntil($endDate);
            foreach ($period as $date) {
                $labels[] = $date->format('M d');
                $revenue = $results->firstWhere('date', $date->format('Y-m-d'));
                $values[] = $revenue ? $revenue->revenue : 0;
            }
        } else {
            // Group by month
            $months = $results->groupBy(function ($item) {
                return Carbon::parse($item->date)->format('Y-m');
            });
            foreach ($months as $month => $data) {
                $labels[] = Carbon::parse($month)->format('M Y');
                $values[] = $data->sum('revenue');
            }
        }

        return ['labels' => $labels, 'values' => $values];
    }

    private function getRevenueChange($range)
    {
        $currentStart = $this->getStartDate($range);
        $currentEnd = Carbon::now();
        $previousEnd = clone $currentStart;
        $previousStart = clone $currentStart;

        if ($range == '7days') {
            $previousStart->subDays(7);
            $previousEnd->subDay();
        } elseif ($range == '30days') {
            $previousStart->subDays(30);
            $previousEnd->subDay();
        } elseif ($range == 'month') {
            $previousStart->subMonth();
            $previousEnd->subDay();
        } else {
            $previousStart->subDays(30);
            $previousEnd->subDay();
        }

        $currentRevenue = Appointment::whereBetween('appointment_date', [$currentStart, $currentEnd])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $previousRevenue = Appointment::whereBetween('appointment_date', [$previousStart, $previousEnd])
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $change = $previousRevenue > 0 ? (($currentRevenue - $previousRevenue) / $previousRevenue) * 100 : 0;

        return [
            'percentage' => round($change, 1),
            'direction' => $change >= 0 ? 'up' : 'down',
            'current' => $currentRevenue,
            'previous' => $previousRevenue
        ];
    }

    private function getTopServices($startDate, $endDate)
    {
        return Service::select('services.id', 'services.name', 'services.price')
            ->selectRaw('COUNT(appointment_service.appointment_id) as total_uses')
            ->selectRaw('SUM(appointment_service.quantity * services.price) as total_revenue')
            ->join('appointment_service', 'services.id', '=', 'appointment_service.service_id')
            ->join('appointments', 'appointment_service.appointment_id', '=', 'appointments.id')
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
            ->where('appointments.payment_status', 'paid')
            ->groupBy('services.id', 'services.name', 'services.price')
            ->orderByDesc('total_uses')
            ->limit(5)
            ->get();
    }

    private function getStaffPerformance($startDate, $endDate)
    {
        return Employee::select('employees.id', 'employees.first_name', 'employees.last_name')
            ->selectRaw('COUNT(appointments.id) as appointments_completed')
            ->selectRaw('SUM(appointments.total_amount) as revenue_generated')
            ->leftJoin('appointments', 'employees.id', '=', 'appointments.employee_id')
            ->whereBetween('appointments.appointment_date', [$startDate, $endDate])
            ->where('appointments.status', 'completed')
            ->where('appointments.payment_status', 'paid')
            ->groupBy('employees.id', 'employees.first_name', 'employees.last_name')
            ->orderByDesc('appointments_completed')
            ->get();
    }

    private function getCustomerRetention($startDate, $endDate)
    {
        // New customers: registered within the period
        $newCustomers = Customer::whereBetween('created_at', [$startDate, $endDate])->count();

        // Returning customers: had at least one appointment before the period and also within period
        $returningCustomers = Customer::whereHas('appointments', function ($q) use ($startDate, $endDate) {
            $q->whereBetween('appointment_date', [$startDate, $endDate]);
        })
            ->whereHas('appointments', function ($q) use ($startDate) {
                $q->where('appointment_date', '<', $startDate);
            })
            ->count();

        $totalActive = $newCustomers + $returningCustomers;

        return [
            'new' => $newCustomers,
            'returning' => $returningCustomers,
            'total' => $totalActive,
            'new_percentage' => $totalActive > 0 ? round(($newCustomers / $totalActive) * 100, 1) : 0,
            'returning_percentage' => $totalActive > 0 ? round(($returningCustomers / $totalActive) * 100, 1) : 0,
        ];
    }

    private function getTodayRevenueChange()
    {
        $today = Carbon::today();
        $lastWeekSameDay = Carbon::today()->subWeek();

        $todayRevenue = Appointment::whereDate('appointment_date', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        $lastWeekRevenue = Appointment::whereDate('appointment_date', $lastWeekSameDay)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        return $this->calculateChange($todayRevenue, $lastWeekRevenue);
    }

    private function getTotalAppointmentsChange()
    {
        $currentMonth = Appointment::whereMonth('appointment_date', Carbon::now()->month)->count();
        $previousMonth = Appointment::whereMonth('appointment_date', Carbon::now()->subMonth()->month)->count();

        return $this->calculateChange($currentMonth, $previousMonth);
    }

    private function getTotalCustomersChange()
    {
        $currentMonth = Customer::whereMonth('created_at', Carbon::now()->month)->count();
        $previousMonth = Customer::whereMonth('created_at', Carbon::now()->subMonth()->month)->count();

        return $this->calculateChange($currentMonth, $previousMonth);
    }

    private function calculateChange($current, $previous)
    {
        if ($previous == 0) {
            return ['percentage' => $current > 0 ? 100 : 0, 'direction' => $current > 0 ? 'up' : 'neutral', 'is_positive' => $current > 0];
        }
        $change = (($current - $previous) / $previous) * 100;
        return [
            'percentage' => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'is_positive' => $change > 0
        ];
    }
}
