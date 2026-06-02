<?php

namespace App\Http\Controllers\Tech;

use App\Http\Controllers\Controller;
use App\Models\Appointment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Carbon\Carbon;
use Illuminate\Support\Facades\DB;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $user = Auth::user();
        $employee = $user->employee;

        if (!$employee) {
            return redirect()->route('home')->with('error', 'Employee profile not found.');
        }

        $today = Carbon::today();

        // Today's appointments
        $todayAppointments = $employee->appointments()
            ->whereDate('appointment_date', $today)
            ->count();

        // Upcoming appointments (future, excluding today, any status)
        $upcomingList = $employee->appointments()
            ->with('customer', 'services')
            ->where('appointment_date', '>', $today)
            ->orderBy('appointment_date')
            ->orderBy('appointment_time')
            ->limit(10)
            ->get();

        $upcomingCount = $upcomingList->count();

        // Completed this month
        $completedThisMonth = $employee->appointments()
            ->where('status', 'completed')
            ->whereMonth('appointment_date', now()->month)
            ->count();

        // Weekly appointments chart (last 7 days)
        $weeklyData = $this->getWeeklyData($employee->id);

        // Percentage change (completed this month vs last month)
        $completedChange = $this->getCompletedChange($employee->id);

        // Recent appointments (paginated)
        $recentAppointments = $employee->appointments()
            ->with('customer')
            ->orderBy('appointment_date', 'desc')
            ->orderBy('appointment_time', 'desc')
            ->paginate(10);

        return view('tech.dashboard', compact(
            'todayAppointments',
            'upcomingCount',
            'completedThisMonth',
            'upcomingList',
            'weeklyData',
            'completedChange',
            'recentAppointments'
        ));
    }

    private function getWeeklyData($employeeId)
    {
        $startDate = Carbon::today()->subDays(6);
        $endDate = Carbon::today();

        $rawData = Appointment::where('employee_id', $employeeId)
            ->whereBetween('appointment_date', [$startDate, $endDate])
            ->select(DB::raw('DATE(appointment_date) as date'), DB::raw('COUNT(*) as count'))
            ->groupBy('date')
            ->orderBy('date')
            ->get()
            ->keyBy('date');

        $labels = [];
        $counts = [];
        $period = Carbon::parse($startDate)->daysUntil($endDate);
        foreach ($period as $date) {
            $labels[] = $date->format('D');
            $counts[] = $rawData->get($date->format('Y-m-d'))->count ?? 0;
        }

        return ['labels' => $labels, 'counts' => $counts];
    }

    private function getCompletedChange($employeeId)
    {
        $currentMonth = Appointment::where('employee_id', $employeeId)
            ->where('status', 'completed')
            ->whereMonth('appointment_date', Carbon::now()->month)
            ->count();

        $lastMonth = Appointment::where('employee_id', $employeeId)
            ->where('status', 'completed')
            ->whereMonth('appointment_date', Carbon::now()->subMonth()->month)
            ->count();

        if ($lastMonth == 0) {
            return ['percentage' => $currentMonth > 0 ? 100 : 0, 'direction' => $currentMonth > 0 ? 'up' : 'neutral', 'is_positive' => $currentMonth > 0];
        }

        $change = (($currentMonth - $lastMonth) / $lastMonth) * 100;
        return [
            'percentage' => round(abs($change), 1),
            'direction' => $change > 0 ? 'up' : ($change < 0 ? 'down' : 'neutral'),
            'is_positive' => $change > 0
        ];
    }
}
