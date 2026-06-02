<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Employee;
use App\Models\JobRole;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Mail\EmployeeInvitationMail;
use Illuminate\Support\Facades\Mail;
use Illuminate\Validation\Rule;

class EmployeeController extends Controller
{
    public function index(Request $request)
    {
        $query = Employee::with('user', 'jobRole');

        // Status filter
        if ($request->filled('status')) {
            $query->whereHas('user', function ($q) use ($request) {
                $q->where('status', $request->status);
            });
        } else {
            // Default: show active and invited (but not inactive/archived)
            $query->whereHas('user', function ($q) {
                $q->whereIn('status', ['active', 'invited']);
            });
        }

        // Job Role filter (by job role title, not user role)
        if ($request->filled('job_role')) {
            $query->whereHas('jobRole', function ($q) use ($request) {
                $q->where('title', $request->job_role);
            });
        }

        // Search filter
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        // Order by latest created (descending)
        $query->orderBy('created_at', 'desc');

        $employees = $query->paginate(10);
        return view('admin.employees.index', compact('employees'));
    }

    public function create()
    {
        $jobRoles = JobRole::all();
        return view('admin.employees.create', compact('jobRoles'));
    }

    public function store(Request $request)
    {
        $request->validate([
            'first_name' => 'required|string|max:255',
            'last_name'  => 'required|string|max:255',
            'email'      => 'required|email|unique:users,email',
            'personal_email' => 'required|email|different:email',
            'password'   => 'required|string|min:8|confirmed',
            'job_role_id' => 'required|exists:job_roles,id',
            'mobile_num' => 'required|string|size:11|regex:/^[0-9]+$/|unique:employees,mobile_num',
            'birthdate'  => 'nullable|date',
            'gender'     => 'nullable|in:male,female,other',
            'hire_date'  => 'nullable|date',
            'image'      => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ]);

        // Create user (work email for login)
        $user = User::create([
            'name'     => $request->first_name . ' ' . $request->last_name,
            'email'    => $request->email,
            'password' => Hash::make($request->password),
            'role'     => $this->mapJobRoleToUserRole($request->job_role_id),
            'status'   => 'invited',
        ]);

        // Create employee record with personal_email
        $employeeData = [
            'user_id'        => $user->id,
            'personal_email' => $request->personal_email,
            'job_role_id'    => $request->job_role_id,
            'first_name'     => $request->first_name,
            'last_name'      => $request->last_name,
            'mobile_num'     => $request->mobile_num,
            'birthdate'      => $request->birthdate,
            'gender'         => $request->gender,
            'hire_date'      => $request->hire_date,
        ];

        if ($request->hasFile('image')) {
            $employeeData['image'] = $request->file('image')->store('employees', 'public');
        }

        $employee = Employee::create($employeeData);

        // Generate invitation token and send email to personal_email
        $user->generateInvitationToken();
        Mail::to($employee->personal_email)->send(new EmployeeInvitationMail($user, $employee->jobRole->title));

        return redirect()->route('admin.employees.index')
            ->with('success', 'Employee invited. They will receive an email at their personal address to set their password.');
    }

    public function edit(Employee $employee)
    {
        $jobRoles = JobRole::all();
        return view('admin.employees.edit', compact('employee', 'jobRoles'));
    }

    public function update(Request $request, Employee $employee)
    {
        $rules = [
            'first_name'     => 'required|string|max:255',
            'last_name'      => 'required|string|max:255',
            'email'          => 'required|email|unique:users,email,' . $employee->user_id, // was missing ignore
            'personal_email' => 'required|email|different:email',
            'password'       => 'nullable|string|min:8|confirmed', // optional in update
            'job_role_id'    => 'required|exists:job_roles,id',
            'mobile_num' => [
                'required',
                'string',
                'size:11',
                'regex:/^[0-9]+$/',
                Rule::unique('employees', 'mobile_num')->ignore($employee->id),
            ],
            'birthdate'      => 'nullable|date',
            'gender'         => 'nullable|in:male,female,other',
            'hire_date'      => 'nullable|date',
            'image'          => 'nullable|image|mimes:jpeg,png,jpg,gif|max:2048',
        ];

        $customMessages = [
            'email.required'     => 'The work email address is required.',
            'email.email'        => 'Please enter a valid work email address.',
            'email.unique'       => 'This work email is already taken.',
            'personal_email.required' => 'The personal email address is required.',
            'personal_email.email'     => 'Please enter a valid personal email address.',
            'personal_email.different' => 'The personal email must be different from the work email.',
            'mobile_num.size'    => 'The mobile number must be exactly 11 digits.',
            'mobile_num.regex'   => 'The mobile number must contain only digits.',
            'mobile_num.unique' => 'This mobile number is already registered to another employee.',
            'password.min'       => 'The password must be at least 8 characters.',
            'password.confirmed' => 'The password confirmation does not match.',
            'job_role_id.required' => 'Please select a job role.',
        ];

        $customAttributes = [
            'email'          => 'work email',
            'personal_email' => 'personal email',
            'mobile_num'     => 'mobile number',
            'password'       => 'password',
        ];

        $request->validate($rules, $customMessages, $customAttributes);

        $user = $employee->user;
        $user->update([
            'name' => $request->first_name . ' ' . $request->last_name,
            'email' => $request->email,
            'role' => $this->mapJobRoleToUserRole($request->job_role_id),
        ]);

        if ($request->filled('password')) {
            $user->update(['password' => Hash::make($request->password)]);
        }

        $employeeData = [
            'job_role_id' => $request->job_role_id,
            'first_name' => $request->first_name,
            'last_name' => $request->last_name,
            'mobile_num' => $request->mobile_num,
            'birthdate' => $request->birthdate,
            'gender' => $request->gender,
            'hire_date' => $request->hire_date,
        ];

        if ($request->hasFile('image')) {
            // Delete old image
            if ($employee->image) {
                Storage::disk('public')->delete($employee->image);
            }
            $imagePath = $request->file('image')->store('employees', 'public');
            $employeeData['image'] = $imagePath;
        }

        $employee->update($employeeData);

        return redirect()->route('admin.employees.index')->with('success', 'Employee updated.');
    }

    public function destroy(Employee $employee)
    {
        $employee->user->update(['status' => 'inactive']);
        return redirect()->route('admin.employees.index')->with('success', 'Employee archived.');
    }

    public function archived(Request $request)
    {
        $query = Employee::whereHas('user', function ($q) {
            $q->where('status', 'inactive');
        })->with('user', 'jobRole');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($uq) use ($search) {
                        $uq->where('email', 'like', "%{$search}%");
                    });
            });
        }

        $employees = $query->paginate(10);
        return view('admin.employees.archived', compact('employees'));
    }

    public function restore($id)
    {
        $employee = Employee::findOrFail($id);
        $user = $employee->user;

        if ($user->invitation_accepted_at === null) {
            // Never accepted → was a cancelled invitation
            $user->update(['status' => 'invited']);
            $user->generateInvitationToken();
            Mail::to($employee->personal_email)->send(new EmployeeInvitationMail($user, $employee->jobRole->title));
            $message = 'Invitation restored. A new invitation email has been sent.';
        } else {
            // Previously active employee
            $user->update(['status' => 'active']);
            $message = 'Employee restored.';
        }

        return redirect()->route('admin.employees.archived')->with('success', $message);
    }

    private function mapJobRoleToUserRole($jobRoleId)
    {
        $role = JobRole::find($jobRoleId);
        switch ($role->title) {
            case 'Front Desk':
                return 'frontdesk';
            case 'Hair Stylist':
            case 'Nail Technician':
                return 'tech';
            case 'Manager':
                return 'manager';
            default:
                return 'employee';
        }
    }

    public function show(Employee $employee)
    {
        // The route model binding should automatically load the employee,
        // but you can eager load user if not already loaded
        $employee->load('user', 'jobRole');
        return view('admin.employees.show', compact('employee'));
    }

    public function history(Employee $employee)
    {
        $appointments = $employee->appointments()
            ->with('customer', 'services')
            ->orderBy('appointment_date', 'desc')
            ->paginate(10);
        return view('admin.employees.history', compact('employee', 'appointments'));
    }

    public function resendInvitation(Employee $employee)
    {
        $user = $employee->user;
        if ($user->status === 'invited') {
            $user->generateInvitationToken(); // refreshes token & expiry
            Mail::to($employee->personal_email)->send(new EmployeeInvitationMail($user, $employee->jobRole->title));
            return back()->with('success', 'Invitation resent to ' . $employee->personal_email);
        }
        return back()->with('error', 'User is already active.');
    }

    public function destroyInvite(Employee $employee)
    {
        $user = $employee->user;
        if ($user->status === 'invited') {
            // Archive the user (no soft delete, just change status)
            $user->update(['status' => 'inactive']);
            // Optionally clear invitation token to invalidate the link
            $user->update(['invitation_token' => null]);

            return redirect()->route('admin.employees.index')
                ->with('success', 'Invitation cancelled. Employee moved to archived.');
        }
        return back()->with('error', 'Cannot cancel active user.');
    }
}
