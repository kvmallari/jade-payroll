<?php

namespace App\Http\Controllers;

use App\Models\Employee;
use App\Models\DTR;
use App\Models\TimeLog;
use App\Models\Holiday;
use App\Models\PayrollScheduleSetting;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use Barryvdh\DomPDF\Facade\Pdf;

class DTRController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display DTR management interface with period selection
     */
    public function index(Request $request)
    {
        $this->authorize('view time logs');

        // Get payroll settings
        $payrollSettings = PayrollScheduleSetting::first();
        if (!$payrollSettings) {
            return redirect()->back()->with('error', 'Payroll schedule settings not configured.');
        }

        $currentPeriod = $this->getCurrentPayrollPeriod($payrollSettings);
        $availablePeriods = $this->getAvailablePeriods($payrollSettings);

        // If no specific period requested, show period selection
        if (!$request->has('period')) {
            return view('dtr.index', compact('currentPeriod', 'availablePeriods'));
        }

        // Get selected period details
        $selectedPeriod = $this->getPeriodByKey($request->get('period'), $payrollSettings);
        if (!$selectedPeriod) {
            return redirect()->route('dtr.index')->with('error', 'Invalid period selected.');
        }

        // Get employees for the selected period
        $employees = Employee::with(['user', 'department'])
            ->where('status', 'active')
            ->get();

        return view('dtr.period-employees', compact('employees', 'selectedPeriod', 'payrollSettings'));
    }

    /**
     * Show DTR record
     */
    public function show($id)
    {
        try {
            // Get DTR by ID
            $dtrRecord = DB::table('d_t_r_s')->where('id', $id)->first();

            if (!$dtrRecord) {
                return redirect()->route('dtr.index')->with('error', "DTR with ID {$id} not found. Please check the DTR list for available records.");
            }

            // Get employee information with day schedule
            $employee = null;
            $employeeModel = null;
            if ($dtrRecord->employee_id) {
                $employeeModel = Employee::with('daySchedule')->find($dtrRecord->employee_id);
                if ($employeeModel) {
                    $userRecord = DB::table('users')->where('id', $employeeModel->user_id)->first();
                    $employee = [
                        'id' => $employeeModel->id,
                        'first_name' => $employeeModel->first_name ?? '',
                        'last_name' => $employeeModel->last_name ?? '',
                        'employee_number' => $employeeModel->employee_number ?? '',
                        'user' => $userRecord ? [
                            'name' => $userRecord->name ?? '',
                            'email' => $userRecord->email ?? ''
                        ] : null
                    ];
                }
            }

            // Create DTR object with all necessary data
            $dtr = [
                'id' => $dtrRecord->id,
                'employee_id' => $dtrRecord->employee_id,
                'payroll_id' => $dtrRecord->payroll_id,
                'period_start' => $dtrRecord->period_start ?? now()->startOfMonth()->format('Y-m-d'),
                'period_end' => $dtrRecord->period_end ?? now()->endOfMonth()->format('Y-m-d'),
                'period_type' => $dtrRecord->period_type ?? 'monthly',
                'dtr_data' => $dtrRecord->dtr_data ?? '{}',
                'total_regular_hours' => $dtrRecord->total_regular_hours ?? 0,
                'total_overtime_hours' => $dtrRecord->total_overtime_hours ?? 0,
                'total_late_hours' => $dtrRecord->total_late_hours ?? 0,
                'regular_days' => $dtrRecord->regular_days ?? 0,
                'saturday_count' => $dtrRecord->saturday_count ?? 0,
                'status' => $dtrRecord->status ?? 'draft',
                'employee' => $employee
            ];

            // Convert to object for easier template access
            $dtr = (object) $dtr;
            if ($employee) {
                $dtr->employee = (object) $employee;
                if ($employee['user']) {
                    $dtr->employee->user = (object) $employee['user'];
                }
            }

            Log::info('DTR Show Method', [
                'dtr_id' => $dtr->id,
                'employee_id' => $dtr->employee_id,
                'period_start' => $dtr->period_start,
                'period_end' => $dtr->period_end
            ]);

            // Create array of all dates in the DTR period for easy iteration
            $periodDates = [];
            $current = \Carbon\Carbon::parse($dtr->period_start);
            $end = \Carbon\Carbon::parse($dtr->period_end);

            while ($current->lte($end)) {
                // Use employee's day schedule to determine rest day instead of hardcoded weekend
                $isRestDay = $employeeModel && $employeeModel->daySchedule ? !$employeeModel->daySchedule->isWorkingDay($current) : $current->isWeekend();

                $periodDates[] = [
                    'date' => $current->format('Y-m-d'),
                    'day_name' => $current->format('l'),
                    'day_short' => $current->format('D'),
                    'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                    'formatted' => $current->format('M d'),
                    'carbon' => $current->copy()
                ];
                $current->addDay();
            }

            Log::info('DTR Rendering view', ['period_dates_count' => count($periodDates)]);

            return view('dtr.show_simple', compact('dtr', 'periodDates'));
        } catch (\Exception $e) {
            Log::error('DTR Show Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Error loading DTR: ' . $e->getMessage());
        }
    }

    /**
     * Edit DTR record
     */
    public function edit($id)
    {
        $this->authorize('edit time logs');

        try {
            // Get DTR by ID using raw query
            $dtrRecord = DB::table('d_t_r_s')->where('id', $id)->first();

            if (!$dtrRecord) {
                return redirect()->route('dtr.index')->with('error', "DTR with ID {$id} not found.");
            }

            // Get employee information with day schedule
            $employee = null;
            $employeeModel = null;
            if ($dtrRecord->employee_id) {
                $employeeModel = Employee::with('daySchedule')->find($dtrRecord->employee_id);
                if ($employeeModel) {
                    $userRecord = DB::table('users')->where('id', $employeeModel->user_id)->first();
                    $departmentRecord = DB::table('departments')->where('id', $employeeModel->department_id)->first();

                    $employee = [
                        'id' => $employeeModel->id,
                        'first_name' => $employeeModel->first_name ?? '',
                        'last_name' => $employeeModel->last_name ?? '',
                        'employee_number' => $employeeModel->employee_number ?? '',
                        'user' => $userRecord ? [
                            'name' => $userRecord->name ?? '',
                            'email' => $userRecord->email ?? ''
                        ] : null,
                        'department' => $departmentRecord ? [
                            'name' => $departmentRecord->name ?? ''
                        ] : null
                    ];
                }
            }

            // Create DTR object
            $dtr = [
                'id' => $dtrRecord->id,
                'employee_id' => $dtrRecord->employee_id,
                'payroll_id' => $dtrRecord->payroll_id,
                'period_start' => $dtrRecord->period_start ?? now()->startOfMonth()->format('Y-m-d'),
                'period_end' => $dtrRecord->period_end ?? now()->endOfMonth()->format('Y-m-d'),
                'period_type' => $dtrRecord->period_type ?? 'monthly',
                'dtr_data' => $dtrRecord->dtr_data ?? '{}',
                'total_regular_hours' => $dtrRecord->total_regular_hours ?? 0,
                'total_overtime_hours' => $dtrRecord->total_overtime_hours ?? 0,
                'total_late_hours' => $dtrRecord->total_late_hours ?? 0,
                'regular_days' => $dtrRecord->regular_days ?? 0,
                'saturday_count' => $dtrRecord->saturday_count ?? 0,
                'status' => $dtrRecord->status ?? 'draft',
                'employee' => $employee
            ];

            // Convert to object
            $dtr = (object) $dtr;
            if ($employee) {
                $dtr->employee = (object) $employee;
                if ($employee['user']) {
                    $dtr->employee->user = (object) $employee['user'];
                }
                if ($employee['department']) {
                    $dtr->employee->department = (object) $employee['department'];
                }
            }

            // Create array of all dates in the DTR period for easy iteration
            $periodDates = [];
            $current = \Carbon\Carbon::parse($dtr->period_start);
            $end = \Carbon\Carbon::parse($dtr->period_end);

            while ($current->lte($end)) {
                // Use employee's day schedule to determine rest day instead of hardcoded weekend
                $isRestDay = $employeeModel && $employeeModel->daySchedule ? !$employeeModel->daySchedule->isWorkingDay($current) : $current->isWeekend();

                $periodDates[] = [
                    'date' => $current->format('Y-m-d'),
                    'day_name' => $current->format('l'),
                    'day_short' => $current->format('D'),
                    'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                    'formatted' => $current->format('M d'),
                    'carbon' => $current->copy()
                ];
                $current->addDay();
            }

            return view('dtr.edit', compact('dtr', 'periodDates'));
        } catch (\Exception $e) {
            Log::error('DTR Edit Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Error loading DTR for editing: ' . $e->getMessage());
        }
    }

    /**
     * Update DTR with time logs
     */
    public function update(Request $request, $id)
    {
        $this->authorize('edit time logs');

        try {
            // Get DTR by ID
            $dtrRecord = DB::table('d_t_r_s')->where('id', $id)->first();

            if (!$dtrRecord) {
                return redirect()->route('dtr.index')->with('error', "DTR with ID {$id} not found.");
            }

            // Get employee with day schedule for dynamic rest day determination
            $employee = Employee::with('daySchedule')->find($dtrRecord->employee_id);

            // Validate the request
            $request->validate([
                'time_logs' => 'required|array',
                'time_logs.*.date' => 'required|date',
                'time_logs.*.time_in' => 'nullable|string',
                'time_logs.*.break_start' => 'nullable|string',
                'time_logs.*.break_end' => 'nullable|string',
                'time_logs.*.time_out' => 'nullable|string',
                'time_logs.*.overtime_start' => 'nullable|string',
                'time_logs.*.overtime_end' => 'nullable|string',
            ]);

            // Process the time logs
            $timeLogs = $request->input('time_logs', []);
            $dtrData = [];
            $totalRegularHours = 0;
            $totalOvertimeHours = 0;
            $totalLateHours = 0;
            $regularDays = 0;
            $saturdayCount = 0;

            foreach ($timeLogs as $log) {
                if (empty($log['time_in']) && empty($log['time_out'])) {
                    continue; // Skip empty logs
                }

                $date = $log['date'];
                $carbon = \Carbon\Carbon::parse($date);

                // Use employee's day schedule to determine rest day instead of hardcoded weekend
                $isRestDay = $employee && $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($carbon) : $carbon->isWeekend();

                // Store the time log
                $dtrData[$date] = [
                    'date' => $date,
                    'day_name' => $carbon->format('l'),
                    'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                    'time_in' => $log['time_in'] ?? null,
                    'break_start' => $log['break_start'] ?? null,
                    'break_end' => $log['break_end'] ?? null,
                    'time_out' => $log['time_out'] ?? null,
                    'overtime_start' => $log['overtime_start'] ?? null,
                    'overtime_end' => $log['overtime_end'] ?? null,
                ];

                // Calculate hours (basic calculation)
                if (!empty($log['time_in']) && !empty($log['time_out'])) {
                    try {
                        $timeIn = \Carbon\Carbon::parse($date . ' ' . $log['time_in']);
                        $timeOut = \Carbon\Carbon::parse($date . ' ' . $log['time_out']);

                        if ($timeOut->gt($timeIn)) {
                            $regularHours = $timeOut->diffInHours($timeIn);

                            // Subtract break time if provided
                            if (!empty($log['break_start']) && !empty($log['break_end'])) {
                                $breakStart = \Carbon\Carbon::parse($date . ' ' . $log['break_start']);
                                $breakEnd = \Carbon\Carbon::parse($date . ' ' . $log['break_end']);
                                if ($breakEnd->gt($breakStart)) {
                                    $breakHours = $breakEnd->diffInHours($breakStart);
                                    $regularHours = max(0, $regularHours - $breakHours);
                                }
                            }

                            $totalRegularHours += $regularHours;

                            if (!$carbon->isWeekend()) {
                                $regularDays++;
                            } elseif ($carbon->isSaturday()) {
                                $saturdayCount++;
                            }
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error calculating hours for date ' . $date, ['error' => $e->getMessage()]);
                    }
                }

                // Calculate overtime hours
                if (!empty($log['overtime_start']) && !empty($log['overtime_end'])) {
                    try {
                        $overtimeStart = \Carbon\Carbon::parse($date . ' ' . $log['overtime_start']);
                        $overtimeEnd = \Carbon\Carbon::parse($date . ' ' . $log['overtime_end']);

                        if ($overtimeEnd->gt($overtimeStart)) {
                            $overtimeHours = $overtimeEnd->diffInHours($overtimeStart);
                            $totalOvertimeHours += $overtimeHours;
                        }
                    } catch (\Exception $e) {
                        Log::warning('Error calculating overtime hours for date ' . $date, ['error' => $e->getMessage()]);
                    }
                }
            }

            // Update the DTR record
            DB::table('d_t_r_s')->where('id', $id)->update([
                'dtr_data' => json_encode($dtrData),
                'total_regular_hours' => $totalRegularHours,
                'total_overtime_hours' => $totalOvertimeHours,
                'total_late_hours' => $totalLateHours,
                'regular_days' => $regularDays,
                'saturday_count' => $saturdayCount,
                'status' => 'completed',
                'updated_at' => now()
            ]);

            Log::info('DTR Updated Successfully', [
                'dtr_id' => $id,
                'total_regular_hours' => $totalRegularHours,
                'total_overtime_hours' => $totalOvertimeHours,
                'regular_days' => $regularDays
            ]);

            return redirect()->route('dtr.show', $id)->with('success', 'DTR updated successfully! Total hours calculated: ' . $totalRegularHours . ' regular, ' . $totalOvertimeHours . ' overtime.');
        } catch (\Exception $e) {
            Log::error('DTR Update Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Error updating DTR: ' . $e->getMessage())->withInput();
        }
    }

    /**
     * Create DTR instantly for employees in a payroll period - SIMPLIFIED VERSION
     */
    public function createInstant(Request $request)
    {
        // Debug: Log the request
        Log::info('DTR Create Instant called', [
            'request_data' => $request->all(),
            'user_id' => Auth::id(),
            'can_create_time_logs' => Auth::user()->can('create time logs')
        ]);

        try {
            // Get the payroll ID from the request
            $payrollId = $request->input('payroll_id');
            $periodStart = $request->input('period_start');
            $periodEnd = $request->input('period_end');

            Log::info('DTR Create Input', [
                'payroll_id' => $payrollId,
                'period_start' => $periodStart,
                'period_end' => $periodEnd
            ]);

            if (!$payrollId || !$periodStart || !$periodEnd) {
                Log::error('DTR Create Missing Parameters', [
                    'payroll_id' => $payrollId,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd
                ]);
                return redirect()->back()->with('error', 'Missing required parameters for DTR creation.');
            }

            // Get the payroll with employees
            Log::info('DTR About to find payroll', ['payroll_id' => $payrollId]);
            $payroll = \App\Models\Payroll::with('payrollDetails.employee')->find($payrollId);
            Log::info('DTR Payroll found', ['payroll' => $payroll ? 'YES' : 'NO']);

            if (!$payroll) {
                Log::error('DTR Payroll not found', ['payroll_id' => $payrollId]);
                return redirect()->back()->with('error', 'Payroll not found.');
            }

            // Check if we have employees
            Log::info('DTR Checking payroll details', ['count' => $payroll->payrollDetails->count()]);
            if ($payroll->payrollDetails->isEmpty()) {
                Log::error('DTR No employees in payroll');
                return redirect()->back()->with('error', 'No employees found in this payroll.');
            }

            $createdCount = 0;
            $firstDTRId = null;

            Log::info('DTR Starting creation process', ['payroll_id' => $payroll->id, 'employee_count' => $payroll->payrollDetails->count()]);

            // Create DTR for each employee in the payroll
            foreach ($payroll->payrollDetails as $detail) {
                $employee = $detail->employee;
                Log::info('DTR Processing employee', ['employee_id' => $employee->id, 'name' => $employee->first_name . ' ' . $employee->last_name]);

                // Check if DTR already exists for this employee and payroll period
                $existing = DB::table('d_t_r_s')->where([
                    'employee_id' => $employee->id,
                    'payroll_id' => $payroll->id,
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd
                ])->first();

                Log::info('DTR Existing check', ['existing' => $existing ? 'YES' : 'NO']);

                if ($existing) {
                    if (!$firstDTRId) {
                        $firstDTRId = $existing->id;
                    }
                    Log::info('DTR Skipping existing', ['dtr_id' => $existing->id]);
                    continue; // Skip if already exists
                }

                // Create DTR ID as combination of payroll_id and employee_id for uniqueness
                // Format: [payroll_id][employee_id] (e.g., payroll 12 + employee 11 = DTR ID 1211)
                $dtrId = (int)($payroll->id . str_pad($employee->id, 2, '0', STR_PAD_LEFT));

                // Generate empty daily time records for the period
                $dailyRecords = [];
                $currentDate = \Carbon\Carbon::parse($periodStart);
                $endDate = \Carbon\Carbon::parse($periodEnd);

                while ($currentDate->lte($endDate)) {
                    $dateStr = $currentDate->format('Y-m-d');
                    $dailyRecords[$dateStr] = [
                        'date' => $dateStr,
                        'day_name' => $currentDate->format('l'),
                        'is_weekend' => $currentDate->isWeekend(),
                        'time_in' => null,
                        'time_out' => null,
                        'break_start' => null,
                        'break_end' => null,
                        'overtime_start' => null,
                        'overtime_end' => null,
                        'regular_hours' => 0,
                        'overtime_hours' => 0,
                        'late_minutes' => 0,
                        'status' => $currentDate->isWeekend() ? 'weekend' : 'pending',
                        'remarks' => null
                    ];
                    $currentDate->addDay();
                }

                DB::table('d_t_r_s')->insert([
                    'id' => $dtrId,
                    'employee_id' => $employee->id,
                    'payroll_id' => $payroll->id,
                    'period_type' => 'semi_monthly',
                    'period_start' => $periodStart,
                    'period_end' => $periodEnd,
                    'month_year' => date('Y-m', strtotime($periodStart)),
                    'regular_days' => 0,
                    'saturday_count' => 0,
                    'dtr_data' => json_encode($dailyRecords),
                    'total_regular_hours' => 0.00,
                    'total_overtime_hours' => 0.00,
                    'total_late_hours' => 0.00,
                    'total_undertime_hours' => 0.00,
                    'status' => 'draft',
                    'created_by' => Auth::id() ?? 1,
                    'created_at' => now(),
                    'updated_at' => now()
                ]);

                if (!$firstDTRId) {
                    $firstDTRId = $dtrId;
                }

                $createdCount++;
                Log::info('DTR Created for employee', ['dtr_id' => $dtrId, 'employee_id' => $employee->id]);
            }

            Log::info('DTR Loop completed', ['created_count' => $createdCount, 'first_dtr_id' => $firstDTRId]);

            if ($createdCount > 0) {
                $message = "Successfully created {$createdCount} DTR records!";
                Log::info('DTR Redirecting to new DTR', ['dtr_id' => $firstDTRId]);
                return redirect()->route('dtr.show', $firstDTRId)->with('success', $message);
            } elseif ($firstDTRId) {
                $message = "DTR records already exist for this payroll period.";
                Log::info('DTR Redirecting to existing DTR', ['dtr_id' => $firstDTRId]);
                return redirect()->route('dtr.show', $firstDTRId)->with('info', $message);
            } else {
                Log::info('DTR No records found or created');
                return redirect()->back()->with('info', 'No DTR records were found or created.');
            }
        } catch (\Exception $e) {
            // Log the actual error for debugging
            Log::error('DTR Creation Error: ' . $e->getMessage());
            Log::error('DTR Creation Stack: ' . $e->getTraceAsString());

            return redirect()->back()->with('error', 'Failed to create DTR: ' . $e->getMessage());
        }
    }

    /**
     * Test DTR creation - debug method
     */
    public function testCreate()
    {
        try {
            // Get first payroll
            $payroll = \App\Models\Payroll::with('payrollDetails.employee')->first();

            if (!$payroll) {
                return response()->json(['error' => 'No payroll found']);
            }

            $employee = $payroll->payrollDetails->first()->employee;

            // Test DTR creation using raw DB insert
            $dtrId = DB::table('d_t_r_s')->insertGetId([
                'employee_id' => $employee->id,
                'payroll_id' => $payroll->id,
                'period_type' => 'semi_monthly',
                'period_start' => $payroll->period_start,
                'period_end' => $payroll->period_end,
                'month_year' => Carbon::parse($payroll->period_start)->format('Y-m'),
                'regular_days' => 0,
                'saturday_count' => 0,
                'dtr_data' => '{}',
                'total_regular_hours' => 0,
                'total_overtime_hours' => 0,
                'total_late_hours' => 0,
                'total_undertime_hours' => 0,
                'status' => 'draft',
                'created_by' => 1,
                'created_at' => now(),
                'updated_at' => now()
            ]);

            return response()->json([
                'success' => true,
                'dtr_id' => $dtrId,
                'employee' => $employee->first_name . ' ' . $employee->last_name
            ]);
        } catch (\Exception $e) {
            return response()->json([
                'error' => $e->getMessage(),
                'line' => $e->getLine(),
                'file' => $e->getFile()
            ]);
        }
    }

    // Helper methods
    private function getCurrentPayrollPeriod($payrollSettings)
    {
        $today = Carbon::now();

        if ($payrollSettings->frequency === 'semi_monthly') {
            if ($today->day <= 15) {
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->day(15);
                $payDate = $today->copy()->day(25);
            } else {
                $startDate = $today->copy()->day(16);
                $endDate = $today->copy()->endOfMonth();
                $payDate = $today->copy()->addMonth()->day(10);
            }
        } else {
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->endOfMonth();
            $payDate = $today->copy()->addMonth()->day(15);
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_date' => $payDate,
            'period_label' => $startDate->format('M d') . ' - ' . $endDate->format('M d, Y'),
            'pay_label' => 'Pay Date: ' . $payDate->format('M d, Y'),
        ];
    }

    private function getAvailablePeriods($payrollSettings)
    {
        // Implementation would go here
        return [];
    }

    private function getPeriodByKey($key, $payrollSettings)
    {
        // Implementation would go here
        return null;
    }

    /**
     * Show DTR import form.
     */
    public function importForm()
    {
        $this->authorize('import time logs');
        return view('dtr.import');
    }

    /**
     * Import DTR from Excel/CSV file.
     */
    public function import(Request $request)
    {
        $this->authorize('import time logs');

        $request->validate([
            'dtr_file' => 'required|file|mimes:xlsx,xls,csv|max:2048',
            'overwrite_existing' => 'boolean',
        ]);

        try {
            DB::beginTransaction();

            $import = new \App\Imports\DTRImport($request->boolean('overwrite_existing'));
            \Maatwebsite\Excel\Facades\Excel::import($import, $request->file('dtr_file'));

            DB::commit();

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errorCount = $import->getErrorCount();

            $message = "DTR import completed! Imported: {$importedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}";

            if ($errorCount > 0) {
                $errors = $import->getErrors();
                return redirect()->route('dtr.import')
                    ->with('warning', $message)
                    ->with('import_errors', $errors);
            }

            return redirect()->route('dtr.import')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DTR Import Error: ' . $e->getMessage());
            return redirect()->route('dtr.import')
                ->withErrors(['error' => 'Failed to import DTR: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Export DTR template.
     */
    public function exportTemplate()
    {
        $this->authorize('import time logs');

        $fileName = 'dtr_import_template_' . date('Y-m-d') . '.csv';

        // Create CSV template with headers and sample data
        $headers = [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => 'attachment; filename="' . $fileName . '"',
            'Cache-Control' => 'max-age=0',
        ];

        return response()->streamDownload(function () {
            $output = fopen('php://output', 'w');

            // CSV Headers
            fputcsv($output, [
                'Employee Number',
                'Date',
                'Time In',
                'Time Out',
                'Break In',
                'Break Out'
            ]);

            // Sample data showing both 12-hour (AM/PM) and 24-hour time formats
            fputcsv($output, [
                'EMP-2025-0001',
                '2024-11-10',
                '8:00 AM',
                '5:00 PM',
                '12:00 PM',
                '1:00 PM'
            ]);

            fputcsv($output, [
                'EMP-2025-0002',
                '2024-11-10',
                '09:00',
                '18:30',
                '12:30',
                '13:30'
            ]);

            fputcsv($output, [
                'EMP-2025-0003',
                '2024-11-10',
                '7:30AM',
                '4:30PM',
                '',
                ''
            ]);

            // Add empty rows for users to fill
            for ($i = 0; $i < 7; $i++) {
                fputcsv($output, ['', '', '', '', '', '']);
            }

            fclose($output);
        }, $fileName, $headers);
    }

    /**
     * Delete DTR record.
     */
    public function destroy($id)
    {
        $this->authorize('delete time logs');

        try {
            // Get DTR by ID
            $dtrRecord = DB::table('d_t_r_s')->where('id', $id)->first();

            if (!$dtrRecord) {
                return redirect()->route('dtr.index')->with('error', "DTR with ID {$id} not found.");
            }

            // Delete related time logs first (if any)
            TimeLog::where('employee_id', $dtrRecord->employee_id)
                ->whereBetween('log_date', [$dtrRecord->period_start, $dtrRecord->period_end])
                ->delete();

            // Delete DTR record
            DB::table('d_t_r_s')->where('id', $id)->delete();

            Log::info('DTR Deleted Successfully', [
                'dtr_id' => $id,
                'employee_id' => $dtrRecord->employee_id,
                'deleted_by' => Auth::id()
            ]);

            return redirect()->route('dtr.index')->with('success', 'DTR record deleted successfully!');
        } catch (\Exception $e) {
            Log::error('DTR Delete Error', [
                'error' => $e->getMessage(),
                'file' => $e->getFile(),
                'line' => $e->getLine()
            ]);

            return redirect()->back()->with('error', 'Error deleting DTR: ' . $e->getMessage());
        }
    }
}
