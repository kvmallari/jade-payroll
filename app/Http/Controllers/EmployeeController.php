<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\Department;
use App\Models\Position;
use App\Models\User;
use App\Models\TimeSchedule;
use App\Models\DaySchedule;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Illuminate\Validation\Rule;
use Spatie\Permission\Models\Role;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;

class EmployeeController extends Controller
{
    use AuthorizesRequests;
    /**
     * Display a listing of the resource.
     */
    public function index(Request $request)
    {
        $this->authorize('view employees');

        $query = Employee::with(['user', 'department', 'position', 'company']);

        // Apply company scope - HR Head and HR Staff can only see their company's employees
        $user = Auth::user();
        if (!$user->isSuperAdmin()) {
            $query->where('company_id', $user->company_id);
        } else {
            // For System Administrator, apply company filter if provided
            if ($request->filled('company')) {
                $company = \App\Models\Company::whereRaw('LOWER(name) = ?', [strtolower($request->company)])->first();
                if ($company) {
                    $query->where('company_id', $company->id);
                }
            }
        }

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        if ($request->filled('employment_type')) {
            $query->where('employment_type', $request->employment_type);
        }

        // Apply sorting
        if ($request->filled('sort_name')) {
            if ($request->sort_name === 'asc') {
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
            } elseif ($request->sort_name === 'desc') {
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
            }
        } elseif ($request->filled('sort_hire_date')) {
            $query->orderBy('hire_date', $request->sort_hire_date);
        } else {
            // Default sorting - latest records first
            $query->latest();
        }

        // Paginate with configurable records per page (default 10)
        $perPage = $request->get('per_page', 10);
        $employees = $query->paginate($perPage)->withQueryString();
        $departments = Department::active()->get();

        // Get companies for filter (only for System Administrator)
        $companies = [];
        if ($user->isSuperAdmin()) {
            $companies = \App\Models\Company::latest('created_at')->get();
        }

        // Get performance data for current month (based on DTR)
        $currentMonth = \Carbon\Carbon::now();
        $startOfMonth = $currentMonth->copy()->startOfMonth();
        $endOfMonth = $currentMonth->copy()->endOfMonth();

        // Calculate performance metrics for active employees
        $performanceData = Employee::where('employment_status', 'active')
            ->with(['timeLogs' => function ($query) use ($startOfMonth, $endOfMonth) {
                $query->whereBetween('log_date', [$startOfMonth, $endOfMonth]);
            }])
            ->get()
            ->map(function ($employee) {
                $totalHours = $employee->timeLogs->sum('regular_hours');
                // Calculate hourly rate based on rate_type and fixed_rate
                $hourlyRate = match ($employee->rate_type) {
                    'hourly' => $employee->fixed_rate,
                    'daily' => $employee->fixed_rate / 8,
                    'weekly' => $employee->fixed_rate / 40,
                    'semi_monthly' => $employee->fixed_rate / (22 * 8 / 2),
                    'monthly' => $employee->fixed_rate / (22 * 8),
                    default => $employee->fixed_rate / (22 * 8) // default to monthly calculation
                };
                $calculatedSalary = $totalHours * $hourlyRate;

                return [
                    'employee' => $employee,
                    'total_hours' => $totalHours,
                    'calculated_salary' => $calculatedSalary,
                    'avg_daily_hours' => $employee->timeLogs->count() > 0 ? $totalHours / $employee->timeLogs->count() : 0,
                ];
            })
            ->filter(function ($data) {
                return $data['total_hours'] > 0; // Only include employees with DTR records
            });

        // Top 5 performers (highest calculated salary)
        $topPerformers = $performanceData->sortByDesc('calculated_salary')->take(5);

        // Least 5 performers (lowest calculated salary but still have some hours)
        $leastPerformers = $performanceData->sortBy('calculated_salary')->take(5);

        // Calculate summary statistics for employees
        $summaryQuery = Employee::query();

        // Apply same filters for summary
        if ($request->filled('search')) {
            $search = $request->search;
            $summaryQuery->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%");
                    });
            });
        }
        if ($request->filled('department')) {
            $summaryQuery->where('department_id', $request->department);
        }
        if ($request->filled('employment_status')) {
            $summaryQuery->where('employment_status', $request->employment_status);
        }
        if ($request->filled('employment_type')) {
            $summaryQuery->where('employment_type', $request->employment_type);
        }

        // Employment Type Statistics
        $employmentTypeStats = $summaryQuery->clone()
            ->selectRaw('employment_type, COUNT(*) as count')
            ->groupBy('employment_type')
            ->pluck('count', 'employment_type')
            ->toArray();

        // Benefits Status Statistics
        $benefitsStatusStats = $summaryQuery->clone()
            ->selectRaw('benefits_status, COUNT(*) as count')
            ->whereNotNull('benefits_status')
            ->groupBy('benefits_status')
            ->pluck('count', 'benefits_status')
            ->toArray();

        // Pay Frequency Statistics
        $payFrequencyStats = $summaryQuery->clone()
            ->selectRaw('pay_schedule, COUNT(*) as count')
            ->whereNotNull('pay_schedule')
            ->groupBy('pay_schedule')
            ->pluck('count', 'pay_schedule')
            ->toArray();

        // Rate Type Statistics
        $rateTypeStats = $summaryQuery->clone()
            ->selectRaw('rate_type, COUNT(*) as count')
            ->whereNotNull('rate_type')
            ->groupBy('rate_type')
            ->pluck('count', 'rate_type')
            ->toArray();

        $summaryStats = [
            'employment_types' => $employmentTypeStats,
            'benefits_status' => $benefitsStatusStats,
            'pay_frequency' => $payFrequencyStats,
            'rate_types' => $rateTypeStats,
        ];

        // Return JSON for AJAX requests
        if ($request->wantsJson() || $request->ajax()) {
            return response()->json([
                'employees' => $employees,
                'departments' => $departments,
                'companies' => $companies,
                'topPerformers' => $topPerformers,
                'leastPerformers' => $leastPerformers,
                'currentMonth' => $currentMonth,
                'summaryStats' => $summaryStats,
                'html' => view('employees.partials.employee-list', compact('employees'))->render(),
                'pagination' => view('employees.partials.pagination', compact('employees'))->render()
            ]);
        }

        return view('employees.index', compact('employees', 'departments', 'companies', 'topPerformers', 'leastPerformers', 'currentMonth', 'summaryStats'));
    }

    /**
     * Show the form for creating a new resource.
     */
    public function create()
    {
        $this->authorize('create employees');

        $departments = Department::active()->get();
        $positions = Position::active()->get();
        $timeSchedules = TimeSchedule::active()->get();
        $daySchedules = DaySchedule::active()->get();
        $employmentTypes = \App\Models\EmploymentType::active()->get();
        $roles = Role::whereIn('name', ['HR Head', 'HR Staff', 'Employee'])->get();
        $paySchedules = \App\Models\PayScheduleSetting::all();

        // Get employee default settings
        $employeeSettings = [
            'employee_number_prefix' => Cache::get('employee_setting_employee_number_prefix', 'EMP'),
            'auto_generate_employee_number' => Cache::get('employee_setting_auto_generate_employee_number', true),
            'default_department_id' => Cache::get('employee_setting_default_department_id'),
            'default_position_id' => Cache::get('employee_setting_default_position_id'),
            'default_employment_type' => Cache::get('employee_setting_default_employment_type', 'regular'),
            'default_employment_status' => Cache::get('employee_setting_default_employment_status', 'active'),
            'default_time_schedule_id' => Cache::get('employee_setting_default_time_schedule_id'),
            'default_day_schedule' => Cache::get('employee_setting_default_day_schedule', 'monday_to_friday'),
            'default_pay_schedule' => Cache::get('employee_setting_default_pay_schedule'),
            'default_paid_leaves' => Cache::get('employee_setting_default_paid_leaves', 15),
        ];



        // Generate next employee number for auto-generate mode
        $nextEmployeeNumber = '';
        if ($employeeSettings['auto_generate_employee_number']) {
            $prefix = $employeeSettings['employee_number_prefix'];
            $currentYear = date('Y');
            $lastEmployee = \App\Models\Employee::where('employee_number', 'LIKE', $prefix . '-' . $currentYear . '-%')
                ->orderBy('employee_number', 'desc')
                ->first();

            if ($lastEmployee) {
                // Extract the numeric part and increment
                $parts = explode('-', $lastEmployee->employee_number);
                $lastNumber = intval(end($parts));
                $nextNumber = $lastNumber + 1;
            } else {
                $nextNumber = Cache::get('employee_setting_employee_number_start', 1);
            }

            $nextEmployeeNumber = $prefix . '-' . $currentYear . '-' . str_pad($nextNumber, 4, '0', STR_PAD_LEFT);
        }

        return view('employees.create', compact('departments', 'positions', 'timeSchedules', 'daySchedules', 'employmentTypes', 'roles', 'paySchedules', 'employeeSettings', 'nextEmployeeNumber'));
    }

    /**
     * Store a newly created resource in storage.
     */
    public function store(Request $request)
    {
        // $this->authorize('create employees');



        $validated = $request->validate([
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'employee_number' => 'required|string|unique:employees,employee_number|unique:users,employee_id',
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'email' => 'required|email|unique:users,email',
            'birth_date' => 'required|date',
            'gender' => 'required|in:male,female',
            'civil_status' => 'required|in:single,married,divorced,widowed',
            'phone' => 'nullable|string|max:20',
            'address' => 'required|string',
            'postal_code' => 'required|digits:4',
            'hire_date' => 'required|date',
            // 'paid_leaves' => $paidLeavesRule,
            'benefits_status' => 'required|in:with_benefits,without_benefits',
            'employment_type_id' => 'required|exists:employment_types,id',
            'employment_status' => 'required|in:active,inactive,terminated,resigned',
            'pay_schedule' => 'required|in:daily,weekly,semi_monthly,monthly',
            'pay_schedule_id' => 'required|exists:pay_schedules,id',
            'sss_number' => 'nullable|string|max:20',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number' => 'nullable|string|max:20',
            'tin_number' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'role' => 'required|exists:roles,name',
            'time_schedule_id' => 'required|exists:time_schedules,id',
            'day_schedule_id' => 'required|exists:day_schedules,id',
            'rate_type' => 'required|in:hourly,daily,weekly,semi_monthly,monthly',
            'fixed_rate' => 'required|numeric|min:0.01',
        ]);


        try {
            // Map employment status to user status
            $userStatusMap = [
                'active' => 'active',
                'inactive' => 'inactive',
                'terminated' => 'inactive',
                'resigned' => 'inactive'
            ];

            $userStatus = $userStatusMap[$validated['employment_status']] ?? 'active';

            // Get company_id from the logged-in user (HR Head/Staff inherit their company)
            $companyId = Auth::user()->company_id;

            // Create user account
            $user = User::create([
                'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
                'email' => $validated['email'],
                'password' => Hash::make($validated['employee_number']), // Use employee number as default password
                'employee_id' => $validated['employee_number'],
                'company_id' => $companyId,
                'status' => $userStatus,
                'email_verified_at' => now(),
            ]);

            // Assign role to user
            $user->assignRole($validated['role']);

            // Create employee record
            $employeeData = collect($validated)->except(['email', 'role'])->toArray();
            $employeeData['user_id'] = $user->id;
            $employeeData['company_id'] = $companyId;

            // Set paid_leaves to null if benefits_status is without_benefits and paid_leaves is empty
            if ($employeeData['benefits_status'] === 'without_benefits' && empty($employeeData['paid_leaves'])) {
                $employeeData['paid_leaves'] = null;
            }

            $employee = Employee::create($employeeData);

            return redirect()->route('employees.show', $employee)
                ->with('success', "Employee created successfully! Default password is: {$validated['employee_number']}");
        } catch (\Exception $e) {
            // Log the detailed error for debugging
            Log::error('Employee creation failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'input' => $request->except(['password']),
                'user_id' => $user->id ?? null
            ]);

            // If user was created but employee creation failed, clean up the user
            if (isset($user) && $user->exists) {
                $user->delete();
            }

            // Provide user-friendly error messages for common issues
            $errorMessage = 'Failed to create employee.';
            if (strpos($e->getMessage(), 'Duplicate entry') !== false) {
                if (strpos($e->getMessage(), 'employee_number') !== false) {
                    $errorMessage = 'Employee number already exists. Please use a different employee number.';
                } elseif (strpos($e->getMessage(), 'email') !== false) {
                    $errorMessage = 'Email address already exists. Please use a different email address.';
                } elseif (strpos($e->getMessage(), 'employee_id') !== false) {
                    $errorMessage = 'Employee ID conflict detected. Please try again or contact administrator.';
                }
            }

            return back()->withInput()
                ->withErrors(['error' => $errorMessage]);
        }
    }

    /**$deductions = $this->calcu
     * Display the specified resource.
     */
    public function show(Employee $employee)
    {
        $this->authorize('view employees');

        $employee->load(['user.roles', 'department', 'position', 'timeSchedule', 'daySchedule', 'timeLogs', 'payrollDetails']);

        return view('employees.show', compact('employee'));
    }

    /**
     * Show the form for editing the specified resource.
     */
    public function edit(Employee $employee)
    {
        $this->authorize('edit employees');

        $employee->load(['user.roles', 'timeSchedule', 'daySchedule', 'employmentType']);
        $departments = Department::active()->get();
        $positions = Position::active()->get();
        $timeSchedules = TimeSchedule::active()->get();
        $daySchedules = DaySchedule::active()->get();
        $employmentTypes = \App\Models\EmploymentType::active()->get();
        $roles = Role::whereIn('name', ['HR Head', 'HR Staff', 'Employee'])->get();

        return view('employees.edit', compact('employee', 'departments', 'positions', 'timeSchedules', 'daySchedules', 'employmentTypes', 'roles'));
    }

    /**
     * Update the specified resource in storage.
     */
    public function update(Request $request, Employee $employee)
    {
        $this->authorize('edit employees');

        Log::info('Employee update request received', [
            'employee_id' => $employee->id,
            'employee_number' => $employee->employee_number,
            'request_data' => $request->all(),
            'rate_fields' => [
                'rate_type' => $request->get('rate_type'),
                'fixed_rate' => $request->get('fixed_rate')
            ]
        ]);

        $validated = $request->validate([
            'first_name' => 'required|string|max:255',
            'middle_name' => 'nullable|string|max:255',
            'last_name' => 'required|string|max:255',
            'suffix' => 'nullable|string|max:10',
            'email' => ['required', 'email', Rule::unique('users')->ignore($employee->user_id)],
            'employee_number' => ['required', 'string', Rule::unique('employees')->ignore($employee->id)],
            'department_id' => 'required|exists:departments,id',
            'position_id' => 'required|exists:positions,id',
            'birth_date' => 'required|date|before:today',
            'gender' => 'required|in:male,female',
            'civil_status' => 'required|in:single,married,divorced,widowed',
            'phone' => 'nullable|string|max:20',
            'address' => 'required|string',
            'postal_code' => 'required|digits:4',
            'hire_date' => 'required|date',
            'benefits_status' => 'required|in:with_benefits,without_benefits',
            'employment_type_id' => 'required|exists:employment_types,id',
            'employment_status' => 'required|in:active,inactive,terminated,resigned',
            'pay_schedule' => 'required|in:daily,weekly,semi_monthly,monthly',
            'pay_schedule_id' => 'required|exists:pay_schedules,id',
            'sss_number' => 'nullable|string|max:20',
            'philhealth_number' => 'nullable|string|max:20',
            'pagibig_number' => 'nullable|string|max:20',
            'tin_number' => 'nullable|string|max:20',
            'emergency_contact_name' => 'nullable|string|max:255',
            'emergency_contact_relationship' => 'nullable|string|max:100',
            'emergency_contact_phone' => 'nullable|string|max:20',
            'bank_name' => 'nullable|string|max:255',
            'bank_account_number' => 'nullable|string|max:50',
            'bank_account_name' => 'nullable|string|max:255',
            'role' => 'nullable|exists:roles,name',
            'time_schedule_id' => 'required|exists:time_schedules,id',
            'day_schedule_id' => 'required|exists:day_schedules,id',
            'rate_type' => 'required|in:hourly,daily,weekly,semi_monthly,monthly',
            'fixed_rate' => 'required|numeric|min:0.01',
        ]);

        try {
            Log::info('Employee update attempt', [
                'employee_id' => $employee->id,
                'validated_data' => $validated,
                'before_update' => [
                    'rate_type' => $employee->rate_type,
                    'fixed_rate' => $employee->fixed_rate
                ]
            ]);

            // Map employment status to user status
            $userStatusMap = [
                'active' => 'active',
                'inactive' => 'inactive',
                'terminated' => 'inactive',
                'resigned' => 'inactive'
            ];

            $userStatus = $userStatusMap[$validated['employment_status']] ?? 'active';

            // Update user account
            $employee->user->update([
                'name' => trim("{$validated['first_name']} {$validated['last_name']}"),
                'email' => $validated['email'],
                'employee_id' => $validated['employee_number'],
                'status' => $userStatus,
            ]);

            // Note: Role updates are disabled in edit form for security
            // Role changes should be handled through separate admin interface

            // Update employee record
            $employeeData = collect($validated)->except(['email', 'role'])->toArray();

            // Update employee record - only use rate_type and fixed_rate
            $employeeData = collect($validated)->except(['email', 'role'])->toArray();

            Log::info('Employee data prepared for update', [
                'employee_id' => $employee->id,
                'employee_data' => $employeeData
            ]);

            $employee->update($employeeData);

            Log::info('Employee updated successfully', [
                'employee_id' => $employee->id,
                'after_update' => [
                    'rate_type' => $employee->fresh()->rate_type,
                    'fixed_rate' => $employee->fresh()->fixed_rate
                ],
                'new_employment_status' => $employee->fresh()->employment_status,
                'user_status' => $userStatus
            ]);

            return redirect()->route('employees.show', $employee)
                ->with('success', 'Employee updated successfully!');
        } catch (\Exception $e) {
            Log::error('Employee update failed', [
                'employee_id' => $employee->id,
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
                'request_data' => $request->all()
            ]);

            return back()->withInput()
                ->withErrors(['error' => 'Failed to update employee: ' . $e->getMessage()]);
        }
    }

    /**
     * Remove the specified resource from storage.
     */
    public function destroy(Employee $employee)
    {
        $this->authorize('delete employees');

        try {
            // Prevent deletion of System Administrator
            if ($employee->user && $employee->user->hasRole('System Admin')) {
                return redirect()->route('employees.index')
                    ->with('error', 'Cannot delete System Administrator account.');
            }

            // Prevent users from deleting their own employee record
            if (Auth::user()->employee && Auth::user()->employee->id === $employee->id) {
                return redirect()->route('employees.index')
                    ->with('error', 'You cannot delete your own employee record.');
            }

            $employeeName = $employee->full_name;

            // Delete the user account (this will cascade delete the employee)
            $employee->user->delete();

            return redirect()->route('employees.index')
                ->with('success', "Employee {$employeeName} deleted successfully!");
        } catch (\Exception $e) {
            return back()->withErrors(['error' => 'Failed to delete employee: ' . $e->getMessage()]);
        }
    }

    /**
     * Generate a unique employee number.
     */
    private function generateEmployeeNumber()
    {
        $year = date('Y');
        $lastEmployee = Employee::where('employee_number', 'like', "EMP-{$year}-%")
            ->orderBy('employee_number', 'desc')
            ->first();

        if ($lastEmployee) {
            $lastNumber = (int) substr($lastEmployee->employee_number, -4);
            $newNumber = str_pad($lastNumber + 1, 4, '0', STR_PAD_LEFT);
        } else {
            $newNumber = '0001';
        }

        return "EMP-{$year}-{$newNumber}";
    }

    /**
     * Calculate deductions for salary preview
     */
    public function calculateDeductions(Request $request)
    {
        $salary = (float) $request->input('salary', 0);
        $benefitsStatus = $request->input('benefits_status');
        $paySchedule = $request->input('pay_schedule');

        if ($salary <= 0) {
            return response()->json(['deductions' => [], 'total_deductions' => 0, 'net_pay' => 0]);
        }

        $deductions = [];
        $totalDeductions = 0;

        // Calculate basic pay components
        $basicPay = $salary; // Assuming salary input is basic pay
        $overtime = 0;
        $bonus = 0;
        $allowances = 0;
        $grossPay = $basicPay + $overtime + $bonus + $allowances;

        // Only calculate deductions if employee has benefits
        if ($benefitsStatus === 'with_benefits') {
            // Convert pay schedule to pay frequency
            $payFrequency = $paySchedule ?? 'semi_monthly';

            // Get active government deductions (SSS, PhilHealth, Pag-IBIG)
            $governmentDeductions = \App\Models\DeductionTaxSetting::active()
                ->where('type', 'government')
                ->get();

            $governmentDeductionTotal = 0;

            foreach ($governmentDeductions as $setting) {
                $amount = $setting->calculateDeduction($basicPay, $overtime, $bonus, $allowances, $grossPay, null, null, $salary, $payFrequency);

                if ($amount > 0) {
                    $deductions[] = [
                        'name' => $setting->name,
                        'amount' => $amount,
                        'formatted_amount' => '₱' . number_format($amount, 2),
                        'type' => $setting->type
                    ];
                    $totalDeductions += $amount;
                    $governmentDeductionTotal += $amount;
                }
            }

            // Calculate taxable income (gross pay minus government deductions)
            $taxableIncome = $grossPay - $governmentDeductionTotal;

            // Get withholding tax deductions
            $taxDeductions = \App\Models\DeductionTaxSetting::active()
                ->where('type', 'government')
                ->where('tax_table_type', 'withholding_tax')
                ->get();

            foreach ($taxDeductions as $setting) {
                $amount = $setting->calculateDeduction($basicPay, $overtime, $bonus, $allowances, $grossPay, $taxableIncome, null, $salary, $payFrequency);

                if ($amount > 0) {
                    $deductions[] = [
                        'name' => $setting->name,
                        'amount' => $amount,
                        'formatted_amount' => '₱' . number_format($amount, 2),
                        'type' => $setting->type
                    ];
                    $totalDeductions += $amount;
                }
            }
        }

        $netPay = $grossPay - $totalDeductions;

        return response()->json([
            'deductions' => $deductions,
            'total_deductions' => $totalDeductions,
            'formatted_total_deductions' => '₱' . number_format($totalDeductions, 2),
            'net_pay' => $netPay,
            'formatted_net_pay' => '₱' . number_format($netPay, 2),
            'gross_pay' => $grossPay,
            'formatted_gross_pay' => '₱' . number_format($grossPay, 2),
            'basic_pay' => $basicPay,
            'formatted_basic_pay' => '₱' . number_format($basicPay, 2),
            'taxable_income' => $taxableIncome ?? 0,
            'formatted_taxable_income' => '₱' . number_format($taxableIncome ?? 0, 2)
        ]);
    }

    /**
     * Check if employee number already exists
     */
    public function checkDuplicate(Request $request)
    {
        $request->validate([
            'employee_number' => 'required|string'
        ]);

        $exists = Employee::where('employee_number', $request->employee_number)->exists();

        return response()->json(['exists' => $exists]);
    }

    /**
     * Generate employee summary
     */
    public function generateSummary(Request $request)
    {
        $this->authorize('view employees');

        $format = $request->input('export', 'pdf');

        // Build query based on filters
        $query = Employee::with(['user', 'department', 'position']);

        // Apply filters
        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('first_name', 'like', "%{$search}%")
                    ->orWhere('last_name', 'like', "%{$search}%")
                    ->orWhere('employee_number', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('email', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('department')) {
            $query->where('department_id', $request->department);
        }

        if ($request->filled('employment_status')) {
            $query->where('employment_status', $request->employment_status);
        }

        // Apply sorting
        if ($request->filled('sort_name')) {
            if ($request->sort_name === 'asc') {
                $query->orderBy('first_name', 'asc')->orderBy('last_name', 'asc');
            } elseif ($request->sort_name === 'desc') {
                $query->orderBy('first_name', 'desc')->orderBy('last_name', 'desc');
            }
        } elseif ($request->filled('sort_hire_date')) {
            $query->orderBy('hire_date', $request->sort_hire_date);
        } else {
            $query->latest();
        }

        $employees = $query->get();

        if ($format === 'excel') {
            return $this->exportEmployeeSummaryExcel($employees);
        } else {
            return $this->exportEmployeeSummaryPDF($employees);
        }
    }

    /**
     * Export employee summary as PDF
     */
    private function exportEmployeeSummaryPDF($employees)
    {
        $fileName = 'employee_summary_' . date('Y-m-d_H-i-s') . '.pdf';

        // Calculate totals and statistics
        $totalEmployees = $employees->count();
        $activeEmployees = $employees->where('employment_status', 'active')->count();
        $inactiveEmployees = $employees->where('employment_status', 'inactive')->count();
        $avgBasicSalary = $employees->avg('basic_salary');

        // Create HTML content for PDF
        $html = '
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset="UTF-8">
            <meta http-equiv="Content-Type" content="text/html; charset=utf-8"/>
            <title>Employee Summary</title>
            <style>
                body { font-family: DejaVu Sans, Arial, sans-serif; font-size: 10px; margin: 20px; }
                .header { text-align: center; margin-bottom: 20px; }
                .header h1 { margin: 0; color: #333; font-size: 18px; }
                .header p { margin: 5px 0; color: #666; font-size: 12px; }
                .stats { margin-bottom: 20px; }
                .stats table { width: 100%; border-collapse: collapse; }
                .stats td { padding: 8px; border: 1px solid #ddd; text-align: center; }
                .stats .label { background-color: #f8f9fa; font-weight: bold; }
                table { width: 100%; border-collapse: collapse; margin-top: 20px; font-size: 9px; }
                th, td { border: 1px solid #ddd; padding: 6px; text-align: left; }
                th { background-color: #f8f9fa; font-weight: bold; }
                .text-right { text-align: right; }
                .currency { text-align: right; }
                .status-active { color: green; font-weight: bold; }
                .status-inactive { color: orange; font-weight: bold; }
                .status-terminated { color: red; font-weight: bold; }
                .status-resigned { color: gray; font-weight: bold; }
            </style>
        </head>
        <body>
            <div class="header">
                <h1>Employee Summary Report</h1>
                <p>Generated on: ' . date('F j, Y g:i A') . '</p>
            </div>
            
            <div class="stats">
                <table>
                    <tr>
                        <td class="label">Total Employees</td>
                        <td class="label">Active Employees</td>
                        <td class="label">Inactive Employees</td>
                        <td class="label">Average Basic Salary</td>
                    </tr>
                    <tr>
                        <td>' . $totalEmployees . '</td>
                        <td>' . $activeEmployees . '</td>
                        <td>' . $inactiveEmployees . '</td>
                        <td>₱' . number_format($avgBasicSalary, 2) . '</td>
                    </tr>
                </table>
            </div>
            
            <table>
                <thead>
                    <tr>
                        <th>Employee #</th>
                        <th>Full Name</th>
                        <th>Department</th>
                        <th>Position</th>
                        <th>Status</th>
                        <th>Hire Date</th>
                        <th class="currency">Basic Salary</th>
                        <th>Pay Schedule</th>
                    </tr>
                </thead>
                <tbody>';

        foreach ($employees as $employee) {
            $statusClass = 'status-' . $employee->employment_status;
            $html .= '
                    <tr>
                        <td>' . $employee->employee_number . '</td>
                        <td>' . $employee->full_name . '</td>
                        <td>' . $employee->department->name . '</td>
                        <td>' . $employee->position->title . '</td>
                        <td class="' . $statusClass . '">' . ucfirst($employee->employment_status) . '</td>
                        <td>' . $employee->hire_date->format('M d, Y') . '</td>
                        <td class="currency">₱' . number_format($employee->basic_salary, 2) . '</td>
                        <td>' . ucwords(str_replace('_', ' ', $employee->pay_schedule)) . '</td>
                    </tr>';
        }

        $html .= '
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
     * Export employee summary as Excel
     */
    private function exportEmployeeSummaryExcel($employees)
    {
        $fileName = 'employee_summary_' . date('Y-m-d_H-i-s') . '.csv';

        // Create CSV content with proper headers
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->streamDownload(function () use ($employees) {
            $output = fopen('php://output', 'w');

            // Write header row
            fputcsv($output, [
                'Employee Number',
                'Full Name',
                'Email',
                'Department',
                'Position',
                'Employment Status',
                'Employment Type',
                'Hire Date',
                'Basic Salary',
                'Pay Schedule',
                'Birth Date',
                'Address'
            ]);

            // Write data rows
            foreach ($employees as $employee) {
                fputcsv($output, [
                    $employee->employee_number,
                    $employee->full_name,
                    $employee->user->email ?? '',
                    $employee->department->name ?? '',
                    $employee->position->title ?? '',
                    ucfirst($employee->employment_status),
                    ucfirst($employee->employment_type),
                    $employee->hire_date->format('M d, Y'),
                    number_format($employee->basic_salary, 2),
                    ucwords(str_replace('_', ' ', $employee->pay_schedule)),
                    $employee->birth_date ? $employee->birth_date->format('M d, Y') : '',
                    $employee->address ?? ''
                ]);
            }

            fclose($output);
        }, $fileName, $headers);
    }

    /**
     * Get pay schedules by type for employee form
     */
    public function getPaySchedulesByType($type)
    {
        // Validate the type
        if (!in_array($type, ['daily', 'weekly', 'semi_monthly', 'monthly'])) {
            return response()->json(['error' => 'Invalid pay schedule type'], 400);
        }

        // Get active pay schedules for this type
        $paySchedules = \App\Models\PaySchedule::where('type', $type)
            ->where('is_active', true)
            ->orderBy('name')
            ->get(['id', 'name', 'is_default']);

        return response()->json($paySchedules);
    }
}
