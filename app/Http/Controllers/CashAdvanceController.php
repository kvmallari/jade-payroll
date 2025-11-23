<?php

namespace App\Http\Controllers;

use App\Models\CashAdvance;
use App\Models\Employee;
use App\Models\PayrollSetting;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class CashAdvanceController extends Controller
{
    use AuthorizesRequests;

    public function __construct()
    {
        // $this->middleware('auth'); // Will be handled by route middleware
    }

    /**
     * Display a listing of cash advances.
     */
    public function index(Request $request)
    {
        // Allow employees to view their own cash advances, others need permission
        if (!Auth::user()->hasRole('Employee')) {
            $this->authorize('view cash advances');
        }



        $query = CashAdvance::with(['employee', 'requestedBy', 'approvedBy']);

        // Super Admin can filter by company
        if (Auth::user()->isSuperAdmin() && $request->filled('company')) {
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
            if ($company) {
                $query->whereHas('employee', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                });
            }
        } elseif (!Auth::user()->isSuperAdmin()) {
            // Non-super admins see only their company's cash advances
            $query->whereHas('employee', function ($q) {
                $q->where('company_id', Auth::user()->company_id);
            });
        }

        // Filter by name search (employee name)
        if ($request->filled('name_search')) {
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

        // Filter by date approved (single date)
        if ($request->filled('date_approved')) {
            $query->whereDate('approved_date', $request->date_approved);
        }

        // Filter by date completed (single date - fully paid date)
        if ($request->filled('date_completed')) {
            $query->whereDate('updated_at', $request->date_completed)
                ->where('status', 'fully_paid');
        }

        // If employee user, only show their own cash advances
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $query->where('employee_id', $employee->id);
            }
        }

        $perPage = $request->get('per_page', 10);
        $cashAdvances = $query->orderByDesc('created_at')->paginate($perPage);

        $employees = Employee::active()->orderBy('last_name')->get();

        // Get companies for Super Admin filter
        $companies = Auth::user()->isSuperAdmin()
            ? \App\Models\Company::latest('created_at')->get()
            : collect();        // Calculate summary statistics
        $summaryQuery = CashAdvance::query();

        // Apply company filter for summary
        if (Auth::user()->isSuperAdmin() && $request->filled('company')) {
            $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
            if ($company) {
                $summaryQuery->whereHas('employee', function ($q) use ($company) {
                    $q->where('company_id', $company->id);
                });
            }
        } elseif (!Auth::user()->isSuperAdmin()) {
            $summaryQuery->whereHas('employee', function ($q) {
                $q->where('company_id', Auth::user()->company_id);
            });
        }

        // Apply same filters for summary
        if ($request->filled('name_search')) {
            $summaryQuery->whereHas('employee', function ($q) use ($request) {
                $searchTerm = $request->name_search;
                $q->where(DB::raw("CONCAT(first_name, ' ', middle_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere(DB::raw("CONCAT(first_name, ' ', last_name)"), 'LIKE', "%{$searchTerm}%")
                    ->orWhere('first_name', 'LIKE', "%{$searchTerm}%")
                    ->orWhere('last_name', 'LIKE', "%{$searchTerm}%");
            });
        }
        if ($request->filled('status')) {
            $summaryQuery->where('status', $request->status);
        }
        if ($request->filled('date_approved')) {
            $summaryQuery->whereDate('approved_date', $request->date_approved);
        }
        if ($request->filled('date_completed')) {
            $summaryQuery->whereDate('updated_at', $request->date_completed)
                ->where('status', 'fully_paid');
        }
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if ($employee) {
                $summaryQuery->where('employee_id', $employee->id);
            }
        }

        $summaryStats = [
            'total_approved_amount' => $summaryQuery->clone()->where('status', 'approved')->sum('approved_amount'),
            'total_interest_amount' => $summaryQuery->clone()->where('status', 'approved')->sum('interest_amount'),
            'total_paid_amount' => $summaryQuery->clone()->where('status', 'approved')->get()->sum('total_paid'),
            'total_unpaid_amount' => $summaryQuery->clone()->where('status', 'approved')->sum('outstanding_balance'),
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'html' => view('cash-advances.partials.cash-advance-list', compact('cashAdvances'))->render(),
                'pagination' => view('cash-advances.partials.pagination', compact('cashAdvances'))->render(),
                'summary_stats' => $summaryStats,
            ]);
        }

        return view('cash-advances.index', compact('cashAdvances', 'employees', 'summaryStats', 'companies'));
    }

    /**
     * Show the form for creating a new cash advance.
     */
    public function create()
    {
        $this->authorize('create cash advances');

        $employee = null;

        // If employee user, get their employee record
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return redirect()->back()->with('error', 'Employee profile not found.');
            }
        }

        $employees = Employee::active()->orderBy('last_name')->get();

        return view('cash-advances.create', compact('employees', 'employee'));
    }

    /**
     * Get employee pay schedule information (AJAX endpoint)
     */
    public function getEmployeePaySchedule(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json(['error' => 'Employee ID is required'], 400);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        return response()->json([
            'pay_schedule' => $employee->pay_schedule, // weekly, semi_monthly, monthly
            'full_name' => $employee->full_name,
            'basic_salary' => $employee->basic_salary,
        ]);
    }

    /**
     * Get payroll periods for an employee (AJAX endpoint)
     */
    public function getEmployeePayrollPeriods(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json(['error' => 'Employee ID is required'], 400);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Get the schedule setting for this employee's pay schedule
        $scheduleSetting = \App\Models\PayScheduleSetting::where('code', $employee->pay_schedule)
            ->where('is_active', true)
            ->first();

        if (!$scheduleSetting) {
            return response()->json(['error' => 'Pay schedule setting not found'], 404);
        }

        // Get timing preference for semi-monthly employees with monthly frequency
        $monthlyTiming = $request->input('monthly_deduction_timing');
        $deductionFrequency = $request->input('deduction_frequency');

        // Calculate the next 3 payroll periods for this employee
        $periods = $this->calculateNextPayrollPeriods($scheduleSetting, 3, $monthlyTiming, $deductionFrequency);

        return response()->json([
            'periods' => $periods,
            'employee_pay_schedule' => $employee->pay_schedule
        ]);
    }

    /**
     * Calculate the next N payroll periods for a given schedule
     */
    private function calculateNextPayrollPeriods($scheduleSetting, $count = 3, $monthlyTiming = null, $deductionFrequency = null)
    {
        $periods = [];
        $currentDate = \Carbon\Carbon::now();

        for ($i = 0; $i < $count; $i++) {
            $periodData = $this->calculatePayrollPeriodForOffset($scheduleSetting, $currentDate, $i, $monthlyTiming, $deductionFrequency);

            $periods[] = [
                'value' => $i + 1,
                'label' => $periodData['display'],
                'description' => "Pay period: {$periodData['display']}",
                'is_default' => $i === 0
            ];
        }

        return $periods;
    }
    /**
     * Calculate payroll period for a specific offset from current date
     */
    private function calculatePayrollPeriodForOffset($scheduleSetting, $baseDate, $offset = 0, $monthlyTiming = null, $deductionFrequency = null)
    {
        switch ($scheduleSetting->code) {
            case 'semi_monthly':
                return $this->calculateSemiMonthlyPeriodForOffset($scheduleSetting, $baseDate, $offset, $monthlyTiming, $deductionFrequency);
            case 'weekly':
                return $this->calculateWeeklyPeriodForOffset($scheduleSetting, $baseDate, $offset, $monthlyTiming, $deductionFrequency);
            case 'monthly':
                return $this->calculateMonthlyPeriodForOffset($scheduleSetting, $baseDate, $offset);
            default:
                return $this->calculateSemiMonthlyPeriodForOffset($scheduleSetting, $baseDate, $offset, $monthlyTiming, $deductionFrequency);
        }
    }

    /**
     * Calculate semi-monthly periods with offset
     */
    private function calculateSemiMonthlyPeriodForOffset($scheduleSetting, $baseDate, $offset, $monthlyTiming = null, $deductionFrequency = null)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods) || count($cutoffPeriods) < 2) {
            // Default semi-monthly cutoffs
            $cutoffPeriods = [
                ['start_day' => 1, 'end_day' => 15],
                ['start_day' => 16, 'end_day' => 31]
            ];
        }

        $currentDay = $baseDate->day;
        $currentMonth = $baseDate->copy();

        // Determine the ACTUAL current period based on today's date
        $isFirstHalf = $currentDay <= 15;

        if ($deductionFrequency === 'monthly' && $monthlyTiming) {
            // For monthly frequency with timing preference
            if ($monthlyTiming === 'first_payroll') {
                // Show only 1st cutoff periods across months
                $preferredPeriodIndex = 0;
                $targetMonth = $currentMonth->copy();

                // If we're currently in 2nd half and user wants 1st cutoff, start from next month
                if (!$isFirstHalf) {
                    $targetMonth->addMonth();
                }

                // Apply offset by adding months (stay on same cutoff type)
                $targetMonth->addMonths($offset);
                $targetPeriodIndex = $preferredPeriodIndex;
            } else {
                // Show only 2nd cutoff periods across months
                $preferredPeriodIndex = 1;
                $targetMonth = $currentMonth->copy();

                // If we're currently in 1st half and user wants 2nd cutoff, use current month's 2nd cutoff
                if ($isFirstHalf) {
                    // Stay in current month for 2nd cutoff (current month's Aug 16-31)
                } else {
                    // If in 2nd half, this IS the current "last payroll", so start here
                    // No need to move to next month for offset 0
                }

                // Apply offset by adding months (stay on same cutoff type)
                $targetMonth->addMonths($offset);
                $targetPeriodIndex = $preferredPeriodIndex;
            }
        } else {
            // For per-payroll frequency, show both cutoffs alternating
            // Start from the CURRENT active period
            $targetPeriodIndex = $isFirstHalf ? 0 : 1;
            $targetMonth = $currentMonth->copy();

            // Apply offset with alternating cutoffs
            for ($i = 0; $i < $offset; $i++) {
                $targetPeriodIndex++;
                if ($targetPeriodIndex >= 2) {
                    $targetPeriodIndex = 0;
                    $targetMonth->addMonth();
                }
            }
        }

        $cutoff = $cutoffPeriods[$targetPeriodIndex];
        $startDay = (int) $cutoff['start_day'];
        $endDay = (int) $cutoff['end_day'];

        // Handle cross-month periods (when start_day > end_day)
        if ($startDay > $endDay) {
            // Period crosses month boundary
            $startDate = $targetMonth->copy()->subMonth()->day($startDay);
            if ($endDay == 31) {
                $endDate = $targetMonth->copy()->endOfMonth();
            } else {
                $endDate = $targetMonth->copy()->day($endDay);
            }
        } else {
            // Period is within same month
            $startDate = $targetMonth->copy()->day($startDay);
            if ($endDay == 31) {
                $endDate = $targetMonth->copy()->endOfMonth();
            } else {
                $endDate = $targetMonth->copy()->day($endDay);
            }
        }

        return [
            'start' => $startDate,
            'end' => $endDate,
            'display' => $startDate->format('M d') . ' - ' . $endDate->format('d, Y')
        ];
    }
    /**
     * Calculate weekly periods with offset
     */
    private function calculateWeeklyPeriodForOffset($scheduleSetting, $baseDate, $offset, $monthlyTiming = null, $deductionFrequency = null)
    {
        $cutoffPeriods = $scheduleSetting->cutoff_periods;
        if (is_string($cutoffPeriods)) {
            $cutoffPeriods = json_decode($cutoffPeriods, true);
        }
        if (empty($cutoffPeriods)) {
            $cutoffPeriods = [['start_day' => 'monday', 'end_day' => 'friday']];
        }

        $cutoff = $cutoffPeriods[0];
        $startDayName = $cutoff['start_day'];

        // Handle monthly deduction frequency with timing preference
        if ($deductionFrequency === 'monthly' && $monthlyTiming) {
            $currentMonth = $baseDate->copy();

            if ($monthlyTiming === 'first_payroll') {
                // Get first week of the month for each offset
                $targetMonth = $currentMonth->copy()->addMonths($offset);
                $firstDayOfMonth = $targetMonth->copy()->startOfMonth();

                // Find the first occurrence of the pay day in this month
                $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
                $targetDayOfWeek = $dayMap[$startDayName] ?? 1;

                // Find the first occurrence of the target day in the month
                $startDate = $firstDayOfMonth->copy();
                while ($startDate->dayOfWeek !== $targetDayOfWeek) {
                    $startDate->addDay();
                }
            } else { // last_payroll
                // Get last week of the month for each offset
                $targetMonth = $currentMonth->copy()->addMonths($offset);
                $lastDayOfMonth = $targetMonth->copy()->endOfMonth();

                // Find the last occurrence of the pay day in this month
                $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
                $targetDayOfWeek = $dayMap[$startDayName] ?? 1;

                // Find the last occurrence of the target day in the month
                $startDate = $lastDayOfMonth->copy();
                while ($startDate->dayOfWeek !== $targetDayOfWeek) {
                    $startDate->subDay();
                }
            }
        } else {
            // Regular weekly calculation - get current week and add offset
            $startDate = $baseDate->copy()->startOfWeek();
            if ($startDayName !== 'monday') {
                // Adjust for different start days
                $dayMap = ['sunday' => 0, 'monday' => 1, 'tuesday' => 2, 'wednesday' => 3, 'thursday' => 4, 'friday' => 5, 'saturday' => 6];
                $targetDay = $dayMap[$startDayName] ?? 1;
                $startDate = $baseDate->copy()->startOfWeek()->addDays($targetDay - 1);
            }

            // Apply offset in weeks
            $startDate->addWeeks($offset);
        }

        $endDate = $startDate->copy()->addDays(6); // 7-day week

        return [
            'start' => $startDate,
            'end' => $endDate,
            'display' => $startDate->format('M d') . ' - ' . $endDate->format('d, Y')
        ];
    }

    /**
     * Calculate monthly periods with offset
     */
    private function calculateMonthlyPeriodForOffset($scheduleSetting, $baseDate, $offset)
    {
        $targetMonth = $baseDate->copy()->addMonths($offset);
        $startDate = $targetMonth->copy()->startOfMonth();
        $endDate = $targetMonth->copy()->endOfMonth();

        return [
            'start' => $startDate,
            'end' => $endDate,
            'display' => $startDate->format('M d') . ' - ' . $endDate->format('d, Y')
        ];
    }
    /**
     * Store a newly created cash advance.
     */
    public function store(Request $request)
    {
        $this->authorize('create cash advances');

        // Base validation rules
        $validationRules = [
            'employee_id' => 'required|exists:employees,id',
            'requested_amount' => 'required|numeric|min:100|max:50000',
            'deduction_frequency' => 'required|in:per_payroll,monthly',
            'monthly_deduction_timing' => 'nullable|in:first_payroll,last_payroll',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'reason' => 'required|string|max:500',
            'starting_payroll_period' => 'required|integer|min:1|max:4',
        ];

        // Add frequency-specific validation rules
        if ($request->deduction_frequency === 'monthly') {
            $validationRules['monthly_installments'] = 'required|integer|min:1|max:12';
            $validationRules['installments'] = 'nullable|integer|min:1|max:12';
            $validationRules['monthly_deduction_timing'] = 'required|in:first_payroll,last_payroll';
        } else {
            $validationRules['installments'] = 'required|integer|min:1|max:12';
            $validationRules['monthly_installments'] = 'nullable|integer|min:1|max:12';
        }

        $validated = $request->validate($validationRules);

        // Clean up monthly_deduction_timing - convert empty string to null for per_payroll frequency
        if ($validated['deduction_frequency'] === 'per_payroll') {
            $validated['monthly_deduction_timing'] = null;
        } elseif (isset($validated['monthly_deduction_timing']) && $validated['monthly_deduction_timing'] === '') {
            $validated['monthly_deduction_timing'] = null;
        }

        // Check if employee already has an active cash advance (applies to all users)
        $existingAdvance = CashAdvance::where('employee_id', $validated['employee_id'])
            ->whereIn('status', ['pending', 'approved'])
            ->where('outstanding_balance', '>', 0)
            ->first();

        if ($existingAdvance) {
            $employee = Employee::find($validated['employee_id']);
            $employeeName = $employee ? $employee->full_name : 'Employee';
            return redirect()->back()
                ->withInput()
                ->with('error', $employeeName . ' already has an active cash advance (Reference: ' . $existingAdvance->reference_number . '). Please wait until it is fully paid before creating a new one.');
        }

        // Additional validation for employee users
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee || $employee->id != $validated['employee_id']) {
                return redirect()->back()->with('error', 'You can only request cash advances for yourself.');
            }
        }

        try {
            DB::beginTransaction();

            // Calculate first deduction date based on starting payroll period
            // Get the actual payroll period dates for the selected starting period
            $employee = Employee::findOrFail($validated['employee_id']);

            // Get the schedule setting for this employee's pay schedule - same as getEmployeePayrollPeriods
            $scheduleSetting = \App\Models\PayScheduleSetting::where('code', $employee->pay_schedule)
                ->where('is_active', true)
                ->first();

            if (!$scheduleSetting) {
                throw new \Exception('Pay schedule setting not found for employee');
            }

            $monthlyTiming = $validated['monthly_deduction_timing'] ?? null;
            $deductionFrequency = $validated['deduction_frequency'];

            // Calculate the actual payroll period for the selected starting period
            $periodOffset = $validated['starting_payroll_period'] - 1; // Convert to 0-based offset

            // Use the SAME calculation that generates the dropdown options with employee's schedule
            $periodData = $this->calculatePayrollPeriodForOffset($scheduleSetting, \Carbon\Carbon::now(), $periodOffset, $monthlyTiming, $deductionFrequency);
            $firstDeductionDate = $periodData['start'];
            $firstDeductionPeriodEnd = $periodData['end'];

            // Determine installments value based on frequency
            $installmentsValue = ($validated['deduction_frequency'] === 'monthly')
                ? ($validated['monthly_installments'] ?? 1)
                : ($validated['installments'] ?? 1);

            $cashAdvance = CashAdvance::create([
                'employee_id' => $validated['employee_id'],
                'reference_number' => CashAdvance::generateReferenceNumber(),
                'requested_amount' => $validated['requested_amount'],
                'installments' => $installmentsValue,
                'monthly_installments' => $validated['monthly_installments'] ?? null,
                'deduction_frequency' => $validated['deduction_frequency'],
                'monthly_deduction_timing' => $validated['deduction_frequency'] === 'monthly' ? ($validated['monthly_deduction_timing'] ?? null) : null,
                'starting_payroll_period' => $validated['starting_payroll_period'],
                'interest_rate' => $validated['interest_rate'] ?? 0,
                'reason' => $validated['reason'],
                'requested_date' => now(),
                'first_deduction_date' => $firstDeductionDate,
                'first_deduction_period_start' => $periodData['start'],
                'first_deduction_period_end' => $periodData['end'],
                'payroll_id' => null, // No longer tied to specific payroll
                'requested_by' => Auth::id(),
                'status' => 'pending',
            ]);

            // Calculate interest and total amounts
            $cashAdvance->updateCalculations();
            $cashAdvance->save();

            DB::commit();

            return redirect()->route('cash-advances.show', $cashAdvance)
                ->with('success', 'Cash advance request submitted successfully! Reference: ' . $cashAdvance->reference_number);
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->withInput()
                ->with('error', 'Failed to submit cash advance request: ' . $e->getMessage());
        }
    }

    /**
     * Display the specified cash advance.
     */
    public function show(CashAdvance $cashAdvance)
    {
        // Check if user can view cash advances or their own cash advances
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee || $employee->id !== $cashAdvance->employee_id) {
                $this->authorize('view cash advances'); // This will fail for employees viewing others'
            }
        } else {
            $this->authorize('view cash advances');
        }

        $cashAdvance->load(['employee', 'requestedBy', 'approvedBy', 'payments.payroll']);

        return view('cash-advances.show', compact('cashAdvance'));
    }

    /**
     * Approve a cash advance.
     */
    public function approve(Request $request, CashAdvance $cashAdvance)
    {
        $this->authorize('approve cash advances');

        if ($cashAdvance->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending cash advances can be approved.');
        }

        $validated = $request->validate([
            'approved_amount' => 'required|numeric|min:100|max:' . $cashAdvance->requested_amount,
            'installments' => 'required|integer|min:1|max:12',
            'interest_rate' => 'required|numeric|min:0|max:100',
            'remarks' => 'nullable|string|max:500',
        ]);

        try {
            DB::beginTransaction();

            // Use the validated values from the form
            $installments = $validated['installments'];
            $interestRate = $validated['interest_rate'];

            $cashAdvance->approve(
                $validated['approved_amount'],
                $installments,
                Auth::id(),
                $validated['remarks'],
                $interestRate
            );

            DB::commit();

            return redirect()->route('cash-advances.show', $cashAdvance)
                ->with('success', 'Cash advance approved successfully!');
        } catch (\Exception $e) {
            DB::rollback();
            return redirect()->back()
                ->with('error', 'Failed to approve cash advance: ' . $e->getMessage());
        }
    }

    /**
     * Reject a cash advance.
     */
    public function reject(Request $request, CashAdvance $cashAdvance)
    {
        $this->authorize('approve cash advances');

        if ($cashAdvance->status !== 'pending') {
            return redirect()->back()->with('error', 'Only pending cash advances can be rejected.');
        }

        $validated = $request->validate([
            'remarks' => 'required|string|max:500',
        ]);

        $cashAdvance->reject($validated['remarks'], Auth::id());

        return redirect()->route('cash-advances.index')
            ->with('success', 'Cash advance rejected successfully.');
    }

    /**
     * Get employee's cash advance eligibility.
     */
    public function checkEligibility(Request $request)
    {
        $employeeId = $request->get('employee_id');

        if (!$employeeId) {
            return response()->json(['eligible' => false, 'reason' => 'Employee not specified']);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['eligible' => false, 'reason' => 'Employee not found']);
        }

        // Check if employee has active cash advance
        $hasActive = CashAdvance::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('outstanding_balance', '>', 0)
            ->exists();

        if ($hasActive) {
            return response()->json([
                'eligible' => false,
                'reason' => 'Employee has an active or pending cash advance'
            ]);
        }

        // Calculate maximum eligible amount (e.g., 50% of monthly salary)
        $monthlySalary = $employee->basic_salary;
        $maxEligible = $monthlySalary * 0.5;

        return response()->json([
            'eligible' => true,
            'max_amount' => $maxEligible,
            'monthly_salary' => $monthlySalary,
        ]);
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(CashAdvance $cashAdvance)
    {
        $this->authorize('edit cash advances');

        // Only allow editing pending requests
        if ($cashAdvance->status !== 'pending') {
            return redirect()->route('cash-advances.show', $cashAdvance)
                ->with('error', 'Only pending cash advance requests can be edited.');
        }

        $employee = null;

        // If employee user, get their employee record
        if (Auth::user()->hasRole('Employee')) {
            $employee = Auth::user()->employee;
            if (!$employee) {
                return redirect()->back()->with('error', 'Employee profile not found.');
            }

            // Check if the employee is editing their own request
            if ($cashAdvance->employee_id !== $employee->id) {
                return redirect()->route('cash-advances.index')
                    ->with('error', 'You can only edit your own cash advance requests.');
            }
        }

        $employees = Employee::active()->orderBy('last_name')->get();
        $payrollSettings = PayrollSetting::first();

        return view('cash-advances.edit', compact('cashAdvance', 'employees', 'employee', 'payrollSettings'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, CashAdvance $cashAdvance)
    {
        $this->authorize('edit cash advances');

        // Only allow updating pending requests
        if ($cashAdvance->status !== 'pending') {
            return redirect()->route('cash-advances.show', $cashAdvance)
                ->with('error', 'Only pending cash advance requests can be updated.');
        }

        // Base validation rules - similar to store method
        $validationRules = [
            'employee_id' => 'required|exists:employees,id',
            'requested_amount' => 'required|numeric|min:100|max:50000',
            'deduction_frequency' => 'required|in:per_payroll,monthly',
            'monthly_deduction_timing' => 'nullable|in:first_payroll,last_payroll',
            'interest_rate' => 'nullable|numeric|min:0|max:100',
            'reason' => 'required|string|max:500',
            'starting_payroll_period' => 'required|integer|min:1|max:4',
        ];

        // Add frequency-specific validation rules
        if ($request->deduction_frequency === 'monthly') {
            $validationRules['monthly_installments'] = 'required|integer|min:1|max:12';
            $validationRules['installments'] = 'nullable|integer|min:1|max:12';
            $validationRules['monthly_deduction_timing'] = 'required|in:first_payroll,last_payroll';
        } else {
            $validationRules['installments'] = 'required|integer|min:1|max:12';
            $validationRules['monthly_installments'] = 'nullable|integer|min:1|max:12';
        }

        $validatedData = $request->validate($validationRules);

        // Calculate amounts and installments
        $requestedAmount = floatval($validatedData['requested_amount']);
        $interestRate = floatval($validatedData['interest_rate'] ?? 0);
        $deductionFrequency = $validatedData['deduction_frequency'];

        // Calculate interest and total amounts
        $interestAmount = ($requestedAmount * $interestRate) / 100;
        $totalAmount = $requestedAmount + $interestAmount;

        // Clean up monthly_deduction_timing - similar to store method
        if ($validatedData['deduction_frequency'] === 'per_payroll') {
            $validatedData['monthly_deduction_timing'] = null;
        } elseif (isset($validatedData['monthly_deduction_timing']) && $validatedData['monthly_deduction_timing'] === '') {
            $validatedData['monthly_deduction_timing'] = null;
        }

        // Determine installments value based on frequency - same logic as store method
        // Always ensure we have a valid installments value (never null)
        if ($validatedData['deduction_frequency'] === 'monthly') {
            $installmentsValue = $validatedData['monthly_installments'] ?? 1;
        } else {
            $installmentsValue = $validatedData['installments'] ?? 1;
        }

        // Ensure installmentsValue is never null
        $installmentsValue = max(1, intval($installmentsValue));

        // Calculate installment amount
        $installmentAmount = $totalAmount / $installmentsValue;

        // Calculate the actual payroll period dates for the updated starting period (same as store method)
        $employee = Employee::findOrFail($validatedData['employee_id']);

        // Get the schedule setting for this employee's pay schedule
        $scheduleSetting = \App\Models\PayScheduleSetting::where('code', $employee->pay_schedule)
            ->where('is_active', true)
            ->first();

        if (!$scheduleSetting) {
            return redirect()->back()
                ->withInput()
                ->with('error', 'Pay schedule setting not found for employee.');
        }

        $monthlyTiming = $validatedData['monthly_deduction_timing'] ?? null;

        // Calculate the actual payroll period for the selected starting period
        $periodOffset = $validatedData['starting_payroll_period'] - 1; // Convert to 0-based offset

        // Use the SAME calculation that generates the dropdown options with employee's schedule
        $periodData = $this->calculatePayrollPeriodForOffset($scheduleSetting, \Carbon\Carbon::now(), $periodOffset, $monthlyTiming, $deductionFrequency);

        // Prepare update data - match the structure of store method
        $updateData = [
            'employee_id' => $validatedData['employee_id'],
            'requested_amount' => $requestedAmount,
            'approved_amount' => $requestedAmount, // Set approved amount same as requested for now
            'outstanding_balance' => $totalAmount, // Will be the full amount until payments are made
            'interest_rate' => $interestRate,
            'interest_amount' => $interestAmount,
            'total_amount' => $totalAmount,
            'installment_amount' => $installmentAmount,
            'deduction_frequency' => $deductionFrequency,
            'starting_payroll_period' => $validatedData['starting_payroll_period'],
            'reason' => $validatedData['reason'],
            'installments' => $installmentsValue, // Always set installments value
            'monthly_installments' => $validatedData['monthly_installments'] ?? null,
            'monthly_deduction_timing' => $validatedData['deduction_frequency'] === 'monthly' ? ($validatedData['monthly_deduction_timing'] ?? null) : null,
            // Add the calculated deduction dates
            'first_deduction_date' => $periodData['start'],
            'first_deduction_period_start' => $periodData['start'],
            'first_deduction_period_end' => $periodData['end'],
        ];

        // Update the cash advance
        $cashAdvance->update($updateData);

        return redirect()->route('cash-advances.show', $cashAdvance)
            ->with('success', 'Cash advance request updated successfully.');
    }

    /**
     * Remove the specified cash advance from storage.
     */
    public function destroy(CashAdvance $cashAdvance)
    {
        $this->authorize('delete cash advances');

        // Check if cash advance can be deleted
        if ($cashAdvance->status === 'approved' && $cashAdvance->outstanding_balance < $cashAdvance->total_amount) {
            return redirect()->route('cash-advances.index')
                ->with('error', 'Cannot delete cash advance that has been partially paid.');
        }

        $reference = $cashAdvance->reference_number;
        $cashAdvance->delete();

        return redirect()->route('cash-advances.index')
            ->with('success', "Cash advance {$reference} has been deleted successfully.");
    }

    /**
     * Check if employee has existing active cash advances (AJAX endpoint)
     */
    public function checkEmployeeActiveAdvances(Request $request)
    {
        $employeeId = $request->input('employee_id');

        if (!$employeeId) {
            return response()->json(['error' => 'Employee ID is required'], 400);
        }

        $employee = Employee::find($employeeId);
        if (!$employee) {
            return response()->json(['error' => 'Employee not found'], 404);
        }

        // Check for active cash advances
        $activeAdvance = CashAdvance::where('employee_id', $employeeId)
            ->whereIn('status', ['pending', 'approved'])
            ->where('outstanding_balance', '>', 0)
            ->with(['employee'])
            ->first();

        if ($activeAdvance) {
            return response()->json([
                'has_active_advance' => true,
                'active_advance' => [
                    'reference_number' => $activeAdvance->reference_number,
                    'status' => $activeAdvance->status,
                    'outstanding_balance' => number_format($activeAdvance->outstanding_balance, 2),
                    'requested_amount' => number_format($activeAdvance->requested_amount, 2),
                ]
            ]);
        }

        return response()->json(['has_active_advance' => false]);
    }

    /**
     * Generate cash advance summary
     */
    public function generateSummary(Request $request)
    {
        $this->authorize('view cash advances');

        $format = $request->input('export', 'pdf');

        // Build query based on filters
        $query = CashAdvance::with(['employee', 'requestedBy', 'approvedBy']);

        // Apply filters
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('employee_id')) {
            $query->where('employee_id', $request->employee_id);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('requested_date', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('requested_date', '<=', $request->date_to);
        }

        $cashAdvances = $query->orderBy('created_at', 'desc')->get();

        if ($format === 'excel') {
            return $this->exportCashAdvanceSummaryExcel($cashAdvances);
        } else {
            return $this->exportCashAdvanceSummaryPDF($cashAdvances);
        }
    }

    /**
     * Export cash advance summary as PDF
     */
    private function exportCashAdvanceSummaryPDF($cashAdvances)
    {
        $fileName = 'cash_advance_summary_' . date('Y-m-d_H-i-s') . '.pdf';

        // Calculate totals
        $totalRequested = $cashAdvances->sum('requested_amount');
        $totalApproved = $cashAdvances->sum('approved_amount');
        $totalPaid = $cashAdvances->sum('total_paid');
        $totalOutstanding = $cashAdvances->sum('outstanding_balance');

        // Create HTML content for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Cash Advance Summary</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; color: #333; font-size: 18px; }
                .header p { margin: 5px 0; color: #666; font-size: 12px; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .text-right { text-align: right; }
                .total-row { background-color: #f8f9fa; font-weight: bold; }
                .currency { text-align: right; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Cash Advance Summary Report</h1>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
                <p>Total Cash Advances: ' . $cashAdvances->count() . '</p>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Reference #</th>
                        <th>Employee</th>
                        <th>Request Date</th>
                        <th class="currency">Requested Amount</th>
                        <th class="currency">Approved Amount</th>
                        <th class="currency">Total Paid</th>
                        <th class="currency">Outstanding</th>
                        <th>Status</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($cashAdvances as $cashAdvance) {
            $html .= '
                    <tr>
                        <td>' . $cashAdvance->reference_number . '</td>
                        <td>' . $cashAdvance->employee->full_name . '</td>
                        <td>' . $cashAdvance->requested_date->format('M d, Y') . '</td>
                        <td class="currency">₱' . number_format($cashAdvance->requested_amount, 2) . '</td>
                        <td class="currency">₱' . number_format($cashAdvance->approved_amount ?: 0, 2) . '</td>
                        <td class="currency">₱' . number_format($cashAdvance->total_paid, 2) . '</td>
                        <td class="currency">₱' . number_format($cashAdvance->outstanding_balance, 2) . '</td>
                        <td>' . ucfirst($cashAdvance->status) . '</td>
                    </tr>';
        }

        $html .= '
                    <tr class="total-row">
                        <td colspan="3"><strong>TOTALS</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalRequested, 2) . '</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalApproved, 2) . '</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalPaid, 2) . '</strong></td>
                        <td class="currency"><strong>₱' . number_format($totalOutstanding, 2) . '</strong></td>
                        <td></td>
                    </tr>
                </tbody>
            </table>
        </body>
        </html>';

        // Use DomPDF to generate proper PDF
        try {
            $pdf = app('dompdf.wrapper');
            $pdf->loadHTML($html);
            $pdf->setPaper('A4', 'landscape');

            return $pdf->download($fileName);
        } catch (\Exception $e) {
            // Fallback to simple HTML if DomPDF is not available
            return response($html, 200, [
                'Content-Type' => 'text/html',
                'Content-Disposition' => 'attachment; filename="' . str_replace('.pdf', '_report.html', $fileName) . '"',
            ]);
        }
    }

    /**
     * Export cash advance summary as Excel
     */
    private function exportCashAdvanceSummaryExcel($cashAdvances)
    {
        $fileName = 'cash_advance_summary_' . date('Y-m-d_H-i-s') . '.csv';

        // Create CSV content with proper headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->streamDownload(function () use ($cashAdvances) {
            $output = fopen('php://output', 'w');

            // Initialize totals
            $totalRequested = 0;
            $totalApproved = 0;
            $totalPaid = 0;
            $totalOutstanding = 0;

            // Write header row
            fputcsv($output, [
                'Reference Number',
                'Employee',
                'Request Date',
                'Requested Amount',
                'Approved Amount',
                'Total Paid',
                'Outstanding Balance',
                'Status'
            ]);

            // Write data rows
            foreach ($cashAdvances as $cashAdvance) {
                $totalRequested += $cashAdvance->requested_amount;
                $totalApproved += $cashAdvance->approved_amount ?: 0;
                $totalPaid += $cashAdvance->total_paid;
                $totalOutstanding += $cashAdvance->outstanding_balance;

                fputcsv($output, [
                    $cashAdvance->reference_number,
                    $cashAdvance->employee->full_name,
                    $cashAdvance->requested_date->format('M d, Y'),
                    number_format($cashAdvance->requested_amount, 2),
                    number_format($cashAdvance->approved_amount ?: 0, 2),
                    number_format($cashAdvance->total_paid, 2),
                    number_format($cashAdvance->outstanding_balance, 2),
                    ucfirst($cashAdvance->status)
                ]);
            }

            // Write totals row
            fputcsv($output, [
                'TOTALS',
                '',
                '',
                number_format($totalRequested, 2),
                number_format($totalApproved, 2),
                number_format($totalPaid, 2),
                number_format($totalOutstanding, 2),
                ''
            ]);

            fclose($output);
        }, $fileName, $headers);
    }
}
