<?php

namespace App\Http\Controllers;

use App\Models\PaidLeave;
use App\Models\Employee;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class PaidLeaveController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        // Middleware will be handled by routes
    }

    /**
     * Display a listing of paid leaves.
     */
    public function index(Request $request)
    {
        // Allow employees to view their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('view paid leaves');
        }

        $query = PaidLeave::with(['employee', 'requestedBy', 'approvedBy']);

        // Company filtering for Super Admin
        if (Auth::user()->isSuperAdmin() && $request->filled('company')) {
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
            if ($company) {
                $query->whereHas('employee', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                });
            }
        } elseif (!Auth::user()->isSuperAdmin()) {
            // Non-super admins see only their company's paid leaves
            $query->whereHas('employee', function ($q) {
                $q->where('company_id', Auth::user()->company_id);
            });
        }

        // If employee user, only show their own paid leaves
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            } else {
                // If no employee record found, return empty results
                $query->where('employee_id', 0);
            }
        }

        // Filter by name search (employee name) - only for non-employees
        if ($request->filled('name_search') && !Auth::user()->hasRole('Employee')) {
            $query->whereHas('employee', function ($q) use ($request) {
                $searchTerm = $request->name_search;
                $q->where(DB::raw("CONCAT(first_name, ' ', middle_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            });
        }

        // Filter by status
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        // Filter by leave type
        if ($request->filled('leave_type')) {
            $query->where('leave_type', $request->leave_type);
        }

        // Filter by date approved
        if ($request->filled('date_approved')) {
            $query->whereDate('approved_date', $request->date_approved);
        }

        // Order by latest first
        $query->orderBy('created_at', 'desc');

        $perPage = $request->get('per_page', 10);
        $paidLeaves = $query->paginate($perPage);

        // Calculate summary statistics
        $summaryStatsQuery = PaidLeave::query();

        // Apply company scope for summary statistics
        if (Auth::user()->isSuperAdmin() && $request->filled('company')) {
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
            if ($company) {
                $summaryStatsQuery->whereHas('employee', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                });
            }
        } elseif (!Auth::user()->isSuperAdmin()) {
            // Non-super admins see only their company's statistics
            $summaryStatsQuery->whereHas('employee', function ($q) {
                $q->where('company_id', Auth::user()->company_id);
            });
        }

        // If employee user, only show their own statistics
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $summaryStatsQuery->where('employee_id', $employee->id);
            } else {
                // If no employee record found, return empty results
                $summaryStatsQuery->where('employee_id', 0);
            }
        }

        $summaryStats = [
            'total_approved_amount' => $summaryStatsQuery->clone()->where('status', 'approved')->sum('total_amount'),
            'total_approved_leave' => $summaryStatsQuery->clone()->where('status', 'approved')->count(),
            'total_pending_amount' => $summaryStatsQuery->clone()->where('status', 'pending')->sum('total_amount'),
            'total_pending_leave' => $summaryStatsQuery->clone()->where('status', 'pending')->count(),
        ];

        // Handle AJAX requests
        if ($request->ajax() || $request->wantsJson()) {
            return response()->json([
                'html' => view('paid-leaves.partials.paid-leave-list', compact('paidLeaves'))->render(),
                'pagination' => view('paid-leaves.partials.pagination', compact('paidLeaves'))->render(),
                'summary_stats' => $summaryStats,
            ]);
        }

        // Get companies for Super Admin filter
        $companies = Auth::user()->isSuperAdmin()
            ? \App\Models\Company::latest('created_at')->get()
            : collect();

        return view('paid-leaves.index', compact('paidLeaves', 'summaryStats', 'companies'));
    }

    /**
     * Show the form for creating a new paid leave.
     */
    public function create()
    {
        // Allow employees to create their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('create paid leaves');
        }

        $employee = null;

        // If employee user, get their employee record
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return redirect()->back()->with('error', 'Employee profile not found.');
            }
        }

        $employees = Employee::active()->orderBy('last_name')->get();

        // Get active leave settings - we'll pass these to the view for JavaScript
        $leaveSettings = \App\Models\PaidLeaveSetting::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'total_days', 'limit_quantity', 'limit_period', 'pay_rule', 'pay_applicable_to']);

        return view('paid-leaves.create', compact('employees', 'employee', 'leaveSettings'));
    }

    /**
     * Calculate leave balances for an employee
     */
    public function getEmployeeLeaveBalances(Request $request)
    {
        $employee = Employee::find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        $leaveSettings = \App\Models\PaidLeaveSetting::active()->get();
        $balances = [];

        foreach ($leaveSettings as $leaveSetting) {
            // Check if employee is eligible for this leave type
            $isEligible = $this->isEmployeeEligibleForLeave($employee, $leaveSetting);

            if ($isEligible) {
                $usedLeaves = $this->calculateUsedLeaves($employee->id, $leaveSetting->id);
                $availableLeaves = $leaveSetting->limit_quantity - $usedLeaves;

                $balances[] = [
                    'leave_setting_id' => $leaveSetting->id,
                    'name' => $leaveSetting->name,
                    'code' => $leaveSetting->code,
                    'total_days' => $leaveSetting->total_days,
                    'limit_quantity' => $leaveSetting->limit_quantity,
                    'limit_period' => $leaveSetting->limit_period,
                    'used_leaves' => $usedLeaves,
                    'available_leaves' => max(0, $availableLeaves),
                    'pay_rule' => $leaveSetting->pay_rule,
                    'pay_applicable_to' => $leaveSetting->pay_applicable_to,
                    'pay_percentage' => $leaveSetting->pay_rule === 'full' ? 100 : 50
                ];
            }
        }

        return response()->json(['balances' => $balances]);
    }

    /**
     * Check if employee is eligible for a leave type
     */
    private function isEmployeeEligibleForLeave($employee, $leaveSetting)
    {
        // Check benefit eligibility
        if ($leaveSetting->pay_applicable_to === 'with_benefits' && !$employee->has_benefits) {
            return false;
        }
        if ($leaveSetting->pay_applicable_to === 'without_benefits' && $employee->has_benefits) {
            return false;
        }
        // 'all' means all employees are eligible

        return true;
    }

    /**
     * Calculate used leaves for employee and leave type in current period
     */
    private function calculateUsedLeaves($employeeId, $leaveSettingId)
    {
        // Get current period based on leave setting limit_period
        $leaveSetting = \App\Models\PaidLeaveSetting::find($leaveSettingId);
        $now = now();

        switch ($leaveSetting->limit_period) {
            case 'monthly':
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
                break;
            case 'quarterly':
                $quarter = ceil($now->month / 3);
                $startDate = $now->copy()->month(($quarter - 1) * 3 + 1)->startOfMonth();
                $endDate = $startDate->copy()->addMonths(2)->endOfMonth();
                break;
            case 'annually':
                $startDate = $now->copy()->startOfYear();
                $endDate = $now->copy()->endOfYear();
                break;
            default:
                $startDate = $now->copy()->startOfMonth();
                $endDate = $now->copy()->endOfMonth();
        }

        return PaidLeave::where('employee_id', $employeeId)
            ->whereHas('leaveSetting', function ($q) use ($leaveSettingId) {
                $q->where('id', $leaveSettingId);
            })
            ->whereBetween('start_date', [$startDate, $endDate])
            ->where('status', '!=', 'rejected')
            ->sum('total_days');
    }

    /**
     * Store a newly created paid leave.
     */
    public function store(Request $request)
    {
        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_setting_id' => 'required|exists:paid_leave_settings,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
            'supporting_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Get leave setting and employee
        $leaveSetting = \App\Models\PaidLeaveSetting::findOrFail($validatedData['leave_setting_id']);
        $employee = Employee::findOrFail($validatedData['employee_id']);

        // Verify employee is eligible for this leave type
        if (!$this->isEmployeeEligibleForLeave($employee, $leaveSetting)) {
            return back()->withErrors(['leave_setting_id' => 'Employee is not eligible for this leave type.']);
        }

        // Check if employee has sufficient leave balance
        $usedLeaves = $this->calculateUsedLeaves($employee->id, $leaveSetting->id);
        $availableLeaves = $leaveSetting->limit_quantity - $usedLeaves;

        if ($availableLeaves < 1) {
            return back()->withErrors(['leave_setting_id' => 'Insufficient leave balance for this leave type.']);
        }

        // Calculate total days
        $startDate = \Carbon\Carbon::parse($validatedData['start_date']);
        $endDate = \Carbon\Carbon::parse($validatedData['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Verify total days matches leave setting
        if ($totalDays != $leaveSetting->total_days) {
            return back()->withErrors(['end_date' => "This leave type allows only {$leaveSetting->total_days} day(s) per request."]);
        }

        // Get employee's daily rate using the same logic as the API endpoint
        $dailyRate = $this->calculateEmployeeDailyRate($employee);

        // Apply pay rule (full or half pay) and round properly
        $payPercentage = $leaveSetting->pay_rule === 'full' ? 100 : 50;
        $payRate = round(($payPercentage / 100) * $dailyRate, 2);
        $totalAmount = round($payRate * $totalDays, 2);

        $paidLeaveData = array_merge($validatedData, [
            'leave_type' => strtolower(str_replace(' ', '_', $leaveSetting->name)), // Convert name to snake_case
            'total_days' => $totalDays,
            'daily_rate' => $payRate, // Use the adjusted rate based on pay percentage
            'total_amount' => $totalAmount,
            'requested_by' => Auth::id(),
            'requested_date' => now(),
        ]);

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            $paidLeaveData['supporting_document'] = $request->file('supporting_document')->store('paid-leaves', 'public');
        }

        $paidLeave = PaidLeave::create($paidLeaveData);

        return redirect()->route('paid-leaves.index')->with('success', 'Paid leave request submitted successfully.');
    }

    /**
     * Display the specified paid leave.
     */
    public function show(PaidLeave $paidLeave)
    {
        // Allow employees to view their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('view paid leaves');
        } else {
            // For employees, check if they own this request
            $employee = Auth::user()->employee;
            if (!$employee || $paidLeave->employee_id !== $employee->id) {
                return redirect()->route('paid-leaves.index')
                    ->with('error', 'You can only view your own paid leave requests.');
            }
        }

        $paidLeave->load(['employee', 'requestedBy', 'approvedBy']);

        return view('paid-leaves.show', compact('paidLeave'));
    }

    /**
     * Approve a paid leave request.
     */
    public function approve(Request $request, PaidLeave $paidLeave)
    {
        $this->authorize('approve paid leaves');

        $request->validate([
            'remarks' => 'nullable|string|max:500',
        ]);

        $paidLeave->update([
            'status' => 'approved',
            'approved_by' => Auth::id(),
            'approved_date' => now(),
            'remarks' => $request->remarks,
        ]);

        // Return JSON response for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Paid leave approved successfully.',
                'paid_leave' => $paidLeave->fresh(['employee', 'requestedBy', 'approvedBy'])
            ], 200);
        }

        // Return redirect for form submissions
        return redirect()->route('paid-leaves.index')
            ->with('success', 'Paid leave approved successfully.');
    }

    /**
     * Reject a paid leave request.
     */
    public function reject(Request $request, PaidLeave $paidLeave)
    {
        $this->authorize('approve paid leaves');

        $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $paidLeave->update([
            'status' => 'rejected',
            'approved_by' => Auth::id(),
            'approved_date' => now(),
            'remarks' => $request->remarks,
        ]);

        // Return JSON response for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'success' => true,
                'message' => 'Paid leave rejected successfully.',
                'paid_leave' => $paidLeave->fresh(['employee', 'requestedBy', 'approvedBy'])
            ], 200);
        }

        // Return redirect for form submissions
        return redirect()->route('paid-leaves.index')
            ->with('success', 'Paid leave rejected successfully.');
    }

    /**
     * Check employee eligibility (AJAX)
     */
    public function checkEligibility(Request $request)
    {
        // Implement eligibility checking logic
        return response()->json(['eligible' => true]);
    }

    /**
     * Get employee payroll periods (AJAX)
     */
    public function getEmployeePayrollPeriods(Request $request)
    {
        // Return available payroll periods for the employee
        return response()->json([]);
    }

    /**
     * Get employee pay schedule (AJAX)
     */
    public function getEmployeePaySchedule(Request $request)
    {
        // Return employee's pay schedule
        return response()->json([]);
    }

    /**
     * Check employee active leaves (AJAX)
     */
    public function checkEmployeeActiveLeaves(Request $request)
    {
        $employeeId = $request->employee_id;

        $activeLeave = PaidLeave::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where(function ($query) {
                $query->where('end_date', '>=', now()->toDateString())
                    ->orWhereNull('end_date');
            })
            ->first();

        if ($activeLeave) {
            return response()->json([
                'has_active' => true,
                'active_leave' => [
                    'reference_number' => $activeLeave->reference_number,
                    'status' => $activeLeave->status,
                    'leave_type' => $activeLeave->leave_type_display,
                    'start_date' => $activeLeave->start_date->format('M d, Y'),
                    'end_date' => $activeLeave->end_date->format('M d, Y'),
                ]
            ]);
        }

        return response()->json(['has_active' => false]);
    }

    /**
     * Generate summary report (AJAX)
     */
    public function generateSummary(Request $request)
    {
        $this->authorize('view paid leaves');

        // Implement summary generation logic
        return response()->json(['message' => 'Summary generated successfully']);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(PaidLeave $paidLeave)
    {
        // Allow employees to edit their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('edit paid leaves');
        }

        // Only allow editing pending requests
        if ($paidLeave->status !== 'pending') {
            return redirect()->route('paid-leaves.show', $paidLeave)
                ->with('error', 'Only pending paid leave requests can be edited.');
        }

        $employee = null;

        // If employee user, get their employee record
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return redirect()->back()->with('error', 'Employee profile not found.');
            }

            // Check if the employee is editing their own request
            if ($paidLeave->employee_id !== $employee->id) {
                return redirect()->route('paid-leaves.index')
                    ->with('error', 'You can only edit your own paid leave requests.');
            }
        }

        $employees = Employee::active()->orderBy('last_name')->get();

        // Get active leave settings
        $leaveSettings = \App\Models\PaidLeaveSetting::active()
            ->orderBy('name')
            ->get(['id', 'name', 'code', 'total_days', 'limit_quantity', 'limit_period', 'pay_rule', 'pay_applicable_to']);

        return view('paid-leaves.edit', compact('paidLeave', 'employees', 'employee', 'leaveSettings'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, PaidLeave $paidLeave)
    {
        // Allow employees to update their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('edit paid leaves');
        } else {
            // For employees, check if they own this request
            $employee = Auth::user()->employee;
            if (!$employee || $paidLeave->employee_id !== $employee->id) {
                return redirect()->route('paid-leaves.index')
                    ->with('error', 'You can only update your own paid leave requests.');
            }
        }

        // Only allow updating pending requests
        if ($paidLeave->status !== 'pending') {
            return redirect()->route('paid-leaves.show', $paidLeave)
                ->with('error', 'Only pending paid leave requests can be updated.');
        }

        $validatedData = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'leave_setting_id' => 'required|exists:paid_leave_settings,id',
            'start_date' => 'required|date|after_or_equal:today',
            'end_date' => 'required|date|after_or_equal:start_date',
            'reason' => 'required|string|max:500',
            'supporting_document' => 'nullable|file|mimes:jpg,jpeg,png,pdf|max:2048',
        ]);

        // Get leave setting and employee
        $leaveSetting = \App\Models\PaidLeaveSetting::findOrFail($validatedData['leave_setting_id']);
        $employee = Employee::findOrFail($validatedData['employee_id']);

        // Verify employee is eligible for this leave type
        if (!$this->isEmployeeEligibleForLeave($employee, $leaveSetting)) {
            return back()->withErrors(['leave_setting_id' => 'Employee is not eligible for this leave type.']);
        }

        // Calculate total days
        $startDate = \Carbon\Carbon::parse($validatedData['start_date']);
        $endDate = \Carbon\Carbon::parse($validatedData['end_date']);
        $totalDays = $startDate->diffInDays($endDate) + 1;

        // Verify total days matches leave setting
        if ($totalDays != $leaveSetting->total_days) {
            return back()->withErrors(['end_date' => "This leave type allows only {$leaveSetting->total_days} day(s) per request."]);
        }

        // Check if employee has sufficient leave balance (exclude current request from calculation)
        $usedLeaves = PaidLeave::where('employee_id', $employee->id)
            ->whereHas('leaveSetting', function ($q) use ($leaveSetting) {
                $q->where('id', $leaveSetting->id);
            })
            ->where('id', '!=', $paidLeave->id) // Exclude current request
            ->where('status', '!=', 'rejected')
            ->sum('total_days');

        $availableLeaves = $leaveSetting->limit_quantity - $usedLeaves;

        if ($availableLeaves < 1) {
            return back()->withErrors(['leave_setting_id' => 'Insufficient leave balance for this leave type.']);
        }

        // Get employee's daily rate
        $dailyRate = $this->calculateEmployeeDailyRate($employee);

        // Apply pay rule (full or half pay) and round properly
        $payPercentage = $leaveSetting->pay_rule === 'full' ? 100 : 50;
        $payRate = round(($payPercentage / 100) * $dailyRate, 2);
        $totalAmount = round($payRate * $totalDays, 2);

        $updateData = array_merge($validatedData, [
            'leave_type' => strtolower(str_replace(' ', '_', $leaveSetting->name)), // Convert name to snake_case
            'total_days' => $totalDays,
            'daily_rate' => $payRate, // Use the adjusted rate based on pay percentage
            'total_amount' => $totalAmount,
        ]);

        // Handle file upload
        if ($request->hasFile('supporting_document')) {
            $updateData['supporting_document'] = $request->file('supporting_document')->store('paid-leaves', 'public');
        }

        $paidLeave->update($updateData);

        return redirect()->route('paid-leaves.show', $paidLeave)
            ->with('success', 'Paid leave request updated successfully.');
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(PaidLeave $paidLeave)
    {
        Log::info('Delete method called for paid leave', [
            'paid_leave_id' => $paidLeave->id,
            'reference_number' => $paidLeave->reference_number,
            'status' => $paidLeave->status,
            'user_id' => Auth::id()
        ]);

        // Allow employees to delete their own paid leaves, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('delete paid leaves');
            Log::info('Authorization passed for non-employee');
            // Allow deletion of any status for HR/Admin
            Log::info('Proceeding with deletion of paid leave with status: ' . $paidLeave->status);
        } else {
            // For employees, check if they own this request and it's pending
            $employee = Auth::user()->employee;
            if (!$employee || $paidLeave->employee_id !== $employee->id) {
                Log::warning('Employee tried to delete paid leave they do not own');
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only delete your own paid leave requests.'
                    ], 403);
                }
                return redirect()->route('paid-leaves.index')
                    ->with('error', 'You can only delete your own paid leave requests.');
            }

            // Only allow employees to delete pending requests
            if ($paidLeave->status !== 'pending') {
                Log::warning('Employee tried to delete non-pending paid leave', ['status' => $paidLeave->status]);
                if (request()->wantsJson() || request()->ajax()) {
                    return response()->json([
                        'success' => false,
                        'message' => 'You can only delete pending paid leave requests.'
                    ], 403);
                }
                return redirect()->route('paid-leaves.index')
                    ->with('error', 'You can only delete pending paid leave requests.');
            }

            Log::info('Employee authorization passed for own pending request');
            Log::info('Proceeding with deletion of pending paid leave');
        }

        try {
            $paidLeave->delete();
            Log::info('Paid leave deleted successfully', ['paid_leave_id' => $paidLeave->id]);

            // Return JSON response for AJAX requests
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => true,
                    'message' => 'Paid leave request deleted successfully.'
                ]);
            }

            // Return redirect for form submissions
            return redirect()->route('paid-leaves.index')
                ->with('success', 'Paid leave request deleted successfully.');
        } catch (\Exception $e) {
            Log::error('Failed to delete paid leave', [
                'paid_leave_id' => $paidLeave->id,
                'error' => $e->getMessage()
            ]);

            // Return JSON response for AJAX requests
            if (request()->wantsJson() || request()->ajax()) {
                return response()->json([
                    'success' => false,
                    'message' => 'Failed to delete paid leave request.'
                ], 500);
            }

            // Return redirect for form submissions
            return redirect()->route('paid-leaves.index')
                ->with('error', 'Failed to delete paid leave request.');
        }
    }

    /**
     * Get employee daily rate calculation info using actual schedule data
     */
    public function getEmployeeDailyRate(Request $request)
    {
        $employee = Employee::with(['timeSchedule', 'daySchedule'])->find($request->employee_id);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Calculate daily rate using the extracted method
        $dailyRate = $this->calculateEmployeeDailyRate($employee);

        // Get schedule information for debugging/display
        $scheduleInfo = [
            'time_schedule' => $employee->timeSchedule ? [
                'name' => $employee->timeSchedule->name,
                'total_hours' => $employee->timeSchedule->total_hours,
                'time_range' => $employee->timeSchedule->time_range_display ?? null
            ] : null,
            'day_schedule' => $employee->daySchedule ? [
                'name' => $employee->daySchedule->name,
                'days' => $employee->daySchedule->days_display ?? null,
                'days_per_week' => $employee->getDaysPerWeek()
            ] : null
        ];

        return response()->json([
            'daily_rate' => $dailyRate, // Already rounded in calculateEmployeeDailyRate method
            'rate_type' => $employee->rate_type ?? 'monthly',
            'fixed_rate' => $employee->fixed_rate ?? $employee->basic_salary,
            'schedule_info' => $scheduleInfo,
            'calculation_method' => $employee->fixed_rate && $employee->rate_type ? 'fixed_rate_system' : 'legacy_system'
        ]);
    }

    /**
     * Calculate employee's daily rate using the same logic as payroll system
     */
    private function calculateEmployeeDailyRate($employee)
    {
        $dailyRate = 0;

        if ($employee->fixed_rate && $employee->rate_type) {
            // Use the new fixed rate system with actual employee schedules

            // Get employee's assigned time schedule total hours for calculation
            $timeSchedule = $employee->timeSchedule;
            $dailyHours = $timeSchedule ? $timeSchedule->total_hours : 8; // Default to 8 hours if no schedule

            // For monthly working days calculation, use current month as default
            $currentMonth = now();
            $monthStart = $currentMonth->copy()->startOfMonth();
            $monthEnd = $currentMonth->copy()->endOfMonth();

            switch ($employee->rate_type) {
                case 'daily':
                    $dailyRate = $employee->fixed_rate;
                    break;

                case 'hourly':
                    // hourly rate * assigned total hours per day
                    $dailyRate = $employee->fixed_rate * $dailyHours;
                    break;

                case 'weekly':
                    // Calculate employee's working days per week based on day schedule
                    $daysPerWeek = $employee->getDaysPerWeek();
                    if ($daysPerWeek > 0) {
                        $dailyRate = $employee->fixed_rate / $daysPerWeek;
                    } else {
                        $dailyRate = $employee->fixed_rate / 5; // Fallback to 5 days
                    }
                    break;

                case 'semi_monthly':
                case 'semi-monthly':
                    // Calculate working days in a semi-monthly period based on employee schedule
                    $sampleSemiStart = $monthStart->copy();
                    $sampleSemiEnd = $sampleSemiStart->copy()->addDays(14); // First 15 days
                    $workingDaysInSemiPeriod = 0;
                    $sampleDate = $sampleSemiStart->copy();

                    while ($sampleDate->lte($sampleSemiEnd)) {
                        if ($employee->isWorkingDay($sampleDate)) {
                            $workingDaysInSemiPeriod++;
                        }
                        $sampleDate->addDay();
                    }

                    if ($workingDaysInSemiPeriod > 0) {
                        $dailyRate = $employee->fixed_rate / $workingDaysInSemiPeriod;
                    } else {
                        $dailyRate = $employee->fixed_rate / 11; // Fallback
                    }
                    break;

                case 'monthly':
                    // Calculate actual working days in the month based on employee's day schedule
                    $workingDaysInMonth = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);

                    if ($workingDaysInMonth > 0) {
                        $dailyRate = $employee->fixed_rate / $workingDaysInMonth;
                    } else {
                        $dailyRate = $employee->fixed_rate / 22; // Fallback
                    }
                    break;

                default:
                    // Fallback calculation using basic salary
                    if ($employee->basic_salary) {
                        $workingDaysInMonth = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);
                        $dailyRate = $workingDaysInMonth > 0 ? ($employee->basic_salary / $workingDaysInMonth) : ($employee->basic_salary / 22);
                    }
            }
        } else {
            // Use old calculation method with dynamic working days if possible
            if ($employee->daily_rate && $employee->daily_rate > 0) {
                $dailyRate = $employee->daily_rate;
            } elseif ($employee->basic_salary && $employee->basic_salary > 0) {
                // Try to use actual working days calculation
                $currentMonth = now();
                $monthStart = $currentMonth->copy()->startOfMonth();
                $monthEnd = $currentMonth->copy()->endOfMonth();
                $workingDaysInMonth = $employee->getWorkingDaysForPeriod($monthStart, $monthEnd);

                $dailyRate = $workingDaysInMonth > 0 ? ($employee->basic_salary / $workingDaysInMonth) : ($employee->basic_salary / 22);
            }
        }

        return round($dailyRate, 2);
    }
}
