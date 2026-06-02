<?php

namespace App\Http\Controllers\FrontDesk;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use App\Models\Customer;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Carbon\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $today = Carbon::today();

        // Basic stats
        $todayAppointments = Appointment::whereDate('appointment_date', $today)->count();
        $pendingValidations = Appointment::where('payment_status', 'for_validation')->count();

        // Today's revenue (from completed & paid appointments)
        $todayRevenue = Appointment::whereDate('appointment_date', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        // Upcoming appointments (next 7 days, excluding today)
        $upcomingAppointments = Appointment::with(['customer', 'employee'])
            ->whereBetween('appointment_date', [$today->copy()->addDay(), $today->copy()->addDays(7)])
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();

        // Weekly appointments chart (last 7 days, counts per day)
        $weeklyData = $this->getWeeklyAppointmentsData();

        // Percentage changes
        $todayAppointmentsChange = $this->getTodayAppointmentsChange();
        $todayRevenueChange = $this->getTodayRevenueChange();

        // Recent appointments (same as before but with pagination)
        $recentAppointments = Appointment::with(['customer', 'employee'])
            ->whereNull('deleted_at')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate(10);

        return view('frontdesk.dashboard', compact(
            'todayAppointments',
            'pendingValidations',
            'todayRevenue',
            'upcomingAppointments',
            'weeklyData',
            'todayAppointmentsChange',
            'todayRevenueChange',
            'recentAppointments'
        ));
    }

    private function getWeeklyAppointmentsData()
    {
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        $rawData = Appointment::whereBetween('appointment_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(appointment_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $counts = [];
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        foreach ($period as $date) {
            $labels[] = $date->format('D'); // Mon, Tue, etc.
            $counts[] = $rawData->get($date->format('Y-m-d'))->count ?? 0;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    private function getTodayAppointmentsChange()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayCount = Appointment::whereDate('appointment_date', $today)->count();
        $yesterdayCount = Appointment::whereDate('appointment_date', $yesterday)->count();

        return $this->calculateChange($todayCount, $yesterdayCount);
    }

    private function getTodayRevenueChange()
    {
        $today = Carbon::today();
        $yesterday = Carbon::yesterday();

        $todayRevenue = Appointment::whereDate('appointment_date', $today)
            ->where('payment_status', 'paid')
            ->sum('total_amount');
        $yesterdayRevenue = Appointment::whereDate('appointment_date', $yesterday)
            ->where('payment_status', 'paid')
            ->sum('total_amount');

        return $this->calculateChange($todayRevenue, $yesterdayRevenue);
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
