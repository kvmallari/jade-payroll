<?php

namespace App\Http\Controllers;

use App\Models\TimeLog;
use App\Models\Employee;
use App\Models\User;
use App\Models\Payroll;
use App\Models\PayrollRateConfiguration;
use App\Models\Holiday;
use Illuminate\Http\Request;
use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\DTRImport;
use Carbon\Carbon;
use Exception;

class TimeLogController extends Controller
{
    use AuthorizesRequests;

    /**
     * Display a listing of DTR batches.
     */
    public function index(Request $request)
    {
        $this->authorize('view time logs');

        // Build query for DTR batches with relationships
        $query = DB::table('d_t_r_s as dtr')
            ->join('employees as e', 'dtr.employee_id', '=', 'e.id')
            ->join('users as u', 'e.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('payrolls as p', 'dtr.payroll_id', '=', 'p.id')
            ->select([
                'dtr.id',
                'dtr.employee_id',
                'dtr.payroll_id',
                'dtr.period_start',
                'dtr.period_end',
                'dtr.total_regular_hours',
                'dtr.total_overtime_hours',
                'dtr.total_late_hours',
                'dtr.regular_days',
                'dtr.status',
                'dtr.created_at',
                'dtr.updated_at',
                'e.first_name',
                'e.last_name',
                'e.employee_number',
                'u.name as user_name',
                'u.email',
                'd.name as department_name',
                'p.payroll_type',
                'p.period_label'
            ])
            ->orderBy('dtr.period_start', 'desc')
            ->orderBy('dtr.created_at', 'desc');

        // Filter by employee if specified
        if ($request->filled('employee_id')) {
            $query->where('dtr.employee_id', $request->employee_id);
        }

        // Filter by date range if specified
        if ($request->filled('start_date')) {
            $query->where('dtr.period_start', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('dtr.period_end', '<=', $request->end_date);
        }

        // Filter by department if specified
        if ($request->filled('department_id')) {
            $query->where('e.department_id', $request->department_id);
        }

        // Paginate results
        $dtrBatches = $query->paginate(20)->withQueryString();

        // Get employees for filter dropdown
        $employees = Employee::with('user')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        // Get departments for filter dropdown
        $departments = DB::table('departments')->orderBy('name')->get();

        // Get statistics
        $totalDTRBatches = DB::table('d_t_r_s')->count();
        $totalEmployeesWithDTR = DB::table('d_t_r_s')->distinct('employee_id')->count();
        $totalRegularHours = DB::table('d_t_r_s')->sum('total_regular_hours');

        return view('time-logs.index', compact('dtrBatches', 'employees', 'departments', 'totalDTRBatches', 'totalEmployeesWithDTR', 'totalRegularHours'));
    }

    /**
     * Show the form for creating a new time log.
     */
    public function create()
    {
        $this->authorize('create time logs');

        $employees = Employee::with('user')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        // Get available log types from rate configurations
        $logTypes = TimeLog::getAvailableLogTypes();

        return view('time-logs.create', compact('employees', 'logTypes'));
    }

    /**
     * Store a newly created time log.
     */
    public function store(Request $request)
    {
        $this->authorize('create time logs');

        // Get available log types for validation
        $availableLogTypes = array_keys(TimeLog::getAvailableLogTypes());

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'log_date' => 'required|date|before_or_equal:today',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_in' => 'nullable|date_format:H:i',
            'break_out' => 'nullable|date_format:H:i|after:break_in',
            'log_type' => 'required|in:' . implode(',', $availableLogTypes),
            'remarks' => 'nullable|string|max:500',
            'is_holiday' => 'boolean',
            'is_rest_day' => 'boolean',
        ]);

        // Check if time log already exists for this employee and date
        $existingLog = TimeLog::where('employee_id', $validated['employee_id'])
            ->where('log_date', $validated['log_date'])
            ->first();

        if ($existingLog) {
            return back()->withErrors(['error' => 'Time log already exists for this employee on this date.'])
                ->withInput();
        }

        // Calculate hours
        $timeIn = Carbon::createFromFormat('H:i', $validated['time_in']);
        $timeOut = $validated['time_out'] ? Carbon::createFromFormat('H:i', $validated['time_out']) : null;

        $breakIn = $validated['break_in'] ? Carbon::createFromFormat('H:i', $validated['break_in']) : null;
        $breakOut = $validated['break_out'] ? Carbon::createFromFormat('H:i', $validated['break_out']) : null;

        $totalHours = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $lateHours = 0;
        $undertimeHours = 0;

        if ($timeOut) {
            // Get employee for schedule information
            $employee = Employee::find($validated['employee_id']);

            // Create a temporary TimeLog object for calculation
            $tempTimeLog = new TimeLog([
                'employee_id' => $validated['employee_id'],
                'log_date' => $validated['log_date'],
                'time_in' => $validated['time_in'],
                'time_out' => $validated['time_out'],
                'break_in' => $validated['break_in'],
                'break_out' => $validated['break_out'],
            ]);
            $tempTimeLog->setRelation('employee', $employee);

            // Calculate using dynamic method
            $calculatedHours = $this->calculateDynamicWorkingHours($tempTimeLog);

            $totalHours = $calculatedHours['total_hours'];
            $regularHours = $calculatedHours['regular_hours'];
            $overtimeHours = $calculatedHours['overtime_hours'];
            $lateHours = $calculatedHours['late_hours'];
            $undertimeHours = $calculatedHours['undertime_hours'];
        }

        $timeLog = TimeLog::create([
            'employee_id' => $validated['employee_id'],
            'log_date' => $validated['log_date'],
            'time_in' => $validated['time_in'],
            'time_out' => $validated['time_out'],
            'break_in' => $validated['break_in'],
            'break_out' => $validated['break_out'],
            'total_hours' => $totalHours,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'late_hours' => $lateHours,
            'undertime_hours' => $undertimeHours,
            'log_type' => $validated['log_type'],
            'creation_method' => 'manual',
            'remarks' => $validated['remarks'],
            'is_holiday' => $validated['is_holiday'] ?? false,
            'is_rest_day' => $validated['is_rest_day'] ?? false,
        ]);

        return redirect()->route('time-logs.show', $timeLog)
            ->with('success', 'Time log created successfully!');
    }

    /**
     * Display the specified DTR batch.
     */
    public function show(TimeLog $timeLog)
    {
        $this->authorize('view time logs');

        $timeLog->load(['employee.user', 'employee.department', 'approver']);

        return view('time-logs.show', compact('timeLog'));
    }

    /**
     * Display detailed DTR records for a specific batch.
     */
    public function showDTRBatch($dtrId)
    {
        $this->authorize('view time logs');

        // Get DTR batch details
        $dtrBatch = DB::table('d_t_r_s as dtr')
            ->join('employees as e', 'dtr.employee_id', '=', 'e.id')
            ->join('users as u', 'e.user_id', '=', 'u.id')
            ->leftJoin('departments as d', 'e.department_id', '=', 'd.id')
            ->leftJoin('payrolls as p', 'dtr.payroll_id', '=', 'p.id')
            ->select([
                'dtr.*',
                'e.first_name',
                'e.last_name',
                'e.employee_number',
                'u.name as user_name',
                'u.email',
                'd.name as department_name',
                'p.payroll_type',
                'p.period_label'
            ])
            ->where('dtr.id', $dtrId)
            ->first();

        if (!$dtrBatch) {
            return redirect()->route('time-logs.index')->with('error', 'DTR batch not found.');
        }

        // Parse DTR data
        $dtrData = json_decode($dtrBatch->dtr_data, true) ?? [];

        // Get employee with day schedule for dynamic rest day determination
        $employee = Employee::with('daySchedule')->find($dtrBatch->employee_id);

        // Get individual time logs for the period (if any exist)
        $timeLogs = TimeLog::where('employee_id', $dtrBatch->employee_id)
            ->whereBetween('log_date', [$dtrBatch->period_start, $dtrBatch->period_end])
            ->orderBy('log_date')
            ->get();

        // Create period dates array for display
        $periodDates = [];
        $current = Carbon::parse($dtrBatch->period_start);
        $end = Carbon::parse($dtrBatch->period_end);

        while ($current->lte($end)) {
            $dateStr = $current->format('Y-m-d');

            // Use employee's day schedule to determine rest day instead of hardcoded weekend
            $isRestDay = $employee && $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($current) : $current->isWeekend();

            $dayData = $dtrData[$dateStr] ?? [
                'date' => $dateStr,
                'day_name' => $current->format('l'),
                'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                'time_in' => null,
                'time_out' => null,
                'break_start' => null,
                'break_end' => null,
                'regular_hours' => 0,
                'overtime_hours' => 0,
                'late_minutes' => 0,
                'status' => 'no_record',
                'remarks' => null
            ];

            $periodDates[] = array_merge($dayData, [
                'carbon' => $current->copy(),
                'formatted' => $current->format('M d')
            ]);

            $current->addDay();
        }

        return view('time-logs.dtr-batch', compact('dtrBatch', 'periodDates', 'timeLogs'));
    }

    /**
     * Show the form for editing the specified time log.
     */
    public function edit(TimeLog $timeLog)
    {
        $this->authorize('edit time logs');

        // Time logs can now always be edited since approval system is removed

        $employees = Employee::with('user')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        // Get available log types from rate configurations
        $logTypes = TimeLog::getAvailableLogTypes();

        return view('time-logs.edit', compact('timeLog', 'employees', 'logTypes'));
    }

    /**
     * Update the specified time log.
     */
    public function update(Request $request, TimeLog $timeLog)
    {
        $this->authorize('edit time logs');

        // Get available log types for validation
        $availableLogTypes = array_keys(TimeLog::getAvailableLogTypes());

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'log_date' => 'required|date|before_or_equal:today',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_in' => 'nullable|date_format:H:i',
            'break_out' => 'nullable|date_format:H:i|after:break_in',
            'log_type' => 'required|in:' . implode(',', $availableLogTypes),
            'remarks' => 'nullable|string|max:500',
            'is_holiday' => 'boolean',
            'is_rest_day' => 'boolean',
        ]);

        // Recalculate hours (same logic as store method)
        $timeIn = Carbon::createFromFormat('H:i', $validated['time_in']);
        $timeOut = $validated['time_out'] ? Carbon::createFromFormat('H:i', $validated['time_out']) : null;

        $breakIn = $validated['break_in'] ? Carbon::createFromFormat('H:i', $validated['break_in']) : null;
        $breakOut = $validated['break_out'] ? Carbon::createFromFormat('H:i', $validated['break_out']) : null;

        $totalHours = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $lateHours = 0;
        $undertimeHours = 0;

        if ($timeOut) {
            // Update the time log with the new data first
            $timeLog->update([
                'employee_id' => $validated['employee_id'],
                'log_date' => $validated['log_date'],
                'time_in' => $validated['time_in'],
                'time_out' => $validated['time_out'],
                'break_in' => $validated['break_in'],
                'break_out' => $validated['break_out'],
                'log_type' => $validated['log_type'],
                'remarks' => $validated['remarks'],
                'is_holiday' => $validated['is_holiday'] ?? false,
                'is_rest_day' => $validated['is_rest_day'] ?? false,
            ]);

            // Get employee for schedule information
            $employee = Employee::find($validated['employee_id']);
            $timeLog->setRelation('employee', $employee);

            // Calculate using dynamic method
            $calculatedHours = $this->calculateDynamicWorkingHours($timeLog);

            $totalHours = $calculatedHours['total_hours'];
            $regularHours = $calculatedHours['regular_hours'];
            $overtimeHours = $calculatedHours['overtime_hours'];
            $lateHours = $calculatedHours['late_hours'];
            $undertimeHours = $calculatedHours['undertime_hours'];
        }

        $timeLog->update([
            'total_hours' => $totalHours,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'late_hours' => $lateHours,
            'undertime_hours' => $undertimeHours,
        ]);

        return redirect()->route('time-logs.show', $timeLog)
            ->with('success', 'Time log updated successfully!');
    }

    /**
     * Remove the specified time log.
     */
    public function destroy(TimeLog $timeLog)
    {
        $this->authorize('delete time logs');

        $timeLog->delete();

        return redirect()->route('time-logs.index')
            ->with('success', 'Time log deleted successfully!');
    }

    /**
     * Remove the specified DTR batch.
     */
    public function destroyDTRBatch($dtrId)
    {
        $this->authorize('delete time logs');

        $dtrBatch = DB::table('d_t_r_s')->where('id', $dtrId)->first();

        if (!$dtrBatch) {
            return redirect()->route('time-logs.index')->with('error', 'DTR batch not found.');
        }

        // Delete related time logs first
        TimeLog::where('employee_id', $dtrBatch->employee_id)
            ->whereBetween('log_date', [$dtrBatch->period_start, $dtrBatch->period_end])
            ->delete();

        // Delete DTR batch
        DB::table('d_t_r_s')->where('id', $dtrId)->delete();

        return redirect()->route('time-logs.index')->with('success', 'DTR batch deleted successfully!');
    }

    /**
     * Show payroll for the DTR batch.
     */
    public function showPayroll($dtrId)
    {
        $this->authorize('view payrolls');

        $dtrBatch = DB::table('d_t_r_s')->where('id', $dtrId)->first();

        if (!$dtrBatch || !$dtrBatch->payroll_id) {
            return redirect()->route('time-logs.index')->with('error', 'Payroll not found for this DTR batch.');
        }

        return redirect()->route('payrolls.show', $dtrBatch->payroll_id);
    }

    /**
     * Show DTR import form.
     */
    public function importForm()
    {
        $this->authorize('import time logs');

        return view('time-logs.import');
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

            $import = new DTRImport($request->boolean('overwrite_existing'));
            Excel::import($import, $request->file('dtr_file'));

            DB::commit();

            $importedCount = $import->getImportedCount();
            $skippedCount = $import->getSkippedCount();
            $errorCount = $import->getErrorCount();

            $message = "DTR import completed! Imported: {$importedCount}, Skipped: {$skippedCount}, Errors: {$errorCount}";

            if ($errorCount > 0) {
                $errors = $import->getErrors();
                return redirect()->route('time-logs.index')
                    ->with('warning', $message)
                    ->with('import_errors', $errors);
            }

            return redirect()->route('time-logs.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('DTR Import Error: ' . $e->getMessage());

            return back()->withErrors(['error' => 'Failed to import DTR: ' . $e->getMessage()])
                ->withInput();
        }
    }

    /**
     * Export DTR template.
     */
    public function exportTemplate()
    {
        $this->authorize('import time logs');

        $headers = [
            'Content-Type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
            'Content-Disposition' => 'attachment; filename="dtr_template.xlsx"',
        ];

        return Excel::download(new \App\Exports\DTRTemplateExport(), 'dtr_template.xlsx', \Maatwebsite\Excel\Excel::XLSX, $headers);
    }

    /**
     * Show employee's own time logs.
     */
    public function myTimeLogs(Request $request)
    {
        $this->authorize('view own time logs');

        $employee = Employee::where('user_id', Auth::id())->first();

        if (!$employee) {
            return redirect()->route('dashboard')
                ->with('error', 'Employee profile not found.');
        }

        $query = TimeLog::where('employee_id', $employee->id);

        // Filter by date range
        if ($request->filled('start_date')) {
            $query->where('log_date', '>=', $request->start_date);
        }
        if ($request->filled('end_date')) {
            $query->where('log_date', '<=', $request->end_date);
        }

        $timeLogs = $query->orderBy('log_date', 'desc')
            ->paginate(20);

        return view('time-logs.my-time-logs', compact('timeLogs', 'employee'));
    }

    /**
     * Store employee's own time log.
     */
    public function storeMyTimeLog(Request $request)
    {
        $this->authorize('create own time logs');

        $employee = Employee::where('user_id', Auth::id())->first();

        if (!$employee) {
            return redirect()->route('dashboard')
                ->with('error', 'Employee profile not found.');
        }

        $validated = $request->validate([
            'log_date' => 'required|date|before_or_equal:today',
            'time_in' => 'required|date_format:H:i',
            'time_out' => 'nullable|date_format:H:i|after:time_in',
            'break_in' => 'nullable|date_format:H:i',
            'break_out' => 'nullable|date_format:H:i|after:break_in',
            'remarks' => 'nullable|string|max:500',
        ]);

        // Check if time log already exists
        $existingLog = TimeLog::where('employee_id', $employee->id)
            ->where('log_date', $validated['log_date'])
            ->first();

        if ($existingLog) {
            return back()->withErrors(['error' => 'Time log already exists for this date.'])
                ->withInput();
        }

        // Calculate hours (same logic as store method)
        $timeIn = Carbon::createFromFormat('H:i', $validated['time_in']);
        $timeOut = $validated['time_out'] ? Carbon::createFromFormat('H:i', $validated['time_out']) : null;

        $breakIn = $validated['break_in'] ? Carbon::createFromFormat('H:i', $validated['break_in']) : null;
        $breakOut = $validated['break_out'] ? Carbon::createFromFormat('H:i', $validated['break_out']) : null;

        $totalHours = 0;
        $regularHours = 0;
        $overtimeHours = 0;
        $lateHours = 0;
        $undertimeHours = 0;

        if ($timeOut) {
            $totalMinutes = $timeIn->diffInMinutes($timeOut);

            if ($breakIn && $breakOut) {
                $breakMinutes = $breakIn->diffInMinutes($breakOut);
                $totalMinutes -= $breakMinutes;
            }

            $totalHours = $totalMinutes / 60;

            $standardHours = 8;
            if ($totalHours <= $standardHours) {
                $regularHours = $totalHours;
            } else {
                $regularHours = $standardHours;
                $overtimeHours = $totalHours - $standardHours;
            }

            $standardTimeIn = Carbon::createFromFormat('H:i', '08:00');
            if ($timeIn->greaterThan($standardTimeIn)) {
                $lateHours = $standardTimeIn->diffInMinutes($timeIn) / 60;
            }

            if ($totalHours < $standardHours) {
                $undertimeHours = $standardHours - $totalHours;
            }
        }

        TimeLog::create([
            'employee_id' => $employee->id,
            'log_date' => $validated['log_date'],
            'time_in' => $validated['time_in'],
            'time_out' => $validated['time_out'],
            'break_in' => $validated['break_in'],
            'break_out' => $validated['break_out'],
            'total_hours' => $totalHours,
            'regular_hours' => $regularHours,
            'overtime_hours' => $overtimeHours,
            'late_hours' => $lateHours,
            'undertime_hours' => $undertimeHours,
            'log_type' => 'regular',
            'creation_method' => 'manual',
            'remarks' => $validated['remarks'],
        ]);

        return redirect()->route('my-time-logs')
            ->with('success', 'Time log submitted successfully!');
    }

    /**
     * Show DTR for a specific employee and period
     */
    public function showDTR(Request $request, Employee $employee)
    {
        $this->authorize('view time logs');

        // Get payroll settings to determine current period
        $payrollSettings = \App\Models\PayrollScheduleSetting::first();

        if (!$payrollSettings) {
            return redirect()->back()->with('error', 'Payroll schedule settings not configured.');
        }

        $currentPeriod = $this->getCurrentPayrollPeriod($payrollSettings);
        $dtrData = $this->generateDTRData($employee, $currentPeriod, $payrollSettings);

        return view('time-logs.show-dtr', compact('employee', 'dtrData', 'currentPeriod', 'payrollSettings'));
    }

    /**
     * Show simple DTR interface with draggable clock.
     */
    public function simpleDTR(Request $request, Employee $employee)
    {
        $this->authorize('view time logs');

        // Get payroll settings to determine current period
        $payrollSettings = \App\Models\PayrollScheduleSetting::first();

        if (!$payrollSettings) {
            return redirect()->back()->with('error', 'Payroll schedule settings not configured.');
        }

        $currentPeriod = $this->getCurrentPayrollPeriod($payrollSettings);
        $dtrData = $this->generateDTRData($employee, $currentPeriod, $payrollSettings);

        return view('time-logs.simple-dtr', compact('employee', 'dtrData', 'currentPeriod', 'payrollSettings'));
    }

    /**
     * Update or create time entry via AJAX
     */
    public function updateTimeEntry(Request $request)
    {
        $this->authorize('create time logs');

        try {
            $validated = $request->validate([
                'employee_id' => 'required|exists:employees,id',
                'log_date' => 'required|date',
                'time_in' => 'nullable|date_format:H:i',
                'time_out' => 'nullable|date_format:H:i',
                'break_in' => 'nullable|date_format:H:i',
                'break_out' => 'nullable|date_format:H:i',
                'remarks' => 'nullable|string|max:500',
                'log_type' => 'required|in:regular,overtime,holiday,rest_day',
                'is_holiday' => 'boolean',
                'is_rest_day' => 'boolean',
            ]);

            // Find existing time log or create new one
            $timeLog = TimeLog::updateOrCreate(
                [
                    'employee_id' => $validated['employee_id'],
                    'log_date' => $validated['log_date'],
                ],
                [
                    'time_in' => $validated['time_in'],
                    'time_out' => $validated['time_out'],
                    'break_in' => $validated['break_in'],
                    'break_out' => $validated['break_out'],
                    'log_type' => $validated['log_type'],
                    'remarks' => $validated['remarks'],
                    'is_holiday' => $validated['is_holiday'] ?? false,
                    'is_rest_day' => $validated['is_rest_day'] ?? false,
                ]
            );

            // Calculate hours
            $this->calculateHours($timeLog);

            return response()->json(['success' => true, 'message' => 'Time entry updated successfully']);
        } catch (\Exception $e) {
            Log::error('Error updating time entry: ' . $e->getMessage());
            return response()->json(['success' => false, 'message' => 'Error updating time entry'], 500);
        }
    }

    /**
     * Get current payroll period
     */
    private function getCurrentPayrollPeriod($payrollSettings)
    {
        $today = Carbon::now();

        if ($payrollSettings->frequency === 'semi_monthly') {
            $day = $today->day;

            if ($day <= 15) {
                // First half of the month
                $startDate = $today->copy()->startOfMonth();
                $endDate = $today->copy()->startOfMonth()->addDays(14);
                $payDate = $today->copy()->startOfMonth()->addDays(19); // 20th
            } else {
                // Second half of the month
                $startDate = $today->copy()->startOfMonth()->addDays(15);
                $endDate = $today->copy()->endOfMonth();
                $payDate = $today->copy()->addMonth()->startOfMonth()->addDays(4); // 5th of next month
            }
        } else {
            // Monthly
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->endOfMonth();
            $payDate = $today->copy()->addMonth()->startOfMonth()->addDays(4); // 5th of next month
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_date' => $payDate,
            'period_label' => $startDate->format('M d') . ' - ' . $endDate->format('M d, Y'),
            'pay_label' => 'Pay Date: ' . $payDate->format('M d, Y'),
        ];
    }

    /**
     * Generate DTR data for the period
     */
    private function generateDTRData(Employee $employee, $currentPeriod, $payrollSettings)
    {
        $startDate = $currentPeriod['start_date'];
        $endDate = $currentPeriod['end_date'];

        // Get all time logs for the period
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('log_date');

        // Get holidays for the period
        $holidays = \App\Models\Holiday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('date');

        // Get suspension days for the period
        $suspensionDays = \App\Models\NoWorkSuspendedSetting::where('status', 'active')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    // Check if the suspension period overlaps with our date range
                    $q->where('date_from', '<=', $endDate->format('Y-m-d'))
                        ->where('date_to', '>=', $startDate->format('Y-m-d'));
                });
            })
            ->get();

        $dtrData = [];

        // Generate data for each day in the period
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $timeLog = $timeLogs->get($dateStr);
            $holiday = $holidays->get($dateStr);

            // Check if this date falls within any suspension periods
            $suspensionInfo = $this->checkSuspensionDay($currentDate, $suspensionDays, $employee);

            // Use employee's day schedule to determine rest day instead of hardcoded weekend
            $isRestDay = $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($currentDate) : $currentDate->isWeekend();

            $dayData = [
                'date' => $currentDate->copy(),
                'day' => $currentDate->format('d'),
                'day_name' => $currentDate->format('l'),
                'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                'is_holiday' => $holiday ? $holiday->name : null,
                'is_suspension' => $suspensionInfo['is_suspension'],
                'suspension_info' => $suspensionInfo['info'],
                'time_log' => $timeLog,
                'time_in' => $timeLog ? $timeLog->time_in : null,
                'time_out' => $timeLog ? $timeLog->time_out : null,
                'break_in' => $timeLog ? $timeLog->break_in : null,
                'break_out' => $timeLog ? $timeLog->break_out : null,
                'log_type' => $timeLog ? $timeLog->log_type : null,
                'remarks' => $timeLog ? $timeLog->remarks : null,
                'regular_hours' => $timeLog ? $timeLog->regular_hours : 0,
                'overtime_hours' => $timeLog ? $timeLog->overtime_hours : 0,
                'late_hours' => $timeLog ? $timeLog->late_hours : 0,
                'total_hours' => $timeLog ? $timeLog->total_hours : 0,
            ];

            $dtrData[] = $dayData;
            $currentDate->addDay();
        }

        return $dtrData;
    }

    /**
     * Check if a specific date is a suspension day for the employee
     */
    private function checkSuspensionDay(\Carbon\Carbon $date, $suspensionDays, Employee $employee)
    {
        foreach ($suspensionDays as $suspension) {
            $suspensionStart = \Carbon\Carbon::parse($suspension->date_from);
            $suspensionEnd = \Carbon\Carbon::parse($suspension->date_to);

            // Check if the date falls within the suspension period
            if ($date->between($suspensionStart, $suspensionEnd)) {
                // Check if this suspension affects this employee
                if ($this->isSuspensionApplicableToEmployee($suspension, $employee)) {
                    // Check if it's a time-specific suspension (partial suspension)
                    $isPartialSuspension = $suspension->type === 'partial_suspension' &&
                        $suspension->time_from &&
                        $suspension->time_to;

                    // Check if this suspension is paid and if this employee is eligible for paid suspension
                    $isPaidSuspension = $suspension->is_paid && $this->isEmployeeEligibleForPaidSuspension($suspension, $employee);

                    return [
                        'is_suspension' => true,
                        'info' => [
                            'id' => $suspension->id,
                            'name' => $suspension->name,
                            'type' => $suspension->type,
                            'reason' => $suspension->reason,
                            'time_from' => $suspension->time_from,
                            'time_to' => $suspension->time_to,
                            'is_partial' => $isPartialSuspension,
                            'is_paid' => $isPaidSuspension,
                            'pay_percentage' => $suspension->pay_percentage,
                            'pay_applicable_to' => $suspension->pay_applicable_to,
                            'detailed_reason' => $suspension->detailed_reason,
                        ]
                    ];
                }
            }
        }

        return [
            'is_suspension' => false,
            'info' => null
        ];
    }

    /**
     * Check if a suspension applies to a specific employee
     */
    private function isSuspensionApplicableToEmployee($suspension, Employee $employee)
    {
        // Suspension days apply to ALL employees when active
        // The pay_applicable_to field only controls who gets auto-fill benefits (handled separately)
        return true;
    }

    /**
     * Check if employee is eligible for paid suspension based on pay_applicable_to setting
     */
    private function isEmployeeEligibleForPaidSuspension($suspension, Employee $employee)
    {
        if (!$suspension->is_paid) {
            return false;
        }

        // If pay_applicable_to is not set, assume it applies to all employees
        if (!$suspension->pay_applicable_to) {
            return true;
        }

        // Check based on pay_applicable_to setting
        switch ($suspension->pay_applicable_to) {
            case 'all':
                return true;
            case 'with_benefits':
                // Check if employee has benefits status
                return $employee->benefits_status === 'with_benefits';
            case 'without_benefits':
                // Check if employee doesn't have benefits
                return $employee->benefits_status === 'without_benefits';
            default:
                return true;
        }
    }

    /**
     * Generate DTR data for a specific date range (used by payroll DTR creation)
     */
    private function generateDTRDataForPeriod(Employee $employee, $startDateStr, $endDateStr)
    {
        $startDate = Carbon::parse($startDateStr);
        $endDate = Carbon::parse($endDateStr);

        Log::info('Generating DTR data for period', [
            'employee_id' => $employee->id,
            'employee_name' => $employee->user->name,
            'start_date' => $startDate->format('Y-m-d'),
            'end_date' => $endDate->format('Y-m-d')
        ]);

        // Get all time logs for the period - ensure we include all required fields
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->orderBy('log_date')
            ->get()
            ->keyBy(function ($timeLog) {
                return Carbon::parse($timeLog->log_date)->format('Y-m-d');
            });

        Log::info('Found existing time logs', [
            'count' => $timeLogs->count(),
            'dates' => $timeLogs->keys()->toArray()
        ]);

        // Get holidays for the period (only active holidays)
        $holidays = \App\Models\Holiday::where('is_active', true)
            ->whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy(function ($holiday) {
                return Carbon::parse($holiday->date)->format('Y-m-d');
            });

        // Get suspension days for the period
        $suspensionDays = \App\Models\NoWorkSuspendedSetting::where('status', 'active')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    // Check if the suspension period overlaps with our date range
                    $q->where('date_from', '<=', $endDate->format('Y-m-d'))
                        ->where('date_to', '>=', $startDate->format('Y-m-d'));
                });
            })
            ->get();

        $dtrData = [];

        // Generate data for each day in the period
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $timeLog = $timeLogs->get($dateStr);
            $holiday = $holidays->get($dateStr);

            // Check if this date falls within any suspension periods
            $suspensionInfo = $this->checkSuspensionDay($currentDate, $suspensionDays, $employee);

            // Use employee's day schedule to determine rest day instead of hardcoded weekend
            $isRestDay = $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($currentDate) : $currentDate->isWeekend();

            // Ensure time values are properly formatted
            $timeIn = null;
            $timeOut = null;
            $breakIn = null;
            $breakOut = null;

            if ($timeLog) {
                // Safely parse time values with error handling
                try {
                    $timeIn = $timeLog->time_in ? Carbon::parse($timeLog->time_in) : null;
                } catch (\Exception $e) {
                    Log::warning('Failed to parse time_in', ['value' => $timeLog->time_in, 'error' => $e->getMessage()]);
                    $timeIn = null;
                }

                try {
                    $timeOut = $timeLog->time_out ? Carbon::parse($timeLog->time_out) : null;
                } catch (\Exception $e) {
                    Log::warning('Failed to parse time_out', ['value' => $timeLog->time_out, 'error' => $e->getMessage()]);
                    $timeOut = null;
                }

                try {
                    $breakIn = $timeLog->break_in ? Carbon::parse($timeLog->break_in) : null;
                } catch (\Exception $e) {
                    Log::warning('Failed to parse break_in', ['value' => $timeLog->break_in, 'error' => $e->getMessage()]);
                    $breakIn = null;
                }

                try {
                    $breakOut = $timeLog->break_out ? Carbon::parse($timeLog->break_out) : null;
                } catch (\Exception $e) {
                    Log::warning('Failed to parse break_out', ['value' => $timeLog->break_out, 'error' => $e->getMessage()]);
                    $breakOut = null;
                }

                Log::debug('Time log found for date', [
                    'date' => $dateStr,
                    'time_in' => $timeIn ? $timeIn->format('H:i') : null,
                    'time_out' => $timeOut ? $timeOut->format('H:i') : null,
                    'break_in' => $breakIn ? $breakIn->format('H:i') : null,
                    'break_out' => $breakOut ? $breakOut->format('H:i') : null,
                ]);
            }

            // FRONTEND DYNAMIC LOGIC: Always override based on current active status
            // This ensures frontend shows current state regardless of existing database records

            $frontendLogType = null;
            $frontendTimeIn = null;
            $frontendTimeOut = null;
            $frontendBreakIn = null;
            $frontendBreakOut = null;

            // PRIORITY 1: Active suspension takes highest priority
            if ($suspensionInfo['is_suspension']) {
                // Use the actual suspension type instead of generic 'suspension'
                $suspensionType = $suspensionInfo['info']['type'] ?? 'full_day_suspension';
                $frontendLogType = $suspensionType;

                // Check if this is a partial suspension first (applies to both paid and unpaid)
                $isPartialSuspension = $suspensionInfo['info']['is_partial'] ?? false;

                if ($isPartialSuspension) {
                    // For ALL partial suspensions (paid/unpaid): preserve existing time logs if available, otherwise leave blank for user input
                    if ($timeLog) {
                        $frontendTimeIn = $timeLog->time_in ? Carbon::parse($timeLog->time_in) : null;
                        $frontendTimeOut = $timeLog->time_out ? Carbon::parse($timeLog->time_out) : null;
                        $frontendBreakIn = $timeLog->break_in ? Carbon::parse($timeLog->break_in) : null;
                        $frontendBreakOut = $timeLog->break_out ? Carbon::parse($timeLog->break_out) : null;
                    } else {
                        // No existing time log - leave times blank for partial suspension user input
                        $frontendTimeIn = null;
                        $frontendTimeOut = null;
                        $frontendBreakIn = null;
                        $frontendBreakOut = null;
                    }
                } else {
                    // Handle FULL DAY suspensions
                    $originalDayType = $isRestDay ? 'rest_day' : 'regular_workday';
                    if ($originalDayType === 'regular_workday' && $suspensionInfo['info']['is_paid']) {
                        // Auto-fill with employee's regular schedule for eligible employees only
                        if ($employee->timeSchedule) {
                            $frontendTimeIn = Carbon::parse($employee->timeSchedule->time_in);
                            $frontendTimeOut = Carbon::parse($employee->timeSchedule->time_out);

                            // Set break times if employee has fixed breaks
                            if ($employee->timeSchedule->break_start && $employee->timeSchedule->break_end) {
                                $frontendBreakIn = Carbon::parse($employee->timeSchedule->break_start);
                                $frontendBreakOut = Carbon::parse($employee->timeSchedule->break_end);
                            }
                        } else {
                            // Fallback schedule for full day suspension
                            $frontendTimeIn = Carbon::parse('08:00');
                            $frontendTimeOut = Carbon::parse('17:00');
                        }
                    }
                    // For unpaid full day suspensions or non-eligible employees, don't auto-fill times
                }
            }
            // PRIORITY 2: Active holiday (if no suspension)
            elseif ($holiday && $holiday->is_active) {
                // Determine holiday log type based on rest day combination
                if (!$isRestDay && $holiday->type === 'regular') {
                    $frontendLogType = 'regular_holiday';
                } elseif (!$isRestDay && $holiday->type === 'special_non_working') {
                    $frontendLogType = 'special_holiday';
                } elseif ($isRestDay && $holiday->type === 'regular') {
                    $frontendLogType = 'rest_day_regular_holiday';
                } elseif ($isRestDay && $holiday->type === 'special_non_working') {
                    $frontendLogType = 'rest_day_special_holiday';
                }

                // For holidays, check if paid or not paid to determine time field handling
                $isPaidHoliday = $holiday->is_paid ?? true;

                if ($isPaidHoliday) {
                    // Paid holidays: auto-fill with existing time logs or preserve existing data
                    if ($timeLog) {
                        $frontendTimeIn = $timeLog->time_in ? Carbon::parse($timeLog->time_in) : null;
                        $frontendTimeOut = $timeLog->time_out ? Carbon::parse($timeLog->time_out) : null;
                        $frontendBreakIn = $timeLog->break_in ? Carbon::parse($timeLog->break_in) : null;
                        $frontendBreakOut = $timeLog->break_out ? Carbon::parse($timeLog->break_out) : null;
                    } else {
                        // CHANGED: Don't auto-fill holidays with employee schedule (like partial suspension behavior)
                        // Users should manually enter time if they worked on holiday
                        $frontendTimeIn = null;
                        $frontendTimeOut = null;
                        $frontendBreakIn = null;
                        $frontendBreakOut = null;
                    }
                } else {
                    // Unpaid holidays: preserve existing time logs if available, otherwise leave blank for manual input
                    if ($timeLog) {
                        $frontendTimeIn = $timeLog->time_in ? Carbon::parse($timeLog->time_in) : null;
                        $frontendTimeOut = $timeLog->time_out ? Carbon::parse($timeLog->time_out) : null;
                        $frontendBreakIn = $timeLog->break_in ? Carbon::parse($timeLog->break_in) : null;
                        $frontendBreakOut = $timeLog->break_out ? Carbon::parse($timeLog->break_out) : null;
                    } else {
                        // No existing time log - leave times blank for unpaid holidays (manual input)
                        $frontendTimeIn = null;
                        $frontendTimeOut = null;
                        $frontendBreakIn = null;
                        $frontendBreakOut = null;
                    }
                }
            }
            // PRIORITY 3: No active holiday/suspension - preserve existing data
            else {
                // If no active holiday/suspension, preserve existing data from database
                // This restores normal behavior when no overrides are active
                if ($timeLog) {
                    // Preserve existing log type from database (could be special_holiday, etc.)
                    $frontendLogType = $timeLog->log_type;
                    $frontendTimeIn = $timeLog->time_in ? Carbon::parse($timeLog->time_in) : null;
                    $frontendTimeOut = $timeLog->time_out ? Carbon::parse($timeLog->time_out) : null;
                    $frontendBreakIn = $timeLog->break_in ? Carbon::parse($timeLog->break_in) : null;
                    $frontendBreakOut = $timeLog->break_out ? Carbon::parse($timeLog->break_out) : null;
                } else {
                    // If no existing time log, use original day type for blank form
                    $frontendLogType = $isRestDay ? 'rest_day' : 'regular_workday';
                    // Times remain null (blank form)
                }
            }

            // The frontend will always use these values regardless of existing database records
            $defaultLogType = $frontendLogType;
            $defaultTimeIn = $frontendTimeIn;
            $defaultTimeOut = $frontendTimeOut;
            $defaultBreakIn = $frontendBreakIn;
            $defaultBreakOut = $frontendBreakOut;
            $dayData = [
                'date' => $currentDate->copy(),
                'day' => $currentDate->format('d'),
                'day_name' => $currentDate->format('l'),
                'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                'is_holiday' => $holiday ? $holiday->name : null,
                'holiday_type' => $holiday ? $holiday->type : null, // Add holiday type for auto day type selection
                'is_holiday_active' => $holiday && $holiday->is_active, // Add flag for active holidays
                'holiday_is_paid' => $holiday ? $holiday->is_paid : null, // Add holiday pay flag
                'holiday_pay_applicable_to' => $holiday ? $holiday->pay_applicable_to : null, // Add holiday pay applicability
                'holiday_info' => $holiday ? [
                    'name' => $holiday->name,
                    'type' => $holiday->type,
                    'is_paid' => $holiday->is_paid,
                    'pay_applicable_to' => $holiday->pay_applicable_to,
                    'pay_rule' => $holiday->pay_rule,
                    'rate_multiplier' => $holiday->rate_multiplier,
                    'is_active' => $holiday->is_active
                ] : null,
                'is_suspension' => $suspensionInfo['is_suspension'],
                'suspension_info' => $suspensionInfo['info'],
                'time_log' => $timeLog,
                // DYNAMIC FRONTEND BEHAVIOR: Always use frontend-determined values regardless of existing records
                'time_in' => $defaultTimeIn,
                'time_out' => $defaultTimeOut,
                'break_in' => $defaultBreakIn,
                'break_out' => $defaultBreakOut,
                'log_type' => $defaultLogType,
                'remarks' => $timeLog ? $timeLog->remarks : null,
                'regular_hours' => $timeLog ? ($timeLog->regular_hours ?? 0) : 0,
                'overtime_hours' => $timeLog ? ($timeLog->overtime_hours ?? 0) : 0,
                'late_hours' => $timeLog ? ($timeLog->late_hours ?? 0) : 0,
                'total_hours' => $timeLog ? ($timeLog->total_hours ?? 0) : 0,
            ];

            $dtrData[] = $dayData;
            $currentDate->addDay();
        }

        Log::info('Generated DTR data', [
            'total_days' => count($dtrData),
            'days_with_time_logs' => count(array_filter($dtrData, function ($day) {
                return $day['time_log'] !== null;
            }))
        ]);

        return $dtrData;
    }

    /**
     * Calculate hours for a time log entry
     */
    private function calculateHours(TimeLog $timeLog)
    {
        if (!$timeLog->time_in || !$timeLog->time_out) {
            $timeLog->update([
                'regular_hours' => 0,
                'overtime_hours' => 0,
                'late_hours' => 0,
                'total_hours' => 0,
            ]);
            return;
        }

        $timeIn = Carbon::parse($timeLog->log_date . ' ' . $timeLog->time_in);
        $timeOut = Carbon::parse($timeLog->log_date . ' ' . $timeLog->time_out);

        // Handle next day time out
        if ($timeOut->lt($timeIn)) {
            $timeOut->addDay();
        }

        $totalMinutes = $timeOut->diffInMinutes($timeIn);

        // Subtract break time if both break_in and break_out are provided
        if ($timeLog->break_in && $timeLog->break_out) {
            $breakIn = Carbon::parse($timeLog->log_date . ' ' . $timeLog->break_in);
            $breakOut = Carbon::parse($timeLog->log_date . ' ' . $timeLog->break_out);

            if ($breakOut->gt($breakIn)) {
                $breakMinutes = $breakOut->diffInMinutes($breakIn);
                $totalMinutes -= $breakMinutes;
            }
        }

        $totalHours = $totalMinutes / 60;

        // Get employee's time schedule
        $employee = $timeLog->employee;
        $timeSchedule = $employee->timeSchedule ?? null;

        // Default to 8-5 schedule if no time schedule is set
        $scheduledStartTime = $timeSchedule ? $timeSchedule->start_time : '08:00';
        $scheduledEndTime = $timeSchedule ? $timeSchedule->end_time : '17:00';

        // Calculate scheduled work hours
        $schedStart = Carbon::parse($timeLog->log_date . ' ' . $scheduledStartTime);
        $schedEnd = Carbon::parse($timeLog->log_date . ' ' . $scheduledEndTime);

        // Handle next day scheduled end time
        if ($schedEnd->lt($schedStart)) {
            $schedEnd->addDay();
        }

        $scheduledWorkMinutes = $schedEnd->diffInMinutes($schedStart);
        $standardHours = $scheduledWorkMinutes / 60;

        // Calculate late hours based on employee's scheduled start time
        $lateMinutes = max(0, $timeIn->diffInMinutes($schedStart));
        $lateHours = $lateMinutes / 60;

        // Calculate regular and overtime hours properly
        // Regular hours should not exceed the scheduled work hours
        $regularHours = min($totalHours, $standardHours);

        // OT should only be calculated when actual end time exceeds scheduled end time
        $actualEndForOT = Carbon::parse($timeLog->log_date . ' ' . $timeLog->time_out);
        if ($actualEndForOT->lt($timeIn)) {
            $actualEndForOT->addDay();
        }

        // Calculate OT: only hours worked beyond scheduled end time
        $overtimeHours = 0;
        if ($actualEndForOT->gt($schedEnd)) {
            $overtimeMinutes = $actualEndForOT->diffInMinutes($schedEnd);
            // Subtract break time from OT if break extends into OT period
            if ($timeLog->break_in && $timeLog->break_out) {
                $breakStart = Carbon::parse($timeLog->log_date . ' ' . $timeLog->break_in);
                $breakEnd = Carbon::parse($timeLog->log_date . ' ' . $timeLog->break_out);

                // If break overlaps with OT period, subtract the overlap
                if ($breakStart->lt($actualEndForOT) && $breakEnd->gt($schedEnd)) {
                    $overlapStart = max($breakStart->timestamp, $schedEnd->timestamp);
                    $overlapEnd = min($breakEnd->timestamp, $actualEndForOT->timestamp);
                    $overlapMinutes = max(0, ($overlapEnd - $overlapStart) / 60);
                    $overtimeMinutes -= $overlapMinutes;
                }
            }
            $overtimeHours = max(0, $overtimeMinutes / 60);
        }

        $timeLog->update([
            'regular_hours' => round($regularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'late_hours' => round($lateHours, 2),
            'total_hours' => round($totalHours, 2),
        ]);
    }

    /**
     * Show bulk time log creation form for an employee's payroll period.
     */
    public function createBulk(Request $request)
    {
        $this->authorize('create time logs');

        $employees = Employee::with('user')
            ->where('employment_status', 'active')
            ->orderBy('first_name')
            ->get();

        $selectedEmployee = null;
        $dtrData = [];
        $currentPeriod = null;

        if ($request->filled('employee_id')) {
            Log::info('Bulk creation: Employee ID selected', ['employee_id' => $request->employee_id]);

            $selectedEmployee = Employee::with('user')->findOrFail($request->employee_id);
            Log::info('Bulk creation: Employee found', ['employee' => $selectedEmployee->first_name . ' ' . $selectedEmployee->last_name]);

            // Get payroll settings to determine current period
            $payrollSettings = \App\Models\PayrollScheduleSetting::first();
            Log::info('Bulk creation: PayrollSettings check', ['has_settings' => $payrollSettings ? 'yes' : 'no']);

            if ($payrollSettings) {
                $currentPeriod = $this->getCurrentPayrollPeriod($payrollSettings);
                $dtrData = $this->generateDTRData($selectedEmployee, $currentPeriod, $payrollSettings);
            } else {
                // Fallback: Use current semi-monthly period if no settings exist
                Log::info('Bulk creation: Using fallback period generation');
                $currentPeriod = $this->getDefaultPayrollPeriod();
                $dtrData = $this->generateDTRDataWithoutSettings($selectedEmployee, $currentPeriod);
            }

            Log::info('Bulk creation: Generated data', [
                'period' => $currentPeriod['period_label'] ?? 'none',
                'dtr_data_count' => count($dtrData)
            ]);
        }

        // Get available log types from rate configurations
        $logTypes = TimeLog::getAvailableLogTypes();

        return view('time-logs.create-bulk', compact('employees', 'selectedEmployee', 'dtrData', 'currentPeriod', 'logTypes'));
    }

    /**
     * Show bulk time log creation form for a specific employee (from payroll context)
     */
    public function createBulkForEmployee(Request $request, $employee_id)
    {
        $this->authorize('create time logs');

        $selectedEmployee = Employee::with(['user', 'daySchedule', 'timeSchedule'])->findOrFail($employee_id);

        // Get period data from request (passed from payroll)
        $periodStart = $request->input('period_start');
        $periodEnd = $request->input('period_end');
        $payrollId = $request->input('payroll_id');
        $schedule = $request->input('schedule'); // Add schedule parameter

        // Generate DTR data for the specific period
        $currentPeriod = [
            'start' => $periodStart,
            'end' => $periodEnd,
            'period_label' => date('M d', strtotime($periodStart)) . ' - ' . date('M d, Y', strtotime($periodEnd))
        ];

        $dtrData = $this->generateDTRDataForPeriod($selectedEmployee, $periodStart, $periodEnd);

        // Get available log types from rate configurations
        $logTypes = TimeLog::getAvailableLogTypes();

        // Get active holidays for the period to help with smart defaults
        $holidays = \App\Models\Holiday::where('is_active', true)
            ->whereBetween('date', [$periodStart, $periodEnd])
            ->get()
            ->keyBy('date');

        return view('time-logs.create-bulk-employee', compact(
            'selectedEmployee',
            'dtrData',
            'currentPeriod',
            'periodStart',
            'periodEnd',
            'payrollId',
            'schedule',
            'logTypes',
            'holidays'
        ));
    }

    /**
     * Store bulk time logs for an employee's payroll period.
     */
    public function storeBulk(Request $request)
    {
        Log::info('Bulk time log storage started', [
            'employee_id' => $request->employee_id,
            'time_logs_count' => $request->has('time_logs') ? count($request->time_logs) : 0
        ]);

        $this->authorize('create time logs');

        // Get available log types for validation
        $availableLogTypes = PayrollRateConfiguration::where('is_active', true)
            ->pluck('type_name')
            ->toArray();

        // Debug: Log available types and request data
        Log::info('Bulk validation debug', [
            'available_log_types' => $availableLogTypes,
            'request_time_logs' => $request->input('time_logs')
        ]);

        $validated = $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'time_logs' => 'required|array',
            'time_logs.*.log_date' => 'required|date',
            'time_logs.*.time_in' => 'nullable|date_format:H:i',
            'time_logs.*.time_out' => 'nullable|date_format:H:i',
            'time_logs.*.break_in' => 'nullable|date_format:H:i',
            'time_logs.*.break_out' => 'nullable|date_format:H:i',
            // Hidden fields for suspension days when inputs are disabled
            'time_logs.*.time_in_hidden' => 'nullable|date_format:H:i',
            'time_logs.*.time_out_hidden' => 'nullable|date_format:H:i',
            'time_logs.*.break_in_hidden' => 'nullable|date_format:H:i',
            'time_logs.*.break_out_hidden' => 'nullable|date_format:H:i',
            'time_logs.*.used_break' => 'nullable|boolean',  // Add flexible break checkbox
            'time_logs.*.log_type' => 'required|in:' . implode(',', $availableLogTypes),
            'time_logs.*.is_holiday' => 'boolean',
            'time_logs.*.is_rest_day' => 'boolean',
        ]);

        Log::info('Bulk time log validation passed', [
            'validated_time_logs_count' => count($validated['time_logs'])
        ]);

        try {
            DB::beginTransaction();

            $createdCount = 0;
            $updatedCount = 0;
            $skippedCount = 0;

            foreach ($validated['time_logs'] as $logData) {
                // Find existing time log for this date
                $existingLog = TimeLog::where('employee_id', $validated['employee_id'])
                    ->where('log_date', $logData['log_date'])
                    ->first();

                // Check if we should process this entry (include hidden fields)
                $hasAnyTimeData = !empty($logData['time_in']) || !empty($logData['time_out']) ||
                    !empty($logData['break_in']) || !empty($logData['break_out']) ||
                    !empty($logData['time_in_hidden']) || !empty($logData['time_out_hidden']) ||
                    !empty($logData['break_in_hidden']) || !empty($logData['break_out_hidden']);

                // Check if this is a day type change or special day (different from default for the day)
                $date = Carbon::parse($logData['log_date']);
                $employee = Employee::with('daySchedule')->findOrFail($validated['employee_id']);
                $isRestDay = $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($date) : $date->isWeekend();
                $isHoliday = $logData['is_holiday'] ?? false;
                $isActiveHoliday = $logData['is_holiday_active'] ?? false;
                $isSuspension = $logData['is_suspension'] ?? false;

                // Determine what the default log type should be for a regular day (no special circumstances)
                $basicDefaultLogType = $isRestDay ? 'rest_day' : 'regular_workday';

                // For special days (holidays, suspensions), we should always save the record
                $isSpecialDay = $isActiveHoliday || $isSuspension || ($logData['log_type'] !== $basicDefaultLogType);

                // Skip only if no existing record, no time data, AND no special day circumstances
                if (!$existingLog && !$hasAnyTimeData && !$isSpecialDay) {
                    $skippedCount++;
                    continue;
                }

                // Prepare time values (allow null for clearing existing data)
                // Check for hidden field values first (for suspension days with disabled inputs)
                $timeIn = !empty($logData['time_in']) ? $logData['time_in'] : (!empty($logData['time_in_hidden']) ? $logData['time_in_hidden'] : null);
                $timeOut = !empty($logData['time_out']) ? $logData['time_out'] : (!empty($logData['time_out_hidden']) ? $logData['time_out_hidden'] : null);
                $breakIn = !empty($logData['break_in']) ? $logData['break_in'] : (!empty($logData['break_in_hidden']) ? $logData['break_in_hidden'] : null);
                $breakOut = !empty($logData['break_out']) ? $logData['break_out'] : (!empty($logData['break_out_hidden']) ? $logData['break_out_hidden'] : null);

                // FORCE OVERRIDE: Auto-detect and force log_type for active suspensions and holidays
                $forcedLogType = null;

                // Priority 1: Check for active suspension (highest priority)
                if ($isSuspension) {
                    $forcedLogType = 'suspension';
                }
                // Priority 2: Check for active holiday (if no suspension)
                elseif ($isActiveHoliday) {
                    // Get the holiday to determine correct log type
                    $holiday = \App\Models\Holiday::where('date', $logData['log_date'])
                        ->where('is_active', true)
                        ->first();

                    if ($holiday) {
                        if (!$isRestDay && $holiday->type === 'regular') {
                            $forcedLogType = 'regular_holiday';
                        } elseif (!$isRestDay && $holiday->type === 'special_non_working') {
                            $forcedLogType = 'special_holiday';
                        } elseif ($isRestDay && $holiday->type === 'regular') {
                            $forcedLogType = 'rest_day_regular_holiday';
                        } elseif ($isRestDay && $holiday->type === 'special_non_working') {
                            $forcedLogType = 'rest_day_special_holiday';
                        }
                    }
                }

                // Use forced log_type if determined, otherwise use form data
                $logType = $forcedLogType ?? $logData['log_type'];

                // If all time fields are blank, we can still allow day type changes
                // The log_type has already been determined above (including forced overrides)
                if (!$timeIn && !$timeOut && !$breakIn && !$breakOut) {
                    // Keep the determined log_type (already set above)
                }

                // Calculate hours only if we have time_in and time_out
                $totalHours = 0;
                $regularHours = 0;
                $overtimeHours = 0;
                $lateHours = 0;
                $undertimeHours = 0;

                if ($timeIn && $timeOut) {
                    $employee = Employee::with('timeSchedule')->findOrFail($validated['employee_id']);
                    $timeInCarbon = Carbon::createFromFormat('H:i', $timeIn);
                    $timeOutCarbon = Carbon::createFromFormat('H:i', $timeOut);

                    $totalMinutes = $timeInCarbon->diffInMinutes($timeOutCarbon);

                    // Handle break time deduction
                    $breakMinutes = 0;
                    if ($breakIn && $breakOut) {
                        // Fixed break: calculate actual break time
                        $breakInCarbon = Carbon::createFromFormat('H:i', $breakIn);
                        $breakOutCarbon = Carbon::createFromFormat('H:i', $breakOut);
                        $breakMinutes = $breakInCarbon->diffInMinutes($breakOutCarbon);
                    } elseif (
                        $employee->timeSchedule &&
                        $employee->timeSchedule->break_duration_minutes &&
                        !$employee->timeSchedule->break_start &&
                        !$employee->timeSchedule->break_end
                    ) {
                        // Flexible break: check if break was used
                        $usedBreak = isset($logData['used_break']) ? (bool)$logData['used_break'] : true;
                        if ($usedBreak) {
                            $breakMinutes = $employee->timeSchedule->break_duration_minutes;
                        }
                    }

                    $totalMinutes -= $breakMinutes;
                    $totalHours = max(0, $totalMinutes) / 60; // Ensure non-negative

                    $standardHours = 8;
                    if ($totalHours <= $standardHours) {
                        $regularHours = $totalHours;
                    } else {
                        $regularHours = $standardHours;
                        $overtimeHours = $totalHours - $standardHours;
                    }

                    $standardTimeIn = Carbon::createFromFormat('H:i', '08:00');
                    if ($timeInCarbon->greaterThan($standardTimeIn)) {
                        $lateHours = $standardTimeIn->diffInMinutes($timeInCarbon) / 60;
                    }

                    if ($totalHours < $standardHours) {
                        $undertimeHours = $standardHours - $totalHours;
                    }
                }

                $timeLogData = [
                    'employee_id' => $validated['employee_id'],
                    'log_date' => $logData['log_date'],
                    'time_in' => $timeIn,
                    'time_out' => $timeOut,
                    'break_in' => $breakIn,
                    'break_out' => $breakOut,
                    'used_break' => isset($logData['used_break']) ? (bool)$logData['used_break'] : null,
                    'total_hours' => $totalHours,
                    'regular_hours' => $regularHours,
                    'overtime_hours' => $overtimeHours,
                    'late_hours' => $lateHours,
                    'undertime_hours' => $undertimeHours,
                    'log_type' => $logType, // Use the determined log_type (default or original)
                    'creation_method' => 'manual',
                    'is_holiday' => $logData['is_holiday'] ?? false,
                    'is_rest_day' => $logData['is_rest_day'] ?? false,
                ];

                if ($existingLog) {
                    $existingLog->update($timeLogData);
                    $updatedCount++;
                } else {
                    TimeLog::create($timeLogData);
                    $createdCount++;
                }
            }

            DB::commit();

            $message = "Bulk time logs processed! Created: {$createdCount}, Updated: {$updatedCount}, Skipped: {$skippedCount}";

            // Check if we should redirect back to payroll
            if ($request->filled('redirect_to_payroll') && $request->filled('payroll_id')) {
                // Get date range from time logs to refresh payroll calculations
                $dates = collect($validated['time_logs'])->pluck('log_date');
                $startDate = $dates->min();
                $endDate = $dates->max();

                // Refresh payroll calculations by recalculating the specific employee's payroll
                $this->refreshPayrollCalculations($validated['employee_id'], $startDate, $endDate);

                // Check if we have schedule parameter for automation routes
                if ($request->filled('schedule')) {
                    $redirectParams = [
                        'schedule' => $request->schedule,
                        'id' => $validated['employee_id']
                    ];

                    // Preserve from_last_payroll parameter if present
                    if ($request->filled('from_last_payroll')) {
                        $redirectParams['from_last_payroll'] = 'true';
                    }

                    return redirect()->route('payrolls.automation.show', $redirectParams)
                        ->with('success', $message . ' Payroll calculations updated.');
                } else {
                    // Fallback to traditional payroll route
                    return redirect()->route('payrolls.show', $request->payroll_id)
                        ->with('success', $message . ' Payroll calculations updated.');
                }
            }

            return redirect()->route('time-logs.index')
                ->with('success', $message);
        } catch (\Exception $e) {
            DB::rollBack();
            Log::error('Bulk Time Log Creation Error: ' . $e->getMessage(), [
                'exception_type' => get_class($e),
                'file' => $e->getFile(),
                'line' => $e->getLine(),
                'trace' => $e->getTraceAsString(),
                'employee_id' => $validated['employee_id'] ?? 'unknown',
                'request_data' => $request->all()
            ]);

            // More specific error message based on exception type
            $errorMessage = 'Failed to create bulk time logs: ';
            if (strpos($e->getMessage(), 'Not enough data available to satisfy format') !== false) {
                $errorMessage .= 'Invalid time format detected. Please ensure all time fields use the correct HH:MM format (e.g., 08:00, 17:30).';
            } else {
                $errorMessage .= $e->getMessage();
            }

            return back()->withErrors(['error' => $errorMessage])
                ->withInput();
        }
    }

    /**
     * Get default payroll period when no settings are configured (fallback)
     */
    private function getDefaultPayrollPeriod()
    {
        $today = Carbon::now();

        // Default to semi-monthly: 1st-15th or 16th-end of month
        $day = $today->day;

        if ($day <= 15) {
            // First half of the month
            $startDate = $today->copy()->startOfMonth();
            $endDate = $today->copy()->startOfMonth()->addDays(14);
            $payDate = $today->copy()->startOfMonth()->addDays(19); // 20th
        } else {
            // Second half of the month
            $startDate = $today->copy()->startOfMonth()->addDays(15);
            $endDate = $today->copy()->endOfMonth();
            $payDate = $today->copy()->addMonth()->startOfMonth()->addDays(4); // 5th of next month
        }

        return [
            'start_date' => $startDate,
            'end_date' => $endDate,
            'pay_date' => $payDate,
            'period_label' => $startDate->format('M d') . ' - ' . $endDate->format('M d, Y'),
            'pay_label' => 'Pay Date: ' . $payDate->format('M d, Y'),
        ];
    }

    /**
     * Generate DTR data without payroll settings (fallback)
     */
    private function generateDTRDataWithoutSettings(Employee $employee, $currentPeriod)
    {
        $startDate = $currentPeriod['start_date'];
        $endDate = $currentPeriod['end_date'];

        // Get all time logs for the period
        $timeLogs = TimeLog::where('employee_id', $employee->id)
            ->whereBetween('log_date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
            ->get()
            ->keyBy('log_date');

        // Get holidays for the period (if Holiday model exists)
        $holidays = collect();
        if (class_exists(\App\Models\Holiday::class)) {
            $holidays = \App\Models\Holiday::whereBetween('date', [$startDate->format('Y-m-d'), $endDate->format('Y-m-d')])
                ->get()
                ->keyBy('date');
        }

        // Get suspension days for the period
        $suspensionDays = \App\Models\NoWorkSuspendedSetting::where('status', 'active')
            ->where(function ($query) use ($startDate, $endDate) {
                $query->where(function ($q) use ($startDate, $endDate) {
                    // Check if the suspension period overlaps with our date range
                    $q->where('date_from', '<=', $endDate->format('Y-m-d'))
                        ->where('date_to', '>=', $startDate->format('Y-m-d'));
                });
            })
            ->get();

        $dtrData = [];

        // Generate data for each day in the period
        $currentDate = $startDate->copy();
        while ($currentDate->lte($endDate)) {
            $dateStr = $currentDate->format('Y-m-d');
            $timeLog = $timeLogs->get($dateStr);
            $holiday = $holidays->get($dateStr);

            // Check if this date falls within any suspension periods
            $suspensionInfo = $this->checkSuspensionDay($currentDate, $suspensionDays, $employee);

            // Use employee's day schedule to determine rest day instead of hardcoded weekend
            $isRestDay = $employee->daySchedule ? !$employee->daySchedule->isWorkingDay($currentDate) : $currentDate->isWeekend();

            $dayData = [
                'date' => $currentDate->copy(),
                'day' => $currentDate->format('d'),
                'day_name' => $currentDate->format('l'),
                'is_weekend' => $isRestDay, // Keep field name for compatibility but use dynamic logic
                'is_holiday' => $holiday ? $holiday->name : null,
                'is_suspension' => $suspensionInfo['is_suspension'],
                'suspension_info' => $suspensionInfo['info'],
                'time_log' => $timeLog,
                'time_in' => $timeLog ? $timeLog->time_in : null,
                'time_out' => $timeLog ? $timeLog->time_out : null,
                'break_in' => $timeLog ? $timeLog->break_in : null,
                'break_out' => $timeLog ? $timeLog->break_out : null,
                'log_type' => $timeLog ? $timeLog->log_type : null,
                'remarks' => $timeLog ? $timeLog->remarks : null,
                'regular_hours' => $timeLog ? $timeLog->regular_hours : 0,
                'overtime_hours' => $timeLog ? $timeLog->overtime_hours : 0,
                'late_hours' => $timeLog ? $timeLog->late_hours : 0,
                'total_hours' => $timeLog ? $timeLog->total_hours : 0,
            ];

            $dtrData[] = $dayData;
            $currentDate->addDay();
        }

        return $dtrData;
    }

    /**
     * Refresh payroll calculations after DTR changes
     */
    private function refreshPayrollCalculations($employeeId, $startDate, $endDate)
    {
        try {
            // Get employee
            $employee = Employee::findOrFail($employeeId);

            // Update payroll totals for this employee and period
            $this->updatePayrollTotals($employeeId, $startDate, $endDate);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to refresh payroll calculations: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Update payroll totals after DTR changes
     */
    private function updatePayrollTotals($employeeId, $startDate, $endDate)
    {
        try {
            // Find the payroll record for this employee and period
            $payroll = Payroll::where('employee_id', $employeeId)
                ->whereDate('period_start', $startDate)
                ->whereDate('period_end', $endDate)
                ->first();

            if ($payroll) {
                // Get time logs for the period
                $timeLogs = TimeLog::where('employee_id', $employeeId)
                    ->whereBetween('log_date', [$startDate, $endDate])
                    ->get();

                // Calculate totals
                $totalHours = $timeLogs->sum('total_hours');
                $totalRegularHours = $timeLogs->sum('regular_hours');
                $totalOvertimeHours = $timeLogs->sum('overtime_hours');
                $totalLateHours = $timeLogs->sum('late_hours');

                // Update payroll with new totals
                $payroll->update([
                    'total_hours' => $totalHours,
                    'regular_hours' => $totalRegularHours,
                    'overtime_hours' => $totalOvertimeHours,
                    'late_hours' => $totalLateHours,
                    'updated_at' => now()
                ]);

                // Recalculate pay based on new hours
                $this->recalculatePayrollAmounts($payroll);
            }

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to update payroll totals: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Recalculate payroll amounts based on updated hours
     */
    private function recalculatePayrollAmounts($payroll)
    {
        try {
            $employee = $payroll->employee;

            // Get hourly rate
            $hourlyRate = $employee->hourly_rate ?? 0;
            $overtimeRate = $hourlyRate * 1.25; // 25% overtime

            // Calculate basic pay
            $basicPay = $payroll->regular_hours * $hourlyRate;
            $overtimePay = $payroll->overtime_hours * $overtimeRate;

            // Calculate gross pay
            $grossPay = $basicPay + $overtimePay + ($payroll->allowances ?? 0);

            // Calculate net pay (gross - deductions)
            $netPay = $grossPay - ($payroll->deductions ?? 0);

            // Update payroll amounts
            $payroll->update([
                'basic_pay' => $basicPay,
                'overtime_pay' => $overtimePay,
                'gross_pay' => $grossPay,
                'net_pay' => $netPay
            ]);

            return true;
        } catch (\Exception $e) {
            Log::error('Failed to recalculate payroll amounts: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Process HTML time input values (from type="time" inputs)
     * HTML time inputs send values in "HH:MM" format
     */
    private function processHtmlTimeInput($timeValue)
    {
        if (empty($timeValue) || trim($timeValue) === '') {
            return null;
        }

        // Clean the input
        $timeValue = trim($timeValue);

        // HTML time inputs send "HH:MM" format (e.g., "14:30")
        // Validate it's in the expected format
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9])$/', $timeValue)) {
            return $timeValue; // Already in correct format for database
        }

        // Handle edge case where seconds might be included "HH:MM:SS"
        if (preg_match('/^([01][0-9]|2[0-3]):([0-5][0-9]):([0-5][0-9])$/', $timeValue)) {
            return substr($timeValue, 0, 5); // Return just "HH:MM" part
        }

        // If it doesn't match, return null (invalid time)
        Log::warning("Invalid time format received: '{$timeValue}'");
        return null;
    }

    /**
     * Parse time string in various formats to Carbon instance
     */
    private function parseTimeString($timeString)
    {
        if (empty($timeString)) {
            return null;
        }

        // Clean the string
        $timeString = trim($timeString);

        // Try different time formats
        $formats = ['H:i', 'H:i:s', 'g:i A', 'g:i a', 'G:i'];

        foreach ($formats as $format) {
            try {
                return Carbon::createFromFormat($format, $timeString);
            } catch (\Exception $e) {
                // Continue to next format
                continue;
            }
        }

        // If all formats fail, throw an exception with details
        throw new \Exception("Unable to parse time string: '{$timeString}'");
    }

    /**
     * Recalculate all time logs for a specific employee or date range
     */
    public function recalculateTimeLogsForEmployee(Request $request)
    {
        $this->authorize('edit time logs');

        $request->validate([
            'employee_id' => 'required|exists:employees,id',
            'start_date' => 'required|date',
            'end_date' => 'required|date|after_or_equal:start_date',
        ]);

        $timeLogs = TimeLog::where('employee_id', $request->employee_id)
            ->whereBetween('log_date', [$request->start_date, $request->end_date])
            ->whereNotNull('time_in')
            ->whereNotNull('time_out')
            ->get();

        $recalculatedCount = 0;

        foreach ($timeLogs as $timeLog) {
            $calculatedHours = $this->calculateDynamicWorkingHours($timeLog);

            $timeLog->update([
                'regular_hours' => $calculatedHours['regular_hours'],
                'overtime_hours' => $calculatedHours['overtime_hours'],
                'late_hours' => $calculatedHours['late_hours'],
                'undertime_hours' => $calculatedHours['undertime_hours'],
                'total_hours' => $calculatedHours['total_hours'],
            ]);

            $recalculatedCount++;
        }

        return response()->json([
            'message' => "Successfully recalculated {$recalculatedCount} time logs",
            'success' => true,
            'recalculated_count' => $recalculatedCount
        ]);
    }

    /**
     * Recalculate single time log
     */
    public function recalculateTimeLog(Request $request, TimeLog $timeLog)
    {
        $this->authorize('edit time logs');

        if (!$timeLog->time_in || !$timeLog->time_out) {
            return response()->json([
                'message' => 'Time in and time out are required for calculation',
                'success' => false
            ], 400);
        }

        // Calculate working hours using the dynamic calculation method
        $calculatedHours = $this->calculateDynamicWorkingHours($timeLog);

        // Update the time log with calculated hours
        $timeLog->update([
            'regular_hours' => $calculatedHours['regular_hours'],
            'night_diff_regular_hours' => $calculatedHours['night_diff_regular_hours'] ?? 0,
            'overtime_hours' => $calculatedHours['overtime_hours'],
            'regular_overtime_hours' => $calculatedHours['regular_overtime_hours'] ?? 0,
            'night_diff_overtime_hours' => $calculatedHours['night_diff_overtime_hours'] ?? 0,
            'late_hours' => $calculatedHours['late_hours'],
            'undertime_hours' => $calculatedHours['undertime_hours'],
            'total_hours' => $calculatedHours['total_hours'],
        ]);

        return response()->json([
            'message' => 'Time log recalculated successfully',
            'success' => true,
            'data' => $calculatedHours
        ]);
    }

    /**
     * Calculate working hours dynamically based on employee schedule and grace periods
     */
    private function calculateDynamicWorkingHours(TimeLog $timeLog)
    {
        // Parse times properly - handle both string and Carbon objects
        if (!$timeLog->log_date) {
            throw new \Exception('Time log date is required for calculation');
        }
        $logDate = $timeLog->log_date instanceof Carbon ? $timeLog->log_date : Carbon::parse($timeLog->log_date);

        // Handle suspension records - check if it's a partial suspension with time logs
        if (in_array($timeLog->log_type, ['suspension', 'full_day_suspension', 'partial_suspension'])) {
            // Check if this is a partial suspension with actual time logs
            $suspensionSetting = \App\Models\NoWorkSuspendedSetting::where('date_from', '<=', $logDate->format('Y-m-d'))
                ->where('date_to', '>=', $logDate->format('Y-m-d'))
                ->first();

            if (
                $suspensionSetting &&
                ($suspensionSetting->type === 'partial_suspension' || $timeLog->log_type === 'partial_suspension') &&
                $suspensionSetting->time_from &&
                $suspensionSetting->time_to &&
                $timeLog->time_in &&
                $timeLog->time_out
            ) {

                // This is a partial suspension with time logs - calculate working hours excluding suspension period
                // Fall through to normal calculation but with suspension period excluded
                $isPartialSuspension = true;

                // Parse suspension times properly - they are datetime objects, extract time part
                if (is_string($suspensionSetting->time_from)) {
                    $suspensionStartTime = Carbon::parse($logDate->format('Y-m-d') . ' ' . $suspensionSetting->time_from);
                } else {
                    $timeOnly = $suspensionSetting->time_from->format('H:i:s');
                    $suspensionStartTime = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                }

                if (is_string($suspensionSetting->time_to)) {
                    $suspensionEndTime = Carbon::parse($logDate->format('Y-m-d') . ' ' . $suspensionSetting->time_to);
                } else {
                    $timeOnly = $suspensionSetting->time_to->format('H:i:s');
                    $suspensionEndTime = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                }
            } else {
                // Full day suspension or partial suspension without time logs
                return [
                    'total_hours' => 0,
                    'regular_hours' => 0,
                    'night_diff_regular_hours' => 0,
                    'overtime_hours' => 0,
                    'regular_overtime_hours' => 0,
                    'night_diff_overtime_hours' => 0,
                    'late_hours' => 0,
                    'undertime_hours' => 0,
                ];
            }
        } else {
            $isPartialSuspension = false;
            $suspensionStartTime = null;
            $suspensionEndTime = null;
        }

        if (!$timeLog->time_in || !$timeLog->time_out) {
            throw new \Exception('Time in and time out are required for calculation');
        }

        // Parse time fields - handle both string and datetime objects properly
        if (is_string($timeLog->time_in)) {
            $actualTimeIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->time_in);
        } else {
            // time_in is a Carbon datetime object, use only the time part with the correct date
            $timeOnly = $timeLog->time_in->format('H:i:s');
            $actualTimeIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
        }

        if (is_string($timeLog->time_out)) {
            $actualTimeOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->time_out);
        } else {
            // time_out is a Carbon datetime object, use only the time part with the correct date
            $timeOnly = $timeLog->time_out->format('H:i:s');
            $actualTimeOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
        }

        // Handle next day time out
        if ($actualTimeOut->lt($actualTimeIn)) {
            $actualTimeOut->addDay();
        }

        // Get employee's time schedule
        $employee = $timeLog->employee;
        $timeSchedule = $employee->timeSchedule ?? null;

        // Determine scheduled times based on day type
        // For all day types (regular, rest day, holidays), we'll use the same grace period logic
        // but may adjust the scheduled times based on context

        $logType = $timeLog->log_type ?? 'regular_workday';
        $isRestDay = in_array($logType, ['rest_day', 'rest_day_regular_holiday', 'rest_day_special_holiday']);
        $isHoliday = in_array($logType, ['special_holiday', 'regular_holiday', 'rest_day_regular_holiday', 'rest_day_special_holiday']);

        // Get scheduled times - use employee schedule for all day types
        // This ensures grace period works consistently across all day types
        $scheduledStartTime = $timeSchedule ? $timeSchedule->time_in->format('H:i') : '08:00';
        $scheduledEndTime = $timeSchedule ? $timeSchedule->time_out->format('H:i') : '17:00';

        $schedStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $scheduledStartTime);
        $schedEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $scheduledEndTime);

        // Handle next day scheduled end time
        if ($schedEnd->lt($schedStart)) {
            $schedEnd->addDay();
        }

        // Get grace period settings - apply to ALL day types (except overtime threshold)
        $gracePeriodSettings = \App\Models\GracePeriodSetting::current();
        $lateGracePeriodMinutes = $gracePeriodSettings->late_grace_minutes;
        $undertimeGracePeriodMinutes = $gracePeriodSettings->undertime_grace_minutes;

        // NEW: Use schedule-specific overtime threshold instead of global setting
        $overtimeThresholdMinutes = $timeSchedule ? $timeSchedule->getOvertimeThresholdMinutes() : 480; // Default 8 hours

        // STEP 1: Calculate work period based on employee schedule boundaries and grace period
        // Apply late grace period for ALL day types: if employee is late but within grace period, 
        // treat as if they came in at scheduled time
        $workStartTime = $schedStart; // Default to scheduled start time

        if ($actualTimeIn->gt($schedStart)) {
            $lateMinutes = $schedStart->diffInMinutes($actualTimeIn);
            if ($lateMinutes > $lateGracePeriodMinutes) {
                // Beyond grace period, use actual time in
                $workStartTime = $actualTimeIn;
            }
            // If within grace period, keep workStartTime as scheduled start time
        } else {
            // Employee came in early or on time, use scheduled start time
            $workStartTime = $schedStart;
        }

        // Work end time - apply undertime grace period logic
        $workEndTime = $actualTimeOut;

        // Apply undertime grace period: if employee left early but within grace period,
        // treat as if they left at scheduled time
        if ($actualTimeOut->lt($schedEnd)) {
            $earlyMinutes = $actualTimeOut->diffInMinutes($schedEnd);
            if ($earlyMinutes <= $undertimeGracePeriodMinutes) {
                // Within grace period, use scheduled end time for calculation
                $workEndTime = $schedEnd;
            }
            // If beyond grace period, use actual time out
        }

        // STEP 2: Calculate working hours based on employee's schedule break configuration
        $totalWorkingMinutes = 0;
        $adjustedWorkEndTime = $workEndTime;

        // Get employee's time schedule for break configuration
        $employee = $timeLog->employee;
        $timeSchedule = $employee->timeSchedule ?? null;

        // Check if employee has actual break in/out logs
        $hasActualBreakLogs = (
            $timeLog->break_in && $timeLog->break_out &&
            $timeLog->break_in !== null && $timeLog->break_out !== null &&
            $timeLog->break_in !== '' && $timeLog->break_out !== ''
        );

        if ($timeSchedule) {
            // Check schedule break configuration type
            $hasFlexibleBreak = ($timeSchedule->break_duration_minutes && $timeSchedule->break_duration_minutes > 0);
            $hasFixedBreak = ($timeSchedule->break_start && $timeSchedule->break_end);

            if ($hasFlexibleBreak && !$hasFixedBreak) {
                // ===== FLEXIBLE BREAK LOGIC =====
                // Check if employee used their break (default to true for backward compatibility)
                $usedBreak = $timeLog->used_break ?? true;

                $totalWorkingMinutes = $workStartTime->diffInMinutes($workEndTime);

                // Only deduct break duration if employee used their break
                if ($usedBreak) {
                    $totalWorkingMinutes = max(0, $totalWorkingMinutes - $timeSchedule->break_duration_minutes);
                }
            } else if ($hasFixedBreak) {
                // FIXED BREAK: Split calculation around break window

                if ($hasActualBreakLogs) {
                    // Employee has break logs - use hybrid approach
                    // Stop counting at schedule break start, resume at employee's break in
                    try {
                        $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));
                        $schedBreakEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i'));

                        if (is_string($timeLog->break_in)) {
                            $empBreakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_in);
                        } else {
                            $timeOnly = $timeLog->break_in->format('H:i:s');
                            $empBreakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                        }

                        if (is_string($timeLog->break_out)) {
                            $empBreakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_out);
                        } else {
                            $timeOnly = $timeLog->break_out->format('H:i:s');
                            $empBreakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                        }

                        // Calculate time before break (stop at the EARLIER of: scheduled break start OR employee break in)
                        $beforeBreak = 0;
                        $stopCountingAt = min($schedBreakStart, $empBreakIn);
                        if ($workStartTime->lt($stopCountingAt)) {
                            $beforeBreak = $workStartTime->diffInMinutes(min($stopCountingAt, $workEndTime));
                        }

                        // Calculate time from employee's break out to work end
                        $afterEmployeeBreak = 0;
                        if ($workEndTime->gt($empBreakOut)) {
                            $afterEmployeeBreak = $empBreakOut->diffInMinutes($workEndTime);
                        }

                        $totalWorkingMinutes = $beforeBreak + $afterEmployeeBreak;
                    } catch (Exception $e) {
                        // If parsing fails, fall back to schedule-only logic
                        $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));
                        $schedBreakEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i'));

                        $beforeBreak = 0;
                        $afterBreak = 0;

                        if ($workStartTime->lt($schedBreakStart)) {
                            $beforeBreak = $workStartTime->diffInMinutes(min($schedBreakStart, $workEndTime));
                        }

                        if ($workEndTime->gt($schedBreakEnd)) {
                            $afterBreakStart = max($schedBreakEnd, $workStartTime);
                            $afterBreak = $afterBreakStart->diffInMinutes($workEndTime);
                        }

                        $totalWorkingMinutes = $beforeBreak + $afterBreak;
                    }
                } else {
                    // No employee break logs - use scheduled break times to split calculation
                    $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));
                    $schedBreakEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i'));

                    $beforeBreak = 0;
                    $afterBreak = 0;

                    // Time worked before break period
                    if ($workStartTime->lt($schedBreakStart)) {
                        $beforeBreak = $workStartTime->diffInMinutes(min($schedBreakStart, $workEndTime));
                    }

                    // Time worked after break period
                    if ($workEndTime->gt($schedBreakEnd)) {
                        $afterBreakStart = max($schedBreakEnd, $workStartTime);
                        $afterBreak = $afterBreakStart->diffInMinutes($workEndTime);
                    }

                    $totalWorkingMinutes = $beforeBreak + $afterBreak;
                }
            } else {
                // No break configured in schedule
                $totalWorkingMinutes = $workStartTime->diffInMinutes($workEndTime);
            }
        } else if ($hasActualBreakLogs) {
            // No schedule but has break logs - use actual break times
            try {
                if (is_string($timeLog->break_in)) {
                    $breakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_in);
                } else {
                    $timeOnly = $timeLog->break_in->format('H:i:s');
                    $breakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                }

                if (is_string($timeLog->break_out)) {
                    $breakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_out);
                } else {
                    $timeOnly = $timeLog->break_out->format('H:i:s');
                    $breakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                }

                if ($breakOut->gt($breakIn)) {
                    $beforeBreak = 0;
                    $afterBreak = 0;

                    if ($workStartTime->lt($breakIn)) {
                        $beforeBreak = $workStartTime->diffInMinutes(min($breakIn, $workEndTime));
                    }

                    if ($workEndTime->gt($breakOut)) {
                        $afterBreak = max($breakOut, $workStartTime)->diffInMinutes($workEndTime);
                    }

                    $totalWorkingMinutes = $beforeBreak + $afterBreak;
                } else {
                    $totalWorkingMinutes = $workStartTime->diffInMinutes($workEndTime);
                }
            } catch (Exception $e) {
                $totalWorkingMinutes = $workStartTime->diffInMinutes($workEndTime);
            }
        } else {
            // No schedule and no break logs - calculate normal work time
            $totalWorkingMinutes = $workStartTime->diffInMinutes($workEndTime);
        }

        // NEW: For partial suspensions, we need to subtract only the suspension time that overlaps with actual work time
        // This should be done AFTER the break calculation to preserve break logic
        if ($isPartialSuspension && $suspensionStartTime && $suspensionEndTime) {
            // Log for debugging
            Log::info('Partial suspension calculation', [
                'employee_id' => $timeLog->employee_id,
                'date' => $logDate->format('Y-m-d'),
                'actual_time_in' => $actualTimeIn->format('H:i'),
                'actual_time_out' => $actualTimeOut->format('H:i'),
                'work_start' => $workStartTime->format('H:i'),
                'work_end' => $workEndTime->format('H:i'),
                'suspension_start' => $suspensionStartTime->format('H:i'),
                'suspension_end' => $suspensionEndTime->format('H:i'),
                'total_working_minutes_before' => $totalWorkingMinutes
            ]);

            // Calculate how much suspension time overlaps with actual work time (after breaks have been calculated)
            // We need to subtract from $totalWorkingMinutes only the overlap between suspension period and work periods

            // Find the overlap between suspension period and work period
            $workPeriodStart = $workStartTime;
            $workPeriodEnd = $workEndTime;

            // Calculate overlap between suspension and work period
            $overlapStart = max($suspensionStartTime, $workPeriodStart);
            $overlapEnd = min($suspensionEndTime, $workPeriodEnd);

            $suspensionOverlapMinutes = 0;
            if ($overlapStart->lt($overlapEnd)) {
                // There is overlap between suspension and work period
                $suspensionOverlapMinutes = $overlapStart->diffInMinutes($overlapEnd);

                // However, if there's a fixed break in the overlap period, we need to exclude the break time
                // from the suspension overlap since break time wasn't counted in work time anyway
                if ($timeSchedule && $timeSchedule->break_start && $timeSchedule->break_end) {
                    $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));
                    $schedBreakEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i'));

                    // Check if break period overlaps with suspension overlap
                    $breakOverlapStart = max($overlapStart, $schedBreakStart);
                    $breakOverlapEnd = min($overlapEnd, $schedBreakEnd);

                    if ($breakOverlapStart->lt($breakOverlapEnd)) {
                        $breakOverlapMinutes = $breakOverlapStart->diffInMinutes($breakOverlapEnd);
                        $suspensionOverlapMinutes = max(0, $suspensionOverlapMinutes - $breakOverlapMinutes);
                    }
                }
            }

            // Subtract the suspension overlap from total working minutes
            $totalWorkingMinutes = max(0, $totalWorkingMinutes - $suspensionOverlapMinutes);

            Log::info('Suspension overlap calculation', [
                'suspension_overlap_minutes' => $suspensionOverlapMinutes,
                'total_working_minutes_after' => $totalWorkingMinutes
            ]);
        }

        // STEP 3: Convert to hours
        $totalHours = max(0, $totalWorkingMinutes) / 60;

        // STEP 4: Calculate late hours (consistent with grace period logic for ALL day types)
        $lateMinutes = 0;
        if ($actualTimeIn->gt($schedStart)) {
            $actualLateMinutes = $schedStart->diffInMinutes($actualTimeIn);

            // Only count late hours if beyond grace period (same logic as work start time)
            // This applies to regular days, rest days, holidays, etc.
            if ($actualLateMinutes > $lateGracePeriodMinutes) {
                // Only charge for the time beyond the grace period
                $lateMinutes = $actualLateMinutes - $lateGracePeriodMinutes;
            }
            // If within grace period, lateMinutes stays 0
        }

        // STEP 5: Calculate standard work hours based on schedule break configuration
        $standardWorkMinutes = $schedStart->diffInMinutes($schedEnd);

        if ($timeSchedule) {
            // Check schedule break configuration type
            $hasFlexibleBreak = ($timeSchedule->break_duration_minutes && $timeSchedule->break_duration_minutes > 0);
            $hasFixedBreak = ($timeSchedule->break_start && $timeSchedule->break_end);

            if ($hasFlexibleBreak && !$hasFixedBreak) {
                // Flexible break: subtract break duration from total scheduled time
                $standardWorkMinutes = max(0, $standardWorkMinutes - $timeSchedule->break_duration_minutes);
            } else if ($hasFixedBreak) {
                // Fixed break: subtract the break window duration
                $scheduledBreakMinutes = $timeSchedule->break_start->diffInMinutes($timeSchedule->break_end);
                $standardWorkMinutes = max(0, $standardWorkMinutes - $scheduledBreakMinutes);
            }
            // If no break configured, use full scheduled time
        }

        $standardHours = max(0, $standardWorkMinutes / 60);

        // STEP 6: Calculate regular and overtime hours with accurate overtime start time
        $actualHoursWorked = $totalHours;
        $overtimeThresholdHours = $overtimeThresholdMinutes / 60; // Convert minutes to hours

        // Determine the boundary for regular vs overtime hours
        // Use the LARGER of: assigned schedule hours OR overtime threshold
        $regularHoursBoundary = max($standardHours, $overtimeThresholdHours);

        // Regular hours = actual hours worked, but capped at the regular hours boundary
        $regularHours = min($actualHoursWorked, $regularHoursBoundary);

        // Overtime = any hours worked beyond the regular hours boundary
        $overtimeHours = max(0, $actualHoursWorked - $regularHoursBoundary);
        $regularOvertimeHours = 0;
        $nightDifferentialOvertimeHours = 0;

        // If there's overtime, calculate the exact overtime start time
        if ($overtimeHours > 0) {
            $overtimeStartTime = null;
            $regularHoursBoundaryMinutes = $regularHoursBoundary * 60;

            // Calculate the exact time when employee reaches regular hours boundary
            if ($timeSchedule && $timeSchedule->break_start && $timeSchedule->break_end && $hasActualBreakLogs) {
                // Fixed break with employee logs - calculate based on actual work pattern
                try {
                    $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));

                    if (is_string($timeLog->break_in)) {
                        $empBreakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_in);
                    } else {
                        $timeOnly = $timeLog->break_in->format('H:i:s');
                        $empBreakIn = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                    }

                    if (is_string($timeLog->break_out)) {
                        $empBreakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeLog->break_out);
                    } else {
                        $timeOnly = $timeLog->break_out->format('H:i:s');
                        $empBreakOut = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeOnly);
                    }

                    $stopCountingAt = min($schedBreakStart, $empBreakIn);
                    $minutesBeforeBreak = $workStartTime->diffInMinutes($stopCountingAt);

                    if ($regularHoursBoundaryMinutes <= $minutesBeforeBreak) {
                        // Regular hours completed before break
                        $overtimeStartTime = $workStartTime->copy()->addMinutes($regularHoursBoundaryMinutes);
                    } else {
                        // Regular hours completed after break
                        $remainingMinutesAfterBreak = $regularHoursBoundaryMinutes - $minutesBeforeBreak;
                        $overtimeStartTime = $empBreakOut->copy()->addMinutes($remainingMinutesAfterBreak);
                    }
                } catch (Exception $e) {
                    // Fallback calculation
                    $overtimeStartTime = $workStartTime->copy()->addMinutes($regularHoursBoundaryMinutes);
                }
            } else if ($timeSchedule && $timeSchedule->break_start && $timeSchedule->break_end && !$hasActualBreakLogs) {
                // Fixed break without employee logs - calculate based on schedule
                $schedBreakStart = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_start->format('H:i'));
                $schedBreakEnd = Carbon::parse($logDate->format('Y-m-d') . ' ' . $timeSchedule->break_end->format('H:i'));

                $minutesBeforeBreak = $workStartTime->diffInMinutes($schedBreakStart);

                if ($regularHoursBoundaryMinutes <= $minutesBeforeBreak) {
                    // Regular hours completed before break
                    $overtimeStartTime = $workStartTime->copy()->addMinutes($regularHoursBoundaryMinutes);
                } else {
                    // Regular hours completed after break
                    $remainingMinutesAfterBreak = $regularHoursBoundaryMinutes - $minutesBeforeBreak;
                    $overtimeStartTime = $schedBreakEnd->copy()->addMinutes($remainingMinutesAfterBreak);
                }
            } else {
                // No break logs OR flexible break - simple calculation
                $overtimeStartTime = $workStartTime->copy()->addMinutes($regularHoursBoundaryMinutes);

                // For flexible break, add the break duration ONLY if employee used their break
                if ($timeSchedule && $timeSchedule->break_duration_minutes && $timeSchedule->break_duration_minutes > 0 && !($timeSchedule->break_start && $timeSchedule->break_end)) {
                    // Check if employee used their break (default to true for backward compatibility)
                    $usedBreak = $timeLog->used_break ?? true;
                    if ($usedBreak) {
                        $overtimeStartTime->addMinutes($timeSchedule->break_duration_minutes);
                    }
                }
            }

            // Calculate night differential for the overtime period
            $overtimeEndTime = $workEndTime;

            if ($overtimeStartTime) {
                $nightDiffBreakdown = $this->calculateNightDifferentialHoursFixed($overtimeStartTime, $overtimeEndTime);
                $regularOvertimeHours = $nightDiffBreakdown['regular_overtime'];
                $nightDifferentialOvertimeHours = $nightDiffBreakdown['night_diff_overtime'];
            }
        }        // STEP 8: Calculate night differential for regular hours
        $nightDiffRegularHours = 0;
        if ($regularHours > 0) {
            // Use the calculated overtime start time if available, otherwise calculate it
            $regularWorkEndTime = null;

            if ($overtimeHours > 0 && isset($overtimeStartTime)) {
                // Use the exact overtime start time as regular work end time
                $regularWorkEndTime = $overtimeStartTime;
            } else {
                // No overtime, so regular hours end at work end time
                $regularWorkEndTime = $workEndTime;
            }

            $nightDiffRegularBreakdown = $this->calculateNightDifferentialForRegularHours($workStartTime, $regularWorkEndTime);
            $nightDiffRegularHours = $nightDiffRegularBreakdown['night_diff_regular_hours'];

            // Adjust regular hours to exclude ND hours (they're tracked separately)
            $regularHours = $regularHours - $nightDiffRegularHours;
        }

        // STEP 9: Calculate undertime with grace period (now simpler since workEndTime is adjusted)
        $undertimeHours = 0;

        // Check if employee left early (after grace period adjustment)
        if ($actualTimeOut->lt($schedEnd)) {
            $earlyMinutes = $actualTimeOut->diffInMinutes($schedEnd);

            // Apply undertime grace period - only count undertime beyond grace period
            if ($earlyMinutes > $undertimeGracePeriodMinutes) {
                // Only charge for the time beyond the grace period
                $undertimeMinutesToCharge = $earlyMinutes - $undertimeGracePeriodMinutes;
                $undertimeHours = $undertimeMinutesToCharge / 60;
            }
            // If within grace period (earlyMinutes <= grace period), no undertime charged
        }

        $lateHours = $lateMinutes / 60;

        return [
            'total_hours' => round($totalHours, 2),
            'regular_hours' => round($regularHours, 2),
            'night_diff_regular_hours' => round($nightDiffRegularHours, 2),
            'overtime_hours' => round($overtimeHours, 2),
            'regular_overtime_hours' => round($regularOvertimeHours, 2),
            'night_diff_overtime_hours' => round($nightDifferentialOvertimeHours, 2),
            'late_hours' => round($lateHours, 2),
            'undertime_hours' => round($undertimeHours, 2),
            'overtime_start_time' => $overtimeHours > 0 && isset($overtimeStartTime) ? $overtimeStartTime : null,
        ];
    }

    /**
     * Calculate night differential hours breakdown for regular hours
     */
    private function calculateNightDifferentialForRegularHours($workStartTime, $workEndTime)
    {
        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();

        if (!$nightDiffSetting || !$nightDiffSetting->is_active) {
            // No night differential configured
            return [
                'night_diff_regular_hours' => 0
            ];
        }

        // Get night differential time period
        $nightStart = \Carbon\Carbon::parse($workStartTime->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
        $nightEnd = \Carbon\Carbon::parse($workStartTime->format('Y-m-d') . ' ' . $nightDiffSetting->end_time);

        // Handle next day end time (e.g., 10 PM to 5 AM next day)
        if ($nightEnd->lte($nightStart)) {
            $nightEnd->addDay();
        }

        // Calculate overlap between regular work period and night differential period
        $overlapStart = $workStartTime->greaterThan($nightStart) ? $workStartTime : $nightStart;
        $overlapEnd = $workEndTime->lessThan($nightEnd) ? $workEndTime : $nightEnd;

        $nightDiffRegularHours = 0;
        if ($overlapStart->lessThan($overlapEnd)) {
            $nightDiffRegularHours = $overlapEnd->diffInHours($overlapStart, true);
        }

        return [
            'night_diff_regular_hours' => $nightDiffRegularHours
        ];
    }

    /**
     * Calculate night differential hours breakdown for overtime
     */
    private function calculateNightDifferentialHours($workStartTime, $workEndTime, $employeeOvertimeThreshold)
    {
        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();

        if (!$nightDiffSetting || !$nightDiffSetting->is_active) {
            // No night differential configured, all overtime is regular
            $totalOvertimeHours = $workStartTime->copy()->addHours($employeeOvertimeThreshold)->diffInHours($workEndTime, true);
            return [
                'regular_overtime' => $totalOvertimeHours,
                'night_diff_overtime' => 0
            ];
        }

        // Get night differential time period
        $nightStart = \Carbon\Carbon::parse($workStartTime->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
        $nightEnd = \Carbon\Carbon::parse($workStartTime->format('Y-m-d') . ' ' . $nightDiffSetting->end_time);

        // Handle next day end time (e.g., 10 PM to 5 AM next day)
        if ($nightEnd->lte($nightStart)) {
            $nightEnd->addDay();
        }

        // Calculate overtime period start (employee's scheduled hours after work start)
        $overtimeStart = $workStartTime->copy()->addHours($employeeOvertimeThreshold);

        // If overtime starts after work ends, no overtime
        if ($overtimeStart->gte($workEndTime)) {
            return [
                'regular_overtime' => 0,
                'night_diff_overtime' => 0
            ];
        }

        // Calculate overlap between overtime period and night differential period
        $overlapStart = $overtimeStart->greaterThan($nightStart) ? $overtimeStart : $nightStart;
        $overlapEnd = $workEndTime->lessThan($nightEnd) ? $workEndTime : $nightEnd;

        $nightDiffOvertimeHours = 0;
        if ($overlapStart->lessThan($overlapEnd)) {
            $nightDiffOvertimeHours = $overlapEnd->diffInHours($overlapStart, true);
        }

        // Total overtime hours
        $totalOvertimeHours = $overtimeStart->diffInHours($workEndTime, true);

        // Regular overtime hours = total overtime - night diff overtime
        $regularOvertimeHours = max(0, $totalOvertimeHours - $nightDiffOvertimeHours);

        return [
            'regular_overtime' => $regularOvertimeHours,
            'night_diff_overtime' => $nightDiffOvertimeHours
        ];
    }

    private function calculateNightDifferentialHoursFixed($overtimeStart, $overtimeEnd)
    {
        $nightDiffSetting = \App\Models\NightDifferentialSetting::current();

        if (!$nightDiffSetting || !$nightDiffSetting->is_active) {
            // No night differential configured, all overtime is regular
            $totalOvertimeHours = $overtimeStart->diffInHours($overtimeEnd, true);
            return [
                'regular_overtime' => $totalOvertimeHours,
                'night_diff_overtime' => 0
            ];
        }

        // Get night differential time period (10 PM to 5 AM)
        $nightStart = \Carbon\Carbon::parse($overtimeStart->format('Y-m-d') . ' ' . $nightDiffSetting->start_time);
        $nightEnd = \Carbon\Carbon::parse($overtimeStart->format('Y-m-d') . ' ' . $nightDiffSetting->end_time);

        // Handle next day end time (e.g., 10 PM to 5 AM next day)
        if ($nightEnd->lte($nightStart)) {
            $nightEnd->addDay();
        }

        // Calculate overlap between overtime period and night differential period
        $overlapStart = max($overtimeStart, $nightStart);
        $overlapEnd = min($overtimeEnd, $nightEnd);

        $nightDiffOvertimeHours = 0;
        if ($overlapStart->lt($overlapEnd)) {
            $nightDiffOvertimeHours = $overlapStart->diffInHours($overlapEnd, true);
        }

        // Total overtime hours
        $totalOvertimeHours = $overtimeStart->diffInHours($overtimeEnd, true);

        // Regular overtime hours = total overtime - night diff overtime
        $regularOvertimeHours = max(0, $totalOvertimeHours - $nightDiffOvertimeHours);

        return [
            'regular_overtime' => $regularOvertimeHours,
            'night_diff_overtime' => $nightDiffOvertimeHours
        ];
    }
}
